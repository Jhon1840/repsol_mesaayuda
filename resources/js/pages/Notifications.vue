<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../layouts/AppLayout.vue';
import Icon from '../components/Icon.vue';
import Pagination from '../components/Pagination.vue';
import { date, type Pagination as Paginated } from '../types';
defineProps<{ notifications: Paginated<{ id: string; data: { code: string; message: string }; read_at: string | null; created_at: string }> }>();
</script>
<template><Head title="Notificaciones" /><AppLayout title="Notificaciones"><div class="page-heading"><div><div class="eyebrow">AL DÍA CON TU EQUIPO</div><h1>Notificaciones<span class="heading-dot">.</span></h1><p>Las novedades de tus solicitudes, en un solo lugar.</p></div><button class="btn secondary" @click="router.post('/notifications/read-all')"><Icon name="CheckCheck" />Marcar todas como leídas</button></div><div class="panel"><article v-for="notification in notifications.data" :key="notification.id" :class="['notification-row', { unread: !notification.read_at }]"><span class="icon-tile"><Icon :name="notification.read_at ? 'Check' : 'Bell'" :size="19" /></span><div><h3>{{ notification.data.message }}</h3><p>{{ notification.data.code }} · {{ date(notification.created_at, true) }}</p></div><button class="btn secondary small" @click="router.post(`/notifications/${notification.id}/read`)">Ver solicitud<Icon name="ArrowRight" :size="14" /></button></article><div v-if="!notifications.data.length" class="empty-state"><span class="icon-tile"><Icon name="Bell" :size="25" /></span><h3>Todo al día</h3><p>Aquí aparecerán las novedades de tus solicitudes.</p></div><div v-if="notifications.total" class="table-bottom"><span>{{ notifications.total }} notificaciones</span><Pagination :links="notifications.links" /></div></div></AppLayout></template>
