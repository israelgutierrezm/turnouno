<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import EncabezadoSeccion from '@/components/EncabezadoSeccion.vue'
import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

const { t } = useI18n()

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
interface RentabilidadOferta {
  id: string | null
  oferta: string
  precio_clase_minor: number | null
  sesiones: number
  asistentes: number
  ingreso_minor: number
  costo_minor: number
  margen_minor: number
  sin_costo_unitario: number
}
interface Rentabilidad {
  moneda: string
  totales: { asistentes: number; ingreso_minor: number; costo_minor: number; margen_minor: number; sin_costo_unitario: number }
  ofertas: RentabilidadOferta[]
}
interface DemandaCelda {
  dia: number
  hora: number
  sesiones: number
  capacidad: number
  confirmadas: number
  espera: number
  ocupacion_pct: number | null
}
interface DemandaActividad {
  id: string | null
  actividad: string
  sesiones: number
  capacidad: number
  confirmadas: number
  espera: number
  ocupacion_pct: number | null
}
interface Demanda {
  totales: { sesiones: number; capacidad: number; confirmadas: number; espera: number; ocupacion_pct: number | null }
  matriz: DemandaCelda[]
  actividades: DemandaActividad[]
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
const rentabilidad = ref<Rentabilidad | null>(null)
const demanda = ref<Demanda | null>(null)
const cargando = ref(true)
const error = ref<string | null>(null)

// Etiquetas de días (lun..dom) desde i18n; el índice 0 corresponde a `dia = 1`.
const diasSemana = computed(() => t('reportes.demanda.dias').split(','))
// Horas presentes en la matriz (unión, ordenadas) → filas del heatmap.
const horasDemanda = computed(() => {
  const set = new Set<number>()
  demanda.value?.matriz.forEach((c) => set.add(c.hora))
  return [...set].sort((a, b) => a - b)
})
function celdaDemanda(dia: number, hora: number): DemandaCelda | undefined {
  return demanda.value?.matriz.find((c) => c.dia === dia && c.hora === hora)
}
// Fondo del heatmap: más ocupación = acento más intenso (12%..92%).
function colorOcupacion(pct: number | null): string {
  if (pct === null) {
    return 'transparent'
  }
  const mezcla = Math.round(12 + Math.min(100, pct) * 0.8)
  return `color-mix(in srgb, var(--primario) ${mezcla}%, transparent)`
}

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

async function cargarRentabilidad(): Promise<void> {
  error.value = null
  try {
    const { data } = await api.get<{ data: Rentabilidad }>(`${base.value}/reportes/rentabilidad`, {
      params: { desde: desde.value, hasta: hasta.value },
    })
    rentabilidad.value = data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  }
}

async function cargarDemanda(): Promise<void> {
  error.value = null
  try {
    const { data } = await api.get<{ data: Demanda }>(`${base.value}/reportes/demanda`, {
      params: { desde: desde.value, hasta: hasta.value },
    })
    demanda.value = data.data
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
      cargarRentabilidad(),
      cargarDemanda(),
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

watch([desde, hasta], () => {
  void cargarNegocio()
  void cargarRentabilidad()
  void cargarDemanda()
})

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

      <!-- Rentabilidad por clase (R30) -->
      <h2 class="mt-8 font-bold text-lg">{{ $t('reportes.rentabilidad.titulo') }}</h2>
      <p v-if="!rentabilidad || rentabilidad.ofertas.length === 0" class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('reportes.rentabilidad.vacio') }}
      </p>
      <template v-else>
        <div class="mt-3 tu-card overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left" :style="{ color: 'var(--texto-suave)' }">
                <th class="px-4 py-2 font-medium">{{ $t('reportes.rentabilidad.colClase') }}</th>
                <th class="px-4 py-2 font-medium text-right hidden sm:table-cell">{{ $t('reportes.rentabilidad.colSesiones') }}</th>
                <th class="px-4 py-2 font-medium text-right">{{ $t('reportes.rentabilidad.colAsistentes') }}</th>
                <th class="px-4 py-2 font-medium text-right">{{ $t('reportes.rentabilidad.colIngreso') }}</th>
                <th class="px-4 py-2 font-medium text-right">{{ $t('reportes.rentabilidad.colCosto') }}</th>
                <th class="px-4 py-2 font-medium text-right">{{ $t('reportes.rentabilidad.colMargen') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in rentabilidad.ofertas" :key="o.id ?? o.oferta" class="border-t" :style="{ borderColor: 'var(--borde)' }">
                <td class="px-4 py-2 font-semibold">{{ o.oferta }}</td>
                <td class="px-4 py-2 text-right hidden sm:table-cell">{{ o.sesiones }}</td>
                <td class="px-4 py-2 text-right">{{ o.asistentes }}</td>
                <td class="px-4 py-2 text-right">{{ dinero(o.ingreso_minor, rentabilidad.moneda) }}</td>
                <td class="px-4 py-2 text-right">{{ dinero(o.costo_minor, rentabilidad.moneda) }}</td>
                <td class="px-4 py-2 text-right font-semibold" :style="{ color: o.margen_minor >= 0 ? 'var(--exito)' : 'var(--error)' }">
                  {{ dinero(o.margen_minor, rentabilidad.moneda) }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t font-bold" :style="{ borderColor: 'var(--borde)' }">
                <td class="px-4 py-2">{{ $t('reportes.rentabilidad.total') }}</td>
                <td class="px-4 py-2 hidden sm:table-cell"></td>
                <td class="px-4 py-2 text-right">{{ rentabilidad.totales.asistentes }}</td>
                <td class="px-4 py-2 text-right">{{ dinero(rentabilidad.totales.ingreso_minor, rentabilidad.moneda) }}</td>
                <td class="px-4 py-2 text-right">{{ dinero(rentabilidad.totales.costo_minor, rentabilidad.moneda) }}</td>
                <td class="px-4 py-2 text-right" :style="{ color: rentabilidad.totales.margen_minor >= 0 ? 'var(--exito)' : 'var(--error)' }">
                  {{ dinero(rentabilidad.totales.margen_minor, rentabilidad.moneda) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
        <p v-if="rentabilidad.totales.sin_costo_unitario > 0" class="mt-3 text-xs" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('reportes.rentabilidad.sinCosto', { n: rentabilidad.totales.sin_costo_unitario }) }}
        </p>
      </template>

      <!-- Demanda por horario (R31) -->
      <h2 class="mt-8 font-bold text-lg">{{ $t('reportes.demanda.titulo') }}</h2>
      <p class="mt-1 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('reportes.demanda.subtitulo') }}</p>
      <p v-if="!demanda || demanda.matriz.length === 0" class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('reportes.demanda.vacio') }}
      </p>
      <template v-else>
        <!-- Heatmap día × hora -->
        <div class="mt-3 tu-card overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-center" :style="{ color: 'var(--texto-suave)' }">
                <th class="px-3 py-2 font-medium text-left">{{ $t('reportes.demanda.hora') }}</th>
                <th v-for="(d, i) in diasSemana" :key="i" class="px-2 py-2 font-medium">{{ d }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="h in horasDemanda" :key="h" class="border-t" :style="{ borderColor: 'var(--borde)' }">
                <td class="px-3 py-1.5 font-semibold whitespace-nowrap">{{ String(h).padStart(2, '0') }}:00</td>
                <td v-for="dia in [1, 2, 3, 4, 5, 6, 7]" :key="dia" class="px-1 py-1 text-center">
                  <div
                    v-if="celdaDemanda(dia, h)"
                    class="relative rounded-lg py-1.5 text-xs font-semibold"
                    :style="{ background: colorOcupacion(celdaDemanda(dia, h)!.ocupacion_pct) }"
                    :title="`${celdaDemanda(dia, h)!.confirmadas}/${celdaDemanda(dia, h)!.capacidad}`"
                  >
                    {{ pct(celdaDemanda(dia, h)!.ocupacion_pct) }}
                    <span
                      v-if="celdaDemanda(dia, h)!.espera > 0"
                      class="absolute top-0.5 right-0.5 h-1.5 w-1.5 rounded-full"
                      :style="{ background: 'var(--aviso)' }"
                      :title="$t('reportes.demanda.colEspera')"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="mt-2 text-xs" :style="{ color: 'var(--texto-suave)' }">{{ $t('reportes.demanda.leyenda') }}</p>

        <!-- Por actividad -->
        <h3 class="mt-6 font-semibold">{{ $t('reportes.demanda.porActividad') }}</h3>
        <div class="mt-3 tu-card overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left" :style="{ color: 'var(--texto-suave)' }">
                <th class="px-4 py-2 font-medium">{{ $t('reportes.demanda.colActividad') }}</th>
                <th class="px-4 py-2 font-medium text-right hidden sm:table-cell">{{ $t('reportes.demanda.colSesiones') }}</th>
                <th class="px-4 py-2 font-medium text-right">{{ $t('reportes.demanda.colConfirmadas') }}</th>
                <th class="px-4 py-2 font-medium text-right">{{ $t('reportes.demanda.colEspera') }}</th>
                <th class="px-4 py-2 font-medium text-right">{{ $t('reportes.demanda.colOcupacion') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in demanda.actividades" :key="a.id ?? a.actividad" class="border-t" :style="{ borderColor: 'var(--borde)' }">
                <td class="px-4 py-2 font-semibold">{{ a.actividad }}</td>
                <td class="px-4 py-2 text-right hidden sm:table-cell">{{ a.sesiones }}</td>
                <td class="px-4 py-2 text-right">{{ a.confirmadas }}</td>
                <td class="px-4 py-2 text-right font-semibold" :style="{ color: a.espera > 0 ? 'var(--aviso)' : 'inherit' }">{{ a.espera }}</td>
                <td class="px-4 py-2 text-right font-semibold">{{ pct(a.ocupacion_pct) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t font-bold" :style="{ borderColor: 'var(--borde)' }">
                <td class="px-4 py-2">{{ $t('reportes.demanda.total') }}</td>
                <td class="px-4 py-2 text-right hidden sm:table-cell">{{ demanda.totales.sesiones }}</td>
                <td class="px-4 py-2 text-right">{{ demanda.totales.confirmadas }}</td>
                <td class="px-4 py-2 text-right">{{ demanda.totales.espera }}</td>
                <td class="px-4 py-2 text-right">{{ pct(demanda.totales.ocupacion_pct) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </template>
    </template>
  </section>
</template>
