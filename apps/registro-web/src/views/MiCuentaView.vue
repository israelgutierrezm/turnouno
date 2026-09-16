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
  inicia_en: string
  zona_horaria: string
  capacidad: number | null
}

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)

const derechos = ref<Derecho[]>([])
const reservas = ref<Reserva[]>([])
const clases = ref<Clase[]>([])
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

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const [p, a] = await Promise.all([
      api.get<{ data: { derechos: Derecho[]; reservas: Reserva[] } }>(`${base.value}/mi/perfil`),
      api.get<{ data: Clase[] }>(`${base.value}/mi/agenda`),
    ])
    derechos.value = p.data.data.derechos
    reservas.value = p.data.data.reservas
    clases.value = a.data.data
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
    mensaje.value = 'ok'
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

const reservadas = computed(() => new Set(reservas.value.map((r) => r.oferta + '|' + r.inicia_en)))

onMounted(cargar)
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 py-10">
    <h1 class="text-3xl font-extrabold">{{ $t('miCuenta.titulo') }}</h1>
    <p class="mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('miCuenta.subtitulo') }}</p>

    <p v-if="cargando" class="mt-8" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>
    <p v-if="error" class="mt-4 text-sm" style="color: var(--error)">{{ error }}</p>

    <div v-if="!cargando" class="mt-6 grid gap-6 md:grid-cols-2">
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
              <span class="tu-badge ml-1" :class="{ 'tu-badge-exito': r.estado === 'confirmada' }">{{
                $t(`miCuenta.${r.estado}`)
              }}</span>
            </span>
            <button class="tu-enlace shrink-0" style="color: var(--error)" :disabled="accionando" @click="cancelar(r)">
              {{ $t('miCuenta.cancelar') }}
            </button>
          </li>
        </ul>
        <p v-else class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('miCuenta.sinReservas') }}</p>
      </div>
    </div>

    <!-- Proximas clases -->
    <div v-if="!cargando" class="mt-6 tu-card p-6">
      <h2 class="font-bold text-lg">{{ $t('miCuenta.agenda') }}</h2>
      <p v-if="mensaje" class="mt-2 text-sm" :style="{ color: 'var(--exito)' }">{{ $t('miCuenta.reservado') }}</p>
      <ul v-if="clases.length > 0" class="mt-3 space-y-2">
        <li v-for="c in clases" :key="c.id" class="flex items-center justify-between gap-2 text-sm">
          <span>
            <span class="font-medium">{{ c.oferta ?? '—' }}</span>
            <span :style="{ color: 'var(--texto-suave)' }"> · {{ horaLocal(c.inicia_en, c.zona_horaria) }}</span>
          </span>
          <span class="flex items-center gap-2 shrink-0">
            <button
              v-if="!reservadas.has(c.oferta + '|' + c.inicia_en)"
              class="tu-btn tu-btn-primario"
              :disabled="accionando"
              @click="reservar(c, false)"
            >
              {{ $t('miCuenta.reservar') }}
            </button>
            <span v-else class="tu-badge tu-badge-exito">✓</span>
          </span>
        </li>
      </ul>
      <p v-else class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('miCuenta.sinClases') }}</p>
    </div>
  </section>
</template>
