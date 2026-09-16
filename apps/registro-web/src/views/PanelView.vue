<script setup lang="ts">
import { isAxiosError } from 'axios'
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface Facturacion {
  plan: string | null
  estado_facturacion: string
  trial_termina_en: string | null
  precio_por_alumno_minor: number
  moneda: string
  uso: {
    periodo: string
    alumnos_activos: number
    regla: string
    cargo_estimado_minor: number
  }
}

const router = useRouter()
const sesion = useSesionTenantStore()

const facturacion = ref<Facturacion | null>(null)
const cargando = ref(true)
const error = ref<string | null>(null)

function dinero(minor: number, moneda: string): string {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: moneda }).format(minor / 100)
}

async function cargar(): Promise<void> {
  if (sesion.slug === null) {
    return
  }
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: Facturacion }>(
      `/api/v1/app/${sesion.slug}/facturacion`,
    )
    facturacion.value = data.data
  } catch (e) {
    // La facturacion es solo para quien tiene facturacion.ver; el resto del staff
    // ve el panel sin ese bloque (no es un error para ellos).
    if (isAxiosError(e) && e.response?.status === 403) {
      facturacion.value = null
    } else {
      error.value = mensajeDeError(e)
    }
  } finally {
    cargando.value = false
  }
}

async function salir(): Promise<void> {
  await sesion.cerrarSesion()
  void router.push({ name: 'inicio' })
}

onMounted(cargar)
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 py-10">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-3xl font-extrabold">
          {{ $t('panel.hola', { nombre: sesion.usuario?.nombre ?? '' }) }}
        </h1>
        <p class="mt-1" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('panel.bienvenida', { estudio: sesion.estudio?.nombre ?? '' }) }}
        </p>
      </div>
      <button class="tu-btn tu-btn-fantasma" @click="salir">{{ $t('panel.salir') }}</button>
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
      <span class="tu-badge">{{ $t('panel.rol') }}: {{ sesion.usuario?.rol }}</span>
      <span class="tu-badge" :class="{ 'tu-badge-exito': sesion.estudio?.estado === 'active' }">
        {{ $t('panel.estado') }}: {{ sesion.estudio?.estado }}
      </span>
    </div>

    <p v-if="cargando" class="mt-8" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>
    <div v-else-if="error" class="mt-8 tu-card p-5" style="color: var(--error)">
      {{ error }}
      <button class="tu-enlace ml-2" @click="cargar">{{ $t('comun.reintentar') }}</button>
    </div>

    <div v-else-if="facturacion" class="mt-6 grid gap-4 sm:grid-cols-2">
      <div class="tu-card p-6">
        <h2 class="font-bold text-lg">{{ $t('panel.facturacion') }}</h2>
        <dl class="mt-3 space-y-2 text-sm">
          <div class="flex justify-between">
            <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('panel.plan') }}</dt>
            <dd class="font-semibold">{{ facturacion.plan ?? '—' }}</dd>
          </div>
          <div class="flex justify-between">
            <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('panel.estado') }}</dt>
            <dd class="font-semibold">{{ facturacion.estado_facturacion }}</dd>
          </div>
          <div v-if="facturacion.trial_termina_en" class="flex justify-between">
            <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('panel.trial') }}</dt>
            <dd class="font-semibold">{{ facturacion.trial_termina_en }}</dd>
          </div>
        </dl>
      </div>

      <div class="tu-card p-6">
        <h2 class="font-bold text-lg">{{ $t('panel.periodo') }} {{ facturacion.uso.periodo }}</h2>
        <div class="mt-3 flex items-end gap-2">
          <span class="text-4xl font-extrabold">{{ facturacion.uso.alumnos_activos }}</span>
          <span class="mb-1 text-sm" :style="{ color: 'var(--texto-suave)' }">{{
            $t('panel.alumnosActivos')
          }}</span>
        </div>
        <p class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('panel.cargoEstimado') }}:
          <span class="font-semibold" :style="{ color: 'var(--texto)' }">{{
            dinero(facturacion.uso.cargo_estimado_minor, facturacion.moneda)
          }}</span>
        </p>
      </div>
    </div>

    <div class="mt-6 tu-card p-6 flex items-center justify-between gap-4 flex-wrap">
      <div>
        <h2 class="font-bold text-lg">{{ $t('panel.proximos') }}</h2>
        <p class="mt-1 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('panel.proximosDesc') }}</p>
      </div>
      <RouterLink class="tu-btn tu-btn-primario" :to="{ name: 'onboarding' }">
        {{ $t('panel.irOnboarding') }}
      </RouterLink>
    </div>
  </section>
</template>
