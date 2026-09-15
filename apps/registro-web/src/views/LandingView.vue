<script setup lang="ts">
import { RouterLink } from 'vue-router'

const DIAS_PRUEBA = 14

const puntos = [
  { icono: '🔒', clave: 'aislado' },
  { icono: '📅', clave: 'reservas' },
  { icono: '💳', clave: 'pagos' },
] as const
</script>

<template>
  <section class="mx-auto max-w-6xl px-4">
    <div class="py-16 sm:py-24 text-center max-w-3xl mx-auto">
      <span class="tu-badge tu-badge-exito mb-5">{{ $t('landing.prueba', { dias: DIAS_PRUEBA }) }}</span>
      <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight leading-tight">
        {{ $t('landing.titulo') }}
      </h1>
      <p class="mt-5 text-lg" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('landing.subtitulo') }}
      </p>
      <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <RouterLink class="tu-btn tu-btn-primario text-base px-6 py-3" :to="{ name: 'registro' }">
          {{ $t('landing.ctaRegistrar') }}
        </RouterLink>
        <RouterLink class="tu-btn tu-btn-fantasma text-base px-6 py-3" :to="{ name: 'directorio' }">
          {{ $t('landing.ctaDirectorio') }}
        </RouterLink>
      </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-3 pb-20">
      <div v-for="p in puntos" :key="p.clave" class="tu-card p-6">
        <div class="text-3xl" aria-hidden="true">{{ p.icono }}</div>
        <h3 class="mt-3 font-bold text-lg">{{ $t(`landing.puntos.${p.clave}`) }}</h3>
        <p class="mt-1.5 text-sm" :style="{ color: 'var(--texto-suave)' }">
          {{ $t(`landing.puntos.${p.clave}Desc`) }}
        </p>
      </div>
    </div>
  </section>
</template>
