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
  <!-- Hero (banda blanca) -->
  <section :style="{ background: 'var(--superficie)' }">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 py-20 sm:py-28 text-center">
      <span class="tu-badge tu-badge-exito mb-6">{{ $t('landing.prueba', { dias: DIAS_PRUEBA }) }}</span>
      <h1
        class="font-bold tracking-tight text-5xl sm:text-6xl lg:text-7xl"
        style="letter-spacing: -0.03em; line-height: 1.05"
      >
        {{ $t('landing.titulo') }}
      </h1>
      <p
        class="mt-6 text-xl sm:text-2xl mx-auto max-w-2xl"
        style="color: var(--texto-suave); letter-spacing: -0.01em"
      >
        {{ $t('landing.subtitulo') }}
      </p>
      <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
        <RouterLink class="tu-btn tu-btn-primario text-base px-7 py-3" :to="{ name: 'registro' }">
          {{ $t('landing.ctaRegistrar') }}
        </RouterLink>
        <RouterLink class="tu-btn tu-btn-fantasma text-base px-7 py-3" :to="{ name: 'directorio' }">
          {{ $t('landing.ctaDirectorio') }}
        </RouterLink>
      </div>
      <p class="mt-4 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.pieHero') }}</p>
    </div>
  </section>

  <!-- Características (banda gris) -->
  <section :style="{ background: 'var(--fondo)' }">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 py-20 sm:py-28">
      <h2 class="text-3xl sm:text-4xl font-bold tracking-tight" style="letter-spacing: -0.02em">
        {{ $t('landing.seccionTitulo') }}
      </h2>
      <p class="mt-3 text-lg max-w-2xl" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('landing.seccionSub') }}
      </p>
      <div class="mt-10 grid gap-5 sm:grid-cols-3">
        <div v-for="p in puntos" :key="p.clave" class="tu-card p-7">
          <div class="text-3xl" aria-hidden="true">{{ p.icono }}</div>
          <h3 class="mt-4 font-semibold text-xl tracking-tight">{{ $t(`landing.puntos.${p.clave}`) }}</h3>
          <p class="mt-2" :style="{ color: 'var(--texto-suave)' }">
            {{ $t(`landing.puntos.${p.clave}Desc`) }}
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA final (banda blanca) -->
  <section :style="{ background: 'var(--superficie)' }">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 py-20 sm:py-28 text-center">
      <h2
        class="font-bold tracking-tight text-4xl sm:text-5xl"
        style="letter-spacing: -0.025em; line-height: 1.07"
      >
        {{ $t('landing.ctaFinalTitulo') }}
      </h2>
      <p class="mt-4 text-lg" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('landing.ctaFinalSub', { dias: DIAS_PRUEBA }) }}
      </p>
      <RouterLink
        class="tu-btn tu-btn-primario text-base px-7 py-3 mt-8"
        :to="{ name: 'registro' }"
      >
        {{ $t('landing.ctaRegistrar') }}
      </RouterLink>
    </div>
  </section>
</template>
