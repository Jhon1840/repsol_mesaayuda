<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import Icon from './Icon.vue';
import { date, fileSize, fileType, type Attachment } from '../types';

const props = defineProps<{ files: Attachment[] }>();
const images = computed(() => props.files.filter(file => file.preview_url));
const dialog = ref<HTMLDialogElement | null>(null);
const selected = ref<Attachment | null>(null);
const loadedThumbnails = ref(new Set<number>());
const failedThumbnails = ref(new Set<number>());
const imageLoaded = ref(false);
const imageFailed = ref(false);
const selectedIndex = computed(() => images.value.findIndex(file => file.id === selected.value?.id));

async function openPreview(file: Attachment): Promise<void> {
    selected.value = file;
    imageLoaded.value = false;
    imageFailed.value = false;
    await nextTick();
    dialog.value?.showModal();
}

function closePreview(): void {
    dialog.value?.close();
    selected.value = null;
}

function navigateImage(direction: number): void {
    const next = images.value[(selectedIndex.value + direction + images.value.length) % images.value.length];
    if (next) {
        selected.value = next;
        imageLoaded.value = false;
        imageFailed.value = false;
    }
}

function handleKeyboard(event: KeyboardEvent): void {
    if (images.value.length < 2) return;
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        navigateImage(event.key === 'ArrowLeft' ? -1 : 1);
    }
}

function closeOnBackdrop(event: MouseEvent): void {
    if (event.target !== dialog.value || !dialog.value) return;
    const bounds = dialog.value.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
        closePreview();
    }
}
</script>

<template>
    <section class="attachment-gallery" aria-label="Archivos adjuntos">
        <div v-if="files.length" class="attachment-section-heading">
            <div>
                <h2>Archivos adjuntos <span class="neutral-count">{{ files.length }}</span></h2>
                <p>Abre las imágenes para ver los detalles o descarga un archivo.</p>
            </div>
        </div>
        <div v-if="files.length" class="attachment-grid">
            <article v-for="file in files" :key="file.id" class="attachment-card" :class="{ 'attachment-document': !file.preview_url }">
                <button v-if="file.preview_url" type="button" class="attachment-thumbnail" :aria-label="`Ver imagen ${file.original_name}`" @click="openPreview(file)">
                    <img v-if="!failedThumbnails.has(file.id)" :src="file.preview_url" :alt="file.original_name" loading="lazy" decoding="async" @load="loadedThumbnails.add(file.id)" @error="failedThumbnails.add(file.id)" />
                    <span v-if="failedThumbnails.has(file.id)" class="attachment-preview-state"><Icon name="Image" :size="28" /><span>Vista previa no disponible</span></span>
                    <span v-else-if="!loadedThumbnails.has(file.id)" class="attachment-preview-state" role="status">Cargando imagen…</span>
                    <span class="attachment-preview-label"><Icon name="Expand" :size="15" />Ver imagen</span>
                </button>
                <div v-else class="attachment-document-cover" aria-hidden="true">
                    <span class="attachment-file-icon"><Icon :name="['pdf', 'txt'].includes(file.original_name.split('.').pop()?.toLowerCase() || '') ? 'FileText' : 'File'" :size="28" /></span>
                    <span class="attachment-type">{{ fileType(file.original_name) }}</span>
                </div>
                <div class="attachment-card-body">
                    <h3 :title="file.original_name">{{ file.original_name }}</h3>
                    <p class="attachment-file-meta">{{ fileType(file.original_name) }} <span aria-hidden="true">·</span> {{ fileSize(file.size) }}</p>
                    <time :datetime="file.created_at">Adjuntado el {{ date(file.created_at, true) }}</time>
                    <div class="attachment-card-actions">
                        <button v-if="file.preview_url" type="button" class="text-button" @click="openPreview(file)"><Icon name="Expand" :size="16" />Vista previa</button>
                        <a :href="file.download_url" class="text-button" :aria-label="`Descargar ${file.original_name}`"><Icon name="Download" :size="16" />Descargar</a>
                    </div>
                </div>
            </article>
        </div>
        <div v-else class="attachment-empty">
            <span class="icon-tile"><Icon name="Paperclip" :size="24" /></span>
            <h2>Aún no hay archivos</h2>
            <p>Adjunta una captura o un documento para ayudar a resolver la solicitud.</p>
        </div>
        <dialog ref="dialog" class="image-viewer" aria-labelledby="image-viewer-title" @cancel.prevent="closePreview" @click="closeOnBackdrop" @keydown="handleKeyboard">
            <template v-if="selected">
                <div class="image-viewer-header">
                    <div class="image-viewer-title">
                        <h2 id="image-viewer-title">{{ selected.original_name }}</h2>
                        <p>{{ fileType(selected.original_name) }} · {{ fileSize(selected.size) }} <span v-if="images.length > 1">· Imagen {{ selectedIndex + 1 }} de {{ images.length }}</span></p>
                    </div>
                    <button type="button" class="icon-button" aria-label="Cerrar vista previa" autofocus @click="closePreview"><Icon name="X" :size="22" /></button>
                </div>
                <div class="image-viewer-stage" :aria-busy="!imageLoaded && !imageFailed">
                    <img v-if="!imageFailed" :key="selected.id" :src="selected.preview_url!" :alt="selected.original_name" @load="imageLoaded = true" @error="imageFailed = true" />
                    <div v-if="imageFailed" class="attachment-preview-state" role="status"><Icon name="Image" :size="32" /><strong>No se pudo cargar la imagen</strong><span>Puedes intentar descargar el archivo.</span></div>
                    <span v-else-if="!imageLoaded" class="attachment-preview-state" role="status">Cargando imagen…</span>
                </div>
                <div class="image-viewer-footer">
                    <div v-if="images.length > 1" class="image-viewer-navigation">
                        <button type="button" class="icon-button" aria-label="Imagen anterior" @click="navigateImage(-1)"><Icon name="ChevronLeft" /></button>
                        <span>{{ selectedIndex + 1 }} / {{ images.length }}</span>
                        <button type="button" class="icon-button" aria-label="Imagen siguiente" @click="navigateImage(1)"><Icon name="ChevronRight" /></button>
                    </div>
                    <span v-else class="helper">Vista completa de la imagen</span>
                    <a :href="selected.download_url" class="btn primary"><Icon name="Download" :size="17" />Descargar imagen</a>
                </div>
            </template>
        </dialog>
    </section>
</template>
