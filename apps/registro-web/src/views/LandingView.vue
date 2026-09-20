<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

const { t } = useI18n()
const DIAS_PRUEBA = 14

const funciones = [
  { icono: '📅', clave: 'agenda' },
  { icono: '✅', clave: 'reservas' },
  { icono: '🎟️', clave: 'membresias' },
  { icono: '💳', clave: 'pagos' },
  { icono: '🛒', clave: 'pos' },
  { icono: '📊', clave: 'reportes' },
] as const

const pasos = [
  { n: 1, t: 'p1t', d: 'p1d' },
  { n: 2, t: 'p2t', d: 'p2d' },
  { n: 3, t: 'p3t', d: 'p3d' },
] as const

const faqs = [
  { q: 'q1', a: 'a1' },
  { q: 'q2', a: 'a2' },
  { q: 'q3', a: 'a3' },
  { q: 'q4', a: 'a4' },
  { q: 'q5', a: 'a5' },
] as const

const verticales = computed(() => t('landing.paraQuien.items').split(','))
</script>

<template>
  <!-- Hero (banda blanca) -->
  <section :style="{ background: 'var(--superficie)' }">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 py-20 sm:py-28 text-center">
      <span class="tu-badge tu-badge-exito mb-6">{{ $t('landing.prueba', { dias: DIAS_PRUEBA }) }}</span>
      <h1 class="font-bold tracking-tight text-5xl sm:text-6xl lg:text-7xl" style="letter-spacing: -0.03em; line-height: 1.05">
        {{ $t('landing.titulo') }}
      </h1>
      <p class="mt-6 text-xl sm:text-2xl mx-auto max-w-2xl" style="color: var(--texto-suave); letter-spacing: -0.01em">
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

  <!-- Cómo funciona (banda gris) -->
  <section :style="{ background: 'var(--fondo)' }">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 py-20 sm:py-28">
      <h2 class="text-3xl sm:text-4xl font-bold tracking-tight" style="letter-spacing: -0.02em">
        {{ $t('landing.comoFunciona.titulo') }}
      </h2>
      <p class="mt-3 text-lg max-w-2xl" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.comoFunciona.subtitulo') }}</p>
      <div class="mt-10 grid gap-5 sm:grid-cols-3">
        <div v-for="p in pasos" :key="p.n" class="tu-card p-7">
          <div
            class="h-10 w-10 rounded-full inline-flex items-center justify-center text-lg font-bold"
            :style="{ background: 'var(--primario)', color: 'var(--primario-contraste)' }"
          >
            {{ p.n }}
          </div>
          <h3 class="mt-4 font-semibold text-xl tracking-tight">{{ $t(`landing.comoFunciona.${p.t}`) }}</h3>
          <p class="mt-2" :style="{ color: 'var(--texto-suave)' }">{{ $t(`landing.comoFunciona.${p.d}`) }}</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Producto (banda blanca) — mockup ilustrativo del sistema -->
  <section :style="{ background: 'var(--superficie)' }">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 py-20 sm:py-28 text-center">
      <h2 class="text-3xl sm:text-4xl font-bold tracking-tight" style="letter-spacing: -0.02em">
        {{ $t('landing.producto.titulo') }}
      </h2>
      <p class="mt-3 text-lg mx-auto max-w-2xl" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.producto.subtitulo') }}</p>

      <!-- Ventana de app -->
      <div class="mt-10 mx-auto max-w-4xl text-left rounded-2xl overflow-hidden" :style="{ border: '1px solid var(--borde)' }">
        <!-- Barra de título -->
        <div class="flex items-center gap-2 px-4 py-3" :style="{ background: 'var(--fondo)', borderBottom: '1px solid var(--borde)' }">
          <span class="h-3 w-3 rounded-full" style="background: #ff5f57"></span>
          <span class="h-3 w-3 rounded-full" style="background: #febc2e"></span>
          <span class="h-3 w-3 rounded-full" style="background: #28c840"></span>
          <span class="ml-3 text-xs font-semibold" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.producto.barra') }} · TurnoUno</span>
        </div>
        <div class="flex">
          <!-- Sidebar simulada -->
          <div class="hidden sm:block w-40 shrink-0 p-3 space-y-2" :style="{ background: 'var(--barra)' }">
            <div class="h-6 rounded-lg" :style="{ background: 'var(--barra-activo)' }"></div>
            <div v-for="i in 5" :key="i" class="h-4 rounded-md" :style="{ background: 'var(--barra-suave)' }"></div>
          </div>
          <!-- Contenido -->
          <div class="flex-1 p-4 sm:p-6" :style="{ background: 'var(--superficie)' }">
            <div class="grid grid-cols-3 gap-3">
              <div class="tu-card p-3">
                <div class="text-2xl font-extrabold">128</div>
                <div class="text-[11px]" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.producto.m1') }}</div>
              </div>
              <div class="tu-card p-3">
                <div class="text-2xl font-extrabold">86%</div>
                <div class="text-[11px]" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.producto.m2') }}</div>
              </div>
              <div class="tu-card p-3">
                <div class="text-2xl font-extrabold">24</div>
                <div class="text-[11px]" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.producto.m3') }}</div>
              </div>
            </div>
            <h4 class="mt-5 font-semibold text-sm">{{ $t('landing.producto.agendaTitulo') }}</h4>
            <div class="mt-2 space-y-2">
              <div class="tu-card p-3 flex items-center justify-between">
                <span class="text-sm font-medium">{{ $t('landing.producto.clase1') }}</span>
                <span class="tu-badge tu-badge-aviso">{{ $t('landing.producto.lleno') }}</span>
              </div>
              <div class="tu-card p-3 flex items-center justify-between">
                <span class="text-sm font-medium">{{ $t('landing.producto.clase2') }}</span>
                <span class="tu-badge tu-badge-exito">{{ $t('landing.producto.lugares') }}</span>
              </div>
              <div class="tu-card p-3 flex items-center justify-between">
                <span class="text-sm font-medium">{{ $t('landing.producto.clase3') }}</span>
                <span class="tu-badge tu-badge-exito">{{ $t('landing.producto.lugares') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Funciones (banda gris) -->
  <section :style="{ background: 'var(--fondo)' }">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 py-20 sm:py-28">
      <h2 class="text-3xl sm:text-4xl font-bold tracking-tight" style="letter-spacing: -0.02em">
        {{ $t('landing.seccionTitulo') }}
      </h2>
      <p class="mt-3 text-lg max-w-2xl" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.seccionSub') }}</p>
      <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="f in funciones" :key="f.clave" class="tu-card p-7">
          <div class="text-3xl" aria-hidden="true">{{ f.icono }}</div>
          <h3 class="mt-4 font-semibold text-xl tracking-tight">{{ $t(`landing.funciones.${f.clave}`) }}</h3>
          <p class="mt-2" :style="{ color: 'var(--texto-suave)' }">{{ $t(`landing.funciones.${f.clave}Desc`) }}</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Para quién (banda blanca) -->
  <section :style="{ background: 'var(--superficie)' }">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 py-20 sm:py-28 text-center">
      <h2 class="text-3xl sm:text-4xl font-bold tracking-tight" style="letter-spacing: -0.02em">
        {{ $t('landing.paraQuien.titulo') }}
      </h2>
      <p class="mt-3 text-lg mx-auto max-w-2xl" :style="{ color: 'var(--texto-suave)' }">{{ $t('landing.paraQuien.subtitulo') }}</p>
      <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <span
          v-for="v in verticales"
          :key="v"
          class="tu-badge text-base px-5 py-2"
          :style="{ background: 'var(--fondo)', color: 'var(--texto)' }"
        >
          {{ v }}
        </span>
      </div>
    </div>
  </section>

  <!-- FAQ (banda gris) -->
  <section :style="{ background: 'var(--fondo)' }">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 py-20 sm:py-28">
      <h2 class="text-3xl sm:text-4xl font-bold tracking-tight" style="letter-spacing: -0.02em">
        {{ $t('landing.faq.titulo') }}
      </h2>
      <div class="mt-8 space-y-3">
        <details v-for="f in faqs" :key="f.q" class="tu-card p-5">
          <summary class="font-semibold cursor-pointer list-none flex items-center justify-between gap-3">
            {{ $t(`landing.faq.${f.q}`) }}
            <span aria-hidden="true" :style="{ color: 'var(--texto-suave)' }">+</span>
          </summary>
          <p class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
            {{ $t(`landing.faq.${f.a}`, { dias: DIAS_PRUEBA }) }}
          </p>
        </details>
      </div>
    </div>
  </section>

  <!-- CTA final (banda blanca) -->
  <section :style="{ background: 'var(--superficie)' }">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 py-20 sm:py-28 text-center">
      <h2 class="font-bold tracking-tight text-4xl sm:text-5xl" style="letter-spacing: -0.025em; line-height: 1.07">
        {{ $t('landing.ctaFinalTitulo') }}
      </h2>
      <p class="mt-4 text-lg" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('landing.ctaFinalSub', { dias: DIAS_PRUEBA }) }}
      </p>
      <RouterLink class="tu-btn tu-btn-primario text-base px-7 py-3 mt-8" :to="{ name: 'registro' }">
        {{ $t('landing.ctaRegistrar') }}
      </RouterLink>
    </div>
  </section>
</template>

<style scoped>
details > summary::-webkit-details-marker {
  display: none;
}
details[open] > summary > span {
  transform: rotate(45deg);
  display: inline-block;
  transition: transform 0.15s ease;
}
</style>
