<script setup lang="ts">
defineProps<{ errors: Record<string, string> }>();
</script>
<template><div v-if="Object.keys(errors).length" class="error-box" role="alert" tabindex="-1"><strong>No se pudo completar la operación</strong><p v-for="(error, key) in errors" :key="key">{{ error }}</p></div></template>
