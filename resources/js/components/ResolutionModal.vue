<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import FormErrors from './FormErrors.vue';
import type { Ticket } from '../types';
const props = defineProps<{ ticket: Ticket | null }>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);
const form = useForm({ version: 0, status: 'completed', resolution: '' });
watch(() => props.ticket, ticket => {
    if (ticket) { form.reset(); form.clearErrors(); form.version = ticket.version; dialog.value?.showModal(); }
    else { dialog.value?.close(); }
});
function submit() {
    if (props.ticket) form.patch(`/tickets/${props.ticket.id}`, { preserveScroll: true, onSuccess: () => emit('close') });
}
</script>
<template><dialog ref="dialog" class="modal" @cancel.prevent="emit('close')"><form @submit.prevent="submit"><div class="modal-top"><span class="icon-tile green"><Icon name="CircleCheck" :size="24" /></span><button type="button" class="icon-button" aria-label="Cerrar" @click="emit('close')"><Icon name="X" /></button></div><h2>Finalizar solicitud</h2><p class="muted">{{ ticket?.code }} · {{ ticket?.subject }}</p><FormErrors :errors="form.errors" /><label class="field">Solución aplicada <span class="required">*</span><textarea v-model="form.resolution" rows="5" required maxlength="10000" placeholder="Describe qué hiciste para resolver la solicitud…" autofocus /></label><p class="helper">El solicitante podrá consultar esta solución en su ticket.</p><div class="form-actions"><button type="button" class="btn secondary" @click="emit('close')">Cancelar</button><button class="btn primary" :disabled="form.processing"><Icon name="Check" />{{ form.processing ? 'Guardando…' : 'Finalizar ticket' }}</button></div></form></dialog></template>
