<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import Icon from '../../components/Icon.vue';
import FormErrors from '../../components/FormErrors.vue';
import type { Category } from '../../types';
const props = defineProps<{ categories: Category[] }>();
const names = ref<Record<number, string>>(Object.fromEntries(props.categories.map(c => [c.id, c.name])));
watch(() => props.categories, categories => { names.value = Object.fromEntries(categories.map(c => [c.id, c.name])); });
const errors = ref<Record<string, string>>({});
const form = useForm({ name: '' });
function update(category: Category, is_active = category.is_active) { errors.value = {}; router.patch(`/support/categories/${category.id}`, { name: names.value[category.id], is_active }, { preserveScroll: true, onError: value => { errors.value = value; } }); }
</script>
<template><Head title="Categorías" /><AppLayout title="Categorías" section="Administración"><div class="page-heading"><div><div class="eyebrow">CADA SOLICITUD EN SU LUGAR</div><h1>Categorías<span class="heading-dot">.</span></h1><p>Organiza los tipos de problemas que atiende tu equipo.</p></div></div><FormErrors :errors="errors" /><div class="admin-grid"><div class="panel"><form v-for="category in categories" :key="category.id" class="category-row" @submit.prevent="update(category)"><span class="icon-tile"><Icon name="Tags" :size="17" /></span><input v-model="names[category.id]" :aria-label="`Nombre de ${category.name}`" required maxlength="80" /><label class="checkbox-label"><input type="checkbox" :checked="category.is_active" @change="update(category, ($event.target as HTMLInputElement).checked)" />Activa</label><button class="btn secondary small">Guardar</button></form><div v-if="!categories.length" class="empty-state"><Icon name="Tags" /><p>Crea la primera categoría.</p></div></div><form class="panel admin-form" @submit.prevent="form.post('/support/categories', { onSuccess: () => form.reset() })"><h2>Nueva categoría</h2><FormErrors :errors="form.errors" /><label class="field">Nombre<input v-model="form.name" required maxlength="80" placeholder="Ej. Red / Internet" /></label><button class="btn primary" :disabled="form.processing"><Icon name="Plus" />Crear categoría</button><p class="helper" style="margin-top:18px">Desactivar una categoría la oculta en nuevas solicitudes y conserva los tickets anteriores.</p></form></div></AppLayout></template>
