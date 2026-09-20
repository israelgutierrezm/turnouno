<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import EncabezadoSeccion from '@/components/EncabezadoSeccion.vue'
import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface Cargo {
  id: string
  periodo: string
  modo_cobro: string
  alumnos_activos: number
  monto_minor: number
  moneda: string
  estado: string
  vence_en: string | null
  pagado_en: string | null
}
interface Renta {
  modo_cobro: string
  moneda: string
  precio_por_alumno_minor: number
  cuota_fija_minor: number
  actual: { periodo: string; alumnos_activos: number; cargo_estimado_minor: number }
  cargos: Cargo[]
}

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)

const renta = ref<Renta | null>(null)
const cargando = ref(true)
const error = ref<string | null>(null)

function dinero(minor: number, moneda: string): string {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: moneda }).format(minor / 100)
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: Renta }>(`${base.value}/renta`)
    renta.value = data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 sm:px-6 py-8">
    <EncabezadoSeccion icono="renta" :titulo="$t('renta.titulo')" :subtitulo="$t('renta.subtitulo')" />

    <p v-if="error" class="mt-4 text-sm" style="color: var(--error)">{{ error }}</p>
    <p v-if="cargando" class="mt-6 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>

    <template v-else-if="renta">
      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <!-- Modo de cobro -->
        <div class="tu-card p-5">
          <h2 class="font-bold">{{ $t('renta.modo.titulo') }}</h2>
          <p class="mt-2 text-lg font-extrabold">
            {{ renta.modo_cobro === 'fijo' ? $t('renta.modo.fijo') : $t('renta.modo.activos') }}
          </p>
          <p class="mt-1 text-sm" :style="{ color: 'var(--texto-suave)' }">
            {{ renta.modo_cobro === 'fijo'
              ? $t('renta.modo.cuota', { monto: dinero(renta.cuota_fija_minor, renta.moneda) })
              : $t('renta.modo.porAlumno', { monto: dinero(renta.precio_por_alumno_minor, renta.moneda) }) }}
          </p>
        </div>

        <!-- Periodo en curso -->
        <div class="tu-card p-5">
          <h2 class="font-bold">{{ $t('renta.actual.titulo') }} · {{ renta.actual.periodo }}</h2>
          <div class="mt-2 flex items-end justify-between">
            <div>
              <div class="text-3xl font-extrabold">{{ dinero(renta.actual.cargo_estimado_minor, renta.moneda) }}</div>
              <div class="text-xs mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('renta.actual.estimado') }}</div>
            </div>
            <div class="text-right">
              <div class="text-xl font-bold">{{ renta.actual.alumnos_activos }}</div>
              <div class="text-xs mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('renta.actual.activos') }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Historial de cargos -->
      <h2 class="mt-8 font-bold text-lg">{{ $t('renta.historial') }}</h2>
      <p v-if="renta.cargos.length === 0" class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('renta.sinCargos') }}
      </p>
      <div v-else class="mt-3 tu-card overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left" :style="{ color: 'var(--texto-suave)' }">
              <th class="px-4 py-2 font-medium">{{ $t('renta.colPeriodo') }}</th>
              <th class="px-4 py-2 font-medium text-right hidden sm:table-cell">{{ $t('renta.colAlumnos') }}</th>
              <th class="px-4 py-2 font-medium text-right">{{ $t('renta.colMonto') }}</th>
              <th class="px-4 py-2 font-medium">{{ $t('renta.colEstado') }}</th>
              <th class="px-4 py-2 font-medium hidden sm:table-cell">{{ $t('renta.colVence') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in renta.cargos" :key="c.id" class="border-t" :style="{ borderColor: 'var(--borde)' }">
              <td class="px-4 py-2 font-semibold">{{ c.periodo }}</td>
              <td class="px-4 py-2 text-right hidden sm:table-cell">{{ c.modo_cobro === 'fijo' ? '—' : c.alumnos_activos }}</td>
              <td class="px-4 py-2 text-right font-semibold">{{ dinero(c.monto_minor, c.moneda) }}</td>
              <td class="px-4 py-2">
                <span class="tu-badge" :class="c.estado === 'pagado' ? 'tu-badge-exito' : 'tu-badge-aviso'">
                  {{ $t(`renta.estados.${c.estado}`) }}
                </span>
              </td>
              <td class="px-4 py-2 hidden sm:table-cell" :style="{ color: 'var(--texto-suave)' }">{{ c.vence_en ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="mt-3 text-xs" :style="{ color: 'var(--texto-suave)' }">{{ $t('renta.pagarPronto') }}</p>
    </template>
  </section>
</template>
