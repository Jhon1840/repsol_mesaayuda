<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormErrors from '../../components/FormErrors.vue';
const props = defineProps<{ token: string; email: string }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
</script>
<template><Head title="Nueva contraseña" /><AuthLayout><form class="auth-form" @submit.prevent="form.post('/reset-password')"><div class="eyebrow">UN NUEVO COMIENZO</div><h1>Tu nueva contraseña<span class="heading-dot">.</span></h1><p>Elige una contraseña de al menos 10 caracteres con letras y números.</p><FormErrors :errors="form.errors" /><label class="field">Correo electrónico<input v-model="form.email" type="email" required autocomplete="email" /></label><label class="field">Nueva contraseña<input v-model="form.password" type="password" required minlength="10" autocomplete="new-password" /></label><label class="field">Confirmar contraseña<input v-model="form.password_confirmation" type="password" required autocomplete="new-password" /></label><button class="btn primary" :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Restablecer contraseña' }}</button></form></AuthLayout></template>
