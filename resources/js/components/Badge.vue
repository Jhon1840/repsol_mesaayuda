<script setup lang="ts">
import { statuses, priorities, type Status, type Priority } from '../types';
defineProps<{ value: Status | Priority; kind?: 'status' | 'priority' }>();
</script>
<template><span class="badge" :class="value"><span class="badge-dot"></span>{{ kind === 'status' ? statuses[value as Status] : priorities[value as Priority] }}</span></template>
