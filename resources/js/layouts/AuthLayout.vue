<script setup lang="ts">
import Icon from '../components/Icon.vue';
</script>
<template><main class="auth-page"><section class="auth-story"><a href="/" class="brand"><span class="brand-mark"><Icon name="Headset" :size="24" /></span><span>Mesa<span class="brand-light"> de ayuda</span><small>WORKSPACE INTERNO</small></span></a><div class="auth-story-main"><div class="eyebrow">TECNOLOGÍA QUE ACOMPAÑA</div><h1>Menos obstáculos.<br>Más soluciones.</h1><p>Un espacio para conectar con soporte y seguir cada solicitud, de principio a solución.</p><div class="auth-promise"><Icon name="ShieldCheck" :size="20" />Tu equipo de soporte, más cerca.</div></div><p class="auth-story-foot">Mesa de ayuda · Equipo de tecnología</p></section><section class="auth-form-area"><slot /></section></main></template>
