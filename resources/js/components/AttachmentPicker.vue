<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Icon from './Icon.vue';
import { fileSize, fileType } from '../types';

const props = withDefaults(defineProps<{ modelValue: File[]; disabled?: boolean; id?: string }>(), { disabled: false, id: 'attachment-files' });
const emit = defineEmits<{ 'update:modelValue': [files: File[]] }>();
const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const dragDepth = ref(0);
const selectionError = ref('');
const previews = ref<{ file: File; url: string | null; failed: boolean }[]>([]);
const totalSize = computed(() => props.modelValue.reduce((total, file) => total + file.size, 0));
const acceptedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'text/plain'];

function clearPreviews(): void {
    previews.value.forEach(item => { if (item.url) URL.revokeObjectURL(item.url); });
}

watch(() => props.modelValue, files => {
    clearPreviews();
    previews.value = files.map(file => ({ file, url: ['image/jpeg', 'image/png'].includes(file.type) ? URL.createObjectURL(file) : null, failed: false }));
    if (!files.length) selectionError.value = '';
}, { immediate: true });
onBeforeUnmount(clearPreviews);

function addFiles(files: File[]): void {
    if (props.disabled) return;
    selectionError.value = '';
    const unique = files.filter(file => !props.modelValue.some(existing => existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified));
    if (props.modelValue.length + unique.length > 5) {
        selectionError.value = 'Puedes adjuntar hasta 5 archivos. Quita uno antes de añadir más.';
        return;
    }
    const invalid = unique.find(file => file.size > 10 * 1024 * 1024 || !acceptedTypes.includes(file.type) && !(file.type === '' && /\.(jpe?g|png|pdf|txt)$/i.test(file.name)));
    if (invalid) {
        selectionError.value = invalid.size > 10 * 1024 * 1024 ? `${invalid.name} supera el límite de 10 MB.` : `${invalid.name} no es compatible. Usa JPG, PNG, PDF o TXT.`;
        return;
    }
    emit('update:modelValue', [...props.modelValue, ...unique]);
}

function selectFiles(event: Event): void {
    addFiles(Array.from((event.target as HTMLInputElement).files || []));
    if (input.value) input.value.value = '';
}

function dropFiles(event: DragEvent): void {
    dragging.value = false;
    dragDepth.value = 0;
    addFiles(Array.from(event.dataTransfer?.files || []));
}

function enterDropzone(): void {
    dragDepth.value++;
    dragging.value = true;
}

function leaveDropzone(): void {
    dragDepth.value = Math.max(0, dragDepth.value - 1);
    dragging.value = dragDepth.value > 0;
}

function removeFile(index: number): void {
    if (props.disabled) return;
    selectionError.value = '';
    emit('update:modelValue', props.modelValue.filter((_, fileIndex) => fileIndex !== index));
}
</script>

<template>
    <div class="attachment-picker" :aria-busy="disabled">
        <div class="attachment-dropzone" :class="{ 'is-dragging': dragging && !disabled, 'is-disabled': disabled }" @dragenter.prevent="enterDropzone" @dragover.prevent @dragleave.prevent="leaveDropzone" @drop.prevent="dropFiles">
            <span class="attachment-upload-icon"><Icon name="Upload" :size="24" /></span>
            <strong>Arrastra tus archivos aquí</strong>
            <span class="helper">o selecciónalos desde tu equipo</span>
            <button type="button" class="btn secondary" :disabled="disabled" :aria-describedby="`${id}-help`" @click="input?.click()"><Icon name="Plus" :size="16" />Seleccionar archivos</button>
            <label :for="id" class="sr-only">Seleccionar archivos adjuntos</label>
            <input :id="id" ref="input" class="sr-only" type="file" tabindex="-1" multiple accept=".jpg,.jpeg,.png,.pdf,.txt" :disabled="disabled" :aria-describedby="`${id}-help`" @change="selectFiles" />
            <p :id="`${id}-help`" class="helper">JPG, PNG, PDF o TXT · Hasta 5 archivos de 10 MB cada uno</p>
        </div>
        <p v-if="selectionError" class="attachment-selection-error" role="alert"><Icon name="AlertTriangle" :size="16" />{{ selectionError }}</p>
        <div v-if="previews.length" class="attachment-selection">
            <div class="attachment-selection-summary" aria-live="polite"><strong>{{ previews.length }} {{ previews.length === 1 ? 'archivo seleccionado' : 'archivos seleccionados' }}</strong><span>{{ fileSize(totalSize) }} en total</span></div>
            <ul class="attachment-selected-list">
                <li v-for="(item, index) in previews" :key="`${item.file.name}-${item.file.size}-${item.file.lastModified}`" class="attachment-selected-file">
                    <span class="attachment-selected-thumbnail">
                        <img v-if="item.url && !item.failed" :src="item.url" :alt="`Vista previa de ${item.file.name}`" @error="item.failed = true" />
                        <Icon v-else :name="item.url ? 'Image' : 'FileText'" :size="23" />
                    </span>
                    <div class="attachment-selected-info"><strong :title="item.file.name">{{ item.file.name }}</strong><span>{{ fileType(item.file.name) }} · {{ fileSize(item.file.size) }}</span></div>
                    <button type="button" class="icon-button" :disabled="disabled" :aria-label="`Quitar ${item.file.name}`" @click="removeFile(index)"><Icon name="X" :size="18" /></button>
                </li>
            </ul>
            <p class="helper">Los archivos se subirán al enviar el formulario.</p>
        </div>
    </div>
</template>
