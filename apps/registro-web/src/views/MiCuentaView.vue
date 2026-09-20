<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface Derecho {
  id: string
  ilimitado: boolean
  saldo: number | null
  disponible: number | null
}
interface Reserva {
  id: string
  estado: string
  oferta: string | null
  inicia_en: string | null
  zona_horaria: string | null
}
interface Clase {
  id: string
  oferta: string | null
  sucursal: string | null
  inicia_en: string
  zona_horaria: string
  capacidad: number | null
  ocupados: number
}
interface Waiver {
  id: string
  titulo: string
  contenido: string
  version: number
}
interface Politica {
  horas_limite: number
  penaliza_tarde: boolean
  penaliza_no_show: boolean
}

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)

const derechos = ref<Derecho[]>([])
const reservas = ref<Reserva[]>([])
const clases = ref<Clase[]>([])
const waivers = ref<Waiver[]>([])
const politica = ref<Politica | null>(null)
const cargando = ref(true)
const error = ref<string | null>(null)
const mensaje = ref<string | null>(null)
const accionando = ref(false)

function horaLocal(iso: string | null, zona: string | null): string {
  if (iso === null) {
    return '—'
  }
  return new Intl.DateTimeFormat('es-MX', {
    timeZone: zona ?? 'America/Mexico_City',
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(iso))
}
function creditos(u: number | null): number {
  return Math.round((u ?? 0) / 1000)
}
function llena(c: Clase): boolean {
  return c.capacidad !== null && c.ocupados >= c.capacidad
}
function lugares(c: Clase): string {
  return c.capacidad !== null ? `${c.ocupados}/${c.capacidad}` : String(c.ocupados)
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const [p, a, w] = await Promise.all([
      api.get<{ data: { derechos: Derecho[]; reservas: Reserva[]; politica_cancelacion: Politica | null } }>(`${base.value}/mi/perfil`),
      api.get<{ data: Clase[] }>(`${base.value}/mi/agenda`),
      api.get<{ data: Waiver[] }>(`${base.value}/mi/waivers`),
    ])
    derechos.value = p.data.data.derechos
    reservas.value = p.data.data.reservas
    politica.value = p.data.data.politica_cancelacion
    clases.value = a.data.data
    waivers.value = w.data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

async function reservar(clase: Clase, esperar: boolean): Promise<void> {
  accionando.value = true
  error.value = null
  mensaje.value = null
  try {
    await api.post(`${base.value}/mi/reservas`, { sesion_id: clase.id, esperar })
    mensaje.value = esperar ? 'espera' : 'ok'
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

async function cancelar(r: Reserva): Promise<void> {
  accionando.value = true
  error.value = null
  try {
    await api.post(`${base.value}/mi/reservas/${r.id}/cancelar`, {})
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

async function aceptar(r: Reserva): Promise<void> {
  accionando.value = true
  error.value = null
  try {
    await api.post(`${base.value}/mi/reservas/${r.id}/aceptar`, {})
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

async function aceptarWaiver(w: Waiver): Promise<void> {
  accionando.value = true
  error.value = null
  try {
    await api.post(`${base.value}/mi/waivers/${w.id}/aceptar`, {})
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

const reservadas = computed(() => new Set(reservas.value.map((r) => r.oferta + '|' + r.inicia_en)))

onMounted(cargar)
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 py-10">
    <h1 class="text-3xl font-extrabold">{{ $t('miCuenta.titulo') }}</h1>
    <p class="mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('miCuenta.subtitulo') }}</p>

    <p v-if="cargando" class="mt-8" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>
    <p v-if="error" class="mt-4 text-sm" style="color: var(--error)">{{ error }}</p>

    <template v-if="!cargando">
      <!-- Consentimientos pendientes -->
      <div v-if="waivers.length > 0" class="mt-6 tu-card p-6" :style="{ borderLeft: '4px solid var(--primario)' }">
        <h2 class="font-bold text-lg">{{ $t('miCuenta.waiversTitulo') }}</h2>
        <ul class="mt-3 space-y-3">
          <li v-for="w in waivers" :key="w.id" class="text-sm">
            <div class="font-semibold">{{ w.titulo }}</div>
            <p class="mt-1 whitespace-pre-line" :style="{ color: 'var(--texto-suave)' }">{{ w.contenido }}</p>
            <button class="tu-btn tu-btn-primario mt-2" :disabled="accionando" @click="aceptarWaiver(w)">
              {{ $t('miCuenta.aceptarWaiver') }}
            </button>
          </li>
        </ul>
      </div>

      <div class="mt-6 grid gap-6 md:grid-cols-2">
        <!-- Creditos -->
        <div class="tu-card p-6">
          <h2 class="font-bold text-lg">{{ $t('miCuenta.creditos') }}</h2>
          <ul v-if="derechos.length > 0" class="mt-3 space-y-2 text-sm">
            <li v-for="d in derechos" :key="d.id" class="flex items-center justify-between">
              <span v-if="d.ilimitado" class="tu-badge tu-badge-exito">{{ $t('miCuenta.ilimitado') }}</span>
              <template v-else>
                <span :style="{ color: 'var(--texto-suave)' }">{{ $t('miCuenta.disponible') }}</span>
                <span class="font-bold text-lg">{{ creditos(d.disponible) }}</span>
              </template>
            </li>
          </ul>
          <p v-else class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('miCuenta.sinCreditos') }}</p>
        </div>

        <!-- Mis reservas -->
        <div class="tu-card p-6">
          <h2 class="font-bold text-lg">{{ $t('miCuenta.misReservas') }}</h2>
          <ul v-if="reservas.length > 0" class="mt-3 space-y-2 text-sm">
            <li v-for="r in reservas" :key="r.id" class="flex items-center justify-between gap-2">
              <span class="min-w-0">
                <span class="font-medium">{{ r.oferta ?? '—' }}</span>
                <span :style="{ color: 'var(--texto-suave)' }"> · {{ horaLocal(r.inicia_en, r.zona_horaria) }}</span>
                <span
                  class="tu-badge ml-1"
                  :class="{ 'tu-badge-exito': r.estado === 'confirmada', 'tu-badge-aviso': r.estado === 'ofrecida' || r.estado === 'en_espera' }"
                >{{ $t(`miCuenta.${r.estado}`) }}</span>
              </span>
              <span class="flex items-center gap-2 shrink-0">
                <button
                  v-if="r.estado === 'ofrecida'"
                  class="tu-btn tu-btn-primario"
                  :disabled="accionando"
                  @click="aceptar(r)"
                >
                  {{ $t('miCuenta.aceptarPlaza') }}
                </button>
                <button class="tu-enlace" style="color: var(--error)" :disabled="accionando" @click="cancelar(r)">
                  {{ $t('miCuenta.cancelar') }}
                </button>
              </span>
            </li>
          </ul>
          <p v-else class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('miCuenta.sinReservas') }}</p>
          <!-- Reglas de cancelacion -->
          <p v-if="politica" class="mt-3 text-xs" :style="{ color: 'var(--texto-suave)' }">
            {{ $t('miCuenta.politica', { horas: politica.horas_limite }) }}
          </p>
        </div>
      </div>

      <!-- Proximas clases -->
      <div class="mt-6 tu-card p-6">
        <h2 class="font-bold text-lg">{{ $t('miCuenta.agenda') }}</h2>
        <p v-if="mensaje === 'ok'" class="mt-2 text-sm" :style="{ color: 'var(--exito)' }">{{ $t('miCuenta.reservado') }}</p>
        <p v-else-if="mensaje === 'espera'" class="mt-2 text-sm" :style="{ color: 'var(--exito)' }">{{ $t('miCuenta.enListaEspera') }}</p>
        <ul v-if="clases.length > 0" class="mt-3 space-y-2">
          <li v-for="c in clases" :key="c.id" class="flex items-center justify-between gap-2 text-sm">
            <span class="min-w-0">
              <span class="font-medium">{{ c.oferta ?? '—' }}</span>
              <span :style="{ color: 'var(--texto-suave)' }"> · {{ horaLocal(c.inicia_en, c.zona_horaria) }}</span>
              <span v-if="c.sucursal" :style="{ color: 'var(--texto-suave)' }"> · {{ c.sucursal }}</span>
              <span class="tu-badge ml-1" :class="llena(c) ? 'tu-badge-aviso' : 'tu-badge-exito'">{{ lugares(c) }}</span>
            </span>
            <span class="flex items-center gap-2 shrink-0">
              <template v-if="!reservadas.has(c.oferta + '|' + c.inicia_en)">
                <button
                  v-if="!llena(c)"
                  class="tu-btn tu-btn-primario"
                  :disabled="accionando"
                  @click="reservar(c, false)"
                >
                  {{ $t('miCuenta.reservar') }}
                </button>
                <button
                  v-else
                  class="tu-btn tu-btn-fantasma"
                  :disabled="accionando"
                  @click="reservar(c, true)"
                >
                  {{ $t('miCuenta.listaEspera') }}
                </button>
              </template>
              <span v-else class="tu-badge tu-badge-exito">✓</span>
            </span>
          </li>
        </ul>
        <p v-else class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('miCuenta.sinClases') }}</p>
      </div>
    </template>
  </section>
</template>
