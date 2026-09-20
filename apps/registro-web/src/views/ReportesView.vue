<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

import EncabezadoSeccion from '@/components/EncabezadoSeccion.vue'
import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface Negocio {
  moneda: string
  ingresos_minor: number
  ordenes_pagadas: number
  clases: number
  ocupacion_pct: number | null
  no_show_pct: number | null
  alumnos_activos: number
  arpu_minor: number | null
}
interface SucursalReporte {
  id: string
  nombre: string
  region: string | null
  moneda: string | null
  miembros_activos: number
  sesiones_proximas: number
}

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)

function iso(d: Date): string {
  const p = (n: number): string => (n < 10 ? `0${n}` : `${n}`)
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}
function inicioMes(): string {
  const d = new Date()
  return iso(new Date(d.getFullYear(), d.getMonth(), 1))
}

const desde = ref(inicioMes())
const hasta = ref(iso(new Date()))
const negocio = ref<Negocio | null>(null)
const sucursales = ref<SucursalReporte[]>([])
const cargando = ref(true)
const error = ref<string | null>(null)

function dinero(minor: number | null, moneda: string): string {
  if (minor === null) {
    return '—'
  }
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: moneda }).format(minor / 100)
}
function pct(v: number | null): string {
  return v !== null ? `${v}%` : '—'
}

const tarjetas = computed(() => {
  const n = negocio.value
  if (n === null) {
    return []
  }
  return [
    { clave: 'ingresos', valor: dinero(n.ingresos_minor, n.moneda) },
    { clave: 'ocupacion', valor: pct(n.ocupacion_pct) },
    { clave: 'noShow', valor: pct(n.no_show_pct) },
    { clave: 'alumnos', valor: String(n.alumnos_activos) },
    { clave: 'arpu', valor: dinero(n.arpu_minor, n.moneda) },
    { clave: 'clases', valor: String(n.clases) },
  ]
})

async function cargarNegocio(): Promise<void> {
  error.value = null
  try {
    const { data } = await api.get<{ data: Negocio }>(`${base.value}/reportes/negocio`, {
      params: { desde: desde.value, hasta: hasta.value },
    })
    negocio.value = data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  }
}

async function cargar(): Promise<void> {
  cargando.value = true
  try {
    const [, s] = await Promise.all([
      cargarNegocio(),
      api.get<{ data: SucursalReporte[] }>(`${base.value}/reportes/sucursales`),
    ])
    sucursales.value = s.data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

function esteMes(): void {
  desde.value = inicioMes()
  hasta.value = iso(new Date())
}

watch([desde, hasta], cargarNegocio)

onMounted(cargar)
</script>

<template>
  <section class="mx-auto max-w-5xl px-4 sm:px-6 py-8">
    <EncabezadoSeccion icono="reportes" :titulo="$t('reportes.titulo')" :subtitulo="$t('reportes.subtitulo')" />

    <!-- Periodo -->
    <div class="mt-6 flex flex-wrap items-end gap-3">
      <div>
        <label class="tu-label" for="rd">{{ $t('reportes.desde') }}</label>
        <input id="rd" v-model="desde" type="date" class="tu-input w-auto" />
      </div>
      <div>
        <label class="tu-label" for="rh">{{ $t('reportes.hasta') }}</label>
        <input id="rh" v-model="hasta" type="date" class="tu-input w-auto" />
      </div>
      <button class="tu-btn tu-btn-fantasma" type="button" @click="esteMes">
        {{ $t('reportes.esteMes') }}
      </button>
    </div>

    <p v-if="error" class="mt-4 text-sm" style="color: var(--error)">{{ error }}</p>
    <p v-if="cargando" class="mt-6 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>

    <template v-else>
      <!-- Métricas del negocio -->
      <div class="mt-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div v-for="t in tarjetas" :key="t.clave" class="tu-card p-4 text-center">
          <div class="text-xl font-extrabold">{{ t.valor }}</div>
          <div class="text-xs mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t(`reportes.metricas.${t.clave}`) }}</div>
        </div>
      </div>

      <!-- Por sucursal -->
      <h2 class="mt-8 font-bold text-lg">{{ $t('reportes.porSucursal') }}</h2>
      <p v-if="sucursales.length === 0" class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('reportes.sinSucursales') }}
      </p>
      <div v-else class="mt-3 tu-card overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left" :style="{ color: 'var(--texto-suave)' }">
              <th class="px-4 py-2 font-medium">{{ $t('reportes.colSucursal') }}</th>
              <th class="px-4 py-2 font-medium hidden sm:table-cell">{{ $t('reportes.colRegion') }}</th>
              <th class="px-4 py-2 font-medium hidden sm:table-cell">{{ $t('reportes.colMoneda') }}</th>
              <th class="px-4 py-2 font-medium text-right">{{ $t('reportes.colMiembros') }}</th>
              <th class="px-4 py-2 font-medium text-right">{{ $t('reportes.colClases') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in sucursales" :key="s.id" class="border-t" :style="{ borderColor: 'var(--borde)' }">
              <td class="px-4 py-2 font-semibold">{{ s.nombre }}</td>
              <td class="px-4 py-2 hidden sm:table-cell" :style="{ color: 'var(--texto-suave)' }">{{ s.region ?? '—' }}</td>
              <td class="px-4 py-2 hidden sm:table-cell">{{ s.moneda ?? '—' }}</td>
              <td class="px-4 py-2 text-right">{{ s.miembros_activos }}</td>
              <td class="px-4 py-2 text-right">{{ s.sesiones_proximas }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>
