<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{ links: { url: string | null; label: string; active: boolean }[] }>();
const label = (v: string) => v.includes('Previous') || v.includes('Anterior') ? 'Anterior' : v.includes('Next') || v.includes('Siguiente') ? 'Siguiente' : v;
</script>
<template><nav class="pagination" aria-label="Paginación"><template v-for="(link, i) in links" :key="i"><Link v-if="link.url" :href="link.url" :class="['page-link', { selected: link.active }]" :aria-current="link.active ? 'page' : undefined" preserve-scroll>{{ label(link.label) }}</Link><span v-else class="page-link disabled">{{ label(link.label) }}</span></template></nav></template>
