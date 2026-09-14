<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
</script>

<template>
  <section class="space-y-3">
    <h2 class="text-xl font-semibold">{{ t('inicio.titulo') }}</h2>
    <p class="text-slate-600">
      {{ t('inicio.bienvenida', { nombre: auth.me?.usuario.nombre ?? '' }) }}
    </p>
    <RouterLink to="/sucursales" class="inline-block text-slate-800 underline">
      {{ t('nav.sucursales') }} →
    </RouterLink>
  </section>
</template>
