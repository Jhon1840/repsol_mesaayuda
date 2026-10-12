<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormErrors from '../../components/FormErrors.vue';
defineProps<{ status: string | null }>();
const form = useForm({ email: '' });
</script>
<template><Head title="Recuperar contraseña" /><AuthLayout><form class="auth-form" @submit.prevent="form.post('/forgot-password')"><div class="eyebrow">RECUPERA TU ACCESO</div><h1>Volvamos a conectar<span class="heading-dot">.</span></h1><p>Te enviaremos un enlace para restablecer tu contraseña.</p><div v-if="status" class="toast" role="status">{{ status }}</div><FormErrors :errors="form.errors" /><label class="field">Correo electrónico<input v-model="form.email" type="email" required autocomplete="email" autofocus /></label><button class="btn primary" :disabled="form.processing">{{ form.processing ? 'Enviando…' : 'Enviar enlace' }}</button><Link href="/login" class="auth-back text-button">Volver a iniciar sesión</Link></form></AuthLayout></template>
