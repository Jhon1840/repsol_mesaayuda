<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';
import Icon from '../../components/Icon.vue';
import FormErrors from '../../components/FormErrors.vue';
const form = useForm({ email: '', password: '', remember: false });
function submit() { form.post('/login', { onFinish: () => form.reset('password') }); }
</script>
<template><Head title="Iniciar sesión" /><AuthLayout><form class="auth-form" @submit.prevent="submit"><div class="eyebrow">BIENVENIDO A TU ESPACIO</div><h1>Qué bueno verte<span class="heading-dot">.</span></h1><p>Inicia sesión con tu cuenta interna para continuar.</p><FormErrors :errors="form.errors" /><label class="field">Correo electrónico<input v-model="form.email" type="email" autocomplete="username" required autofocus placeholder="nombre@empresa.com" /></label><label class="field">Contraseña<input v-model="form.password" type="password" autocomplete="current-password" required placeholder="Ingresa tu contraseña" /></label><div class="auth-options"><label class="checkbox-label"><input v-model="form.remember" type="checkbox" />Recordarme</label><Link href="/forgot-password">¿Olvidaste tu contraseña?</Link></div><button class="btn primary" :disabled="form.processing">{{ form.processing ? 'Ingresando…' : 'Iniciar sesión' }}<Icon name="ArrowRight" :size="17" /></button><div class="auth-foot">¿Necesitas una cuenta? Contacta al equipo de soporte.</div></form></AuthLayout></template>
