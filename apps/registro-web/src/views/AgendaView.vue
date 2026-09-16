<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface Oferta {
  id: string
  nombre: string
}
interface Sucursal {
  id: string
  nombre: string
  zona_horaria: string
}
interface Sesion {
  id: string
  oferta: string | null
  instructor: string | null
  inicia_en: string
  termina_en: string
  zona_horaria: string
  capacidad: number | null
  estado: string
}
interface Miembro {
  id: string
  nombre: string
  apellidos: string | null
}
interface Reserva {
  id: string
  estado: string
  persona: string | null
  unidades: number
  asistencia: string | null
}

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)
const puedeGestionar = computed(() => sesion.puede('agenda.gestionar'))
const puedeReservar = computed(() => sesion.puede('reservas.gestionar'))
const puedeMarcar = computed(() => sesion.puede('asistencia.marcar'))

const ofertas = ref<Oferta[]>([])
const sucursales = ref<Sucursal[]>([])
const sesiones = ref<Sesion[]>([])
const miembros = ref<Miembro[]>([])
const instructores = ref<{ id: string; nombre: string }[]>([])
const cargando = ref(true)
const error = ref<string | null>(null)

const form = ref({ ofertaId: '', sucursalId: '', instructorId: '', fecha: '', duracion: '60', capacidad: '' })
const creando = ref(false)

const expandidaId = ref<string | null>(null)
const roster = ref<Reserva[]>([])
const cargandoRoster = ref(false)
const reservarModel = ref({ miembroId: '', esperar: false })
const accionando = ref(false)

function horaLocal(iso: string, zona: string): string {
  return new Intl.DateTimeFormat('es-MX', {
    timeZone: zona,
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(iso))
}
function nombreMiembro(m: Miembro): string {
  return `${m.nombre} ${m.apellidos ?? ''}`.trim()
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const [o, s, se, m] = await Promise.all([
      api.get<{ data: Oferta[] }>(`${base.value}/ofertas`),
      api.get<{ data: Sucursal[] }>(`${base.value}/sucursales`),
      api.get<{ data: Sesion[] }>(`${base.value}/sesiones`),
      api.get<{ data: Miembro[] }>(`${base.value}/miembros`, { params: { tipo: 'miembro' } }),
    ])
    ofertas.value = o.data.data
    sucursales.value = s.data.data
    sesiones.value = se.data.data
    miembros.value = m.data.data
    // Solo el staff que gestiona la agenda puede listar/asignar instructores.
    if (puedeGestionar.value) {
      const i = await api.get<{ data: { id: string; nombre: string }[] }>(`${base.value}/instructores`)
      instructores.value = i.data.data
    }
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

async function crearSesion(): Promise<void> {
  creando.value = true
  error.value = null
  try {
    await api.post(`${base.value}/sesiones`, {
      oferta_id: form.value.ofertaId,
      sucursal_id: form.value.sucursalId,
      instructor_id: form.value.instructorId !== '' ? form.value.instructorId : null,
      inicia_en_local: form.value.fecha.replace('T', ' ') + ':00',
      duracion_minutos: Number(form.value.duracion),
      capacidad: form.value.capacidad !== '' ? Number(form.value.capacidad) : null,
    })
    form.value.fecha = ''
    form.value.capacidad = ''
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    creando.value = false
  }
}

async function cargarRoster(id: string): Promise<void> {
  cargandoRoster.value = true
  try {
    const { data } = await api.get<{ data: Reserva[] }>(`${base.value}/sesiones/${id}/reservas`)
    roster.value = data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargandoRoster.value = false
  }
}

async function alternarRoster(s: Sesion): Promise<void> {
  if (expandidaId.value === s.id) {
    expandidaId.value = null
    return
  }
  expandidaId.value = s.id
  roster.value = []
  reservarModel.value = { miembroId: '', esperar: false }
  await cargarRoster(s.id)
}

async function reservar(id: string): Promise<void> {
  accionando.value = true
  error.value = null
  try {
    await api.post(`${base.value}/sesiones/${id}/reservas`, {
      persona_id: reservarModel.value.miembroId,
      esperar: reservarModel.value.esperar,
    })
    reservarModel.value.miembroId = ''
    await cargarRoster(id)
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

async function marcar(reservaId: string, estado: 'presente' | 'ausente', sesionId: string): Promise<void> {
  accionando.value = true
  error.value = null
  try {
    await api.post(`${base.value}/reservas/${reservaId}/asistencia`, { estado })
    await cargarRoster(sesionId)
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

async function cancelarReserva(reservaId: string, sesionId: string): Promise<void> {
  accionando.value = true
  error.value = null
  try {
    await api.post(`${base.value}/reservas/${reservaId}/cancelar`, {})
    await cargarRoster(sesionId)
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

async function cancelarSesion(id: string): Promise<void> {
  accionando.value = true
  error.value = null
  try {
    await api.post(`${base.value}/sesiones/${id}/cancelar`, {})
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 py-10">
    <h1 class="text-3xl font-extrabold">{{ $t('agenda.titulo') }}</h1>
    <p class="mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('agenda.subtitulo') }}</p>

    <p v-if="cargando" class="mt-8" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>
    <p v-if="error" class="mt-4 text-sm" style="color: var(--error)">{{ error }}</p>

    <template v-if="!cargando">
      <!-- Nueva clase -->
      <div v-if="puedeGestionar" class="mt-6 tu-card p-6">
        <h2 class="font-bold text-lg">{{ $t('agenda.nueva.titulo') }}</h2>
        <p v-if="ofertas.length === 0" class="mt-2 text-sm" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('agenda.nueva.sinOfertas') }}
        </p>
        <p v-else-if="sucursales.length === 0" class="mt-2 text-sm" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('agenda.nueva.sinSucursales') }}
        </p>
        <form v-else class="mt-3 grid sm:grid-cols-2 gap-3" @submit.prevent="crearSesion">
          <div>
            <label class="tu-label" for="ao">{{ $t('agenda.nueva.oferta') }}</label>
            <select id="ao" v-model="form.ofertaId" class="tu-input" required>
              <option value="" disabled>{{ $t('agenda.reservar.elegir') }}</option>
              <option v-for="o in ofertas" :key="o.id" :value="o.id">{{ o.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="tu-label" for="as">{{ $t('agenda.nueva.sucursal') }}</label>
            <select id="as" v-model="form.sucursalId" class="tu-input" required>
              <option value="" disabled>{{ $t('agenda.reservar.elegir') }}</option>
              <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="tu-label" for="af">{{ $t('agenda.nueva.fecha') }}</label>
            <input id="af" v-model="form.fecha" class="tu-input" type="datetime-local" required />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="tu-label" for="ad">{{ $t('agenda.nueva.duracion') }}</label>
              <input id="ad" v-model="form.duracion" class="tu-input" type="number" min="1" />
            </div>
            <div>
              <label class="tu-label" for="ac">{{ $t('agenda.nueva.capacidad') }}</label>
              <input id="ac" v-model="form.capacidad" class="tu-input" type="number" min="1" />
            </div>
          </div>
          <div v-if="instructores.length > 0">
            <label class="tu-label" for="ai">{{ $t('agenda.nueva.instructor') }}</label>
            <select id="ai" v-model="form.instructorId" class="tu-input">
              <option value="">{{ $t('agenda.nueva.sinInstructor') }}</option>
              <option v-for="i in instructores" :key="i.id" :value="i.id">{{ i.nombre }}</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <button
              class="tu-btn tu-btn-primario"
              type="submit"
              :disabled="creando || form.ofertaId === '' || form.sucursalId === '' || form.fecha === ''"
            >
              {{ creando ? $t('agenda.nueva.creando') : $t('agenda.nueva.crear') }}
            </button>
          </div>
        </form>
      </div>

      <!-- Sesiones -->
      <p
        v-if="sesiones.length === 0"
        class="mt-8 text-center text-sm"
        :style="{ color: 'var(--texto-suave)' }"
      >
        {{ $t('agenda.vacio') }}
      </p>

      <ul v-else class="mt-6 space-y-3">
        <li v-for="s in sesiones" :key="s.id" class="tu-card p-4">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
              <p class="font-semibold">{{ s.oferta ?? '—' }}</p>
              <p class="text-sm" :style="{ color: 'var(--texto-suave)' }">
                {{ horaLocal(s.inicia_en, s.zona_horaria) }}
                <span v-if="s.instructor"> · {{ s.instructor }}</span>
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span class="tu-badge">{{
                s.capacidad !== null ? $t('agenda.sesion.cupo', { n: s.capacidad }) : $t('agenda.sesion.sinCupo')
              }}</span>
              <span
                class="tu-badge"
                :class="s.estado === 'programada' ? 'tu-badge-exito' : 'tu-badge-aviso'"
                >{{ s.estado === 'programada' ? $t('agenda.sesion.programada') : $t('agenda.sesion.cancelada') }}</span
              >
            </div>
          </div>

          <div v-if="s.estado === 'programada'" class="mt-3 flex items-center gap-3">
            <button class="tu-enlace text-sm" @click="alternarRoster(s)">
              {{ expandidaId === s.id ? $t('agenda.sesion.ocultar') : $t('agenda.sesion.reservas') }}
            </button>
            <button
              v-if="puedeGestionar"
              class="tu-enlace text-sm"
              style="color: var(--error)"
              :disabled="accionando"
              @click="cancelarSesion(s.id)"
            >
              {{ $t('agenda.sesion.cancelar') }}
            </button>
          </div>

          <!-- Roster -->
          <div
            v-if="expandidaId === s.id"
            class="mt-4 border-t pt-4"
            :style="{ borderColor: 'var(--borde)' }"
          >
            <form
              v-if="puedeReservar && miembros.length > 0"
              class="flex flex-wrap items-end gap-2"
              @submit.prevent="reservar(s.id)"
            >
              <div class="flex-1 min-w-[160px]">
                <label class="tu-label" :for="'rm' + s.id">{{ $t('agenda.reservar.miembro') }}</label>
                <select :id="'rm' + s.id" v-model="reservarModel.miembroId" class="tu-input" required>
                  <option value="" disabled>{{ $t('agenda.reservar.elegir') }}</option>
                  <option v-for="m in miembros" :key="m.id" :value="m.id">{{ nombreMiembro(m) }}</option>
                </select>
              </div>
              <label class="flex items-center gap-1.5 text-sm pb-2.5">
                <input v-model="reservarModel.esperar" type="checkbox" />
                {{ $t('agenda.reservar.esperar') }}
              </label>
              <button
                class="tu-btn tu-btn-primario"
                type="submit"
                :disabled="accionando || reservarModel.miembroId === ''"
              >
                {{ $t('agenda.reservar.reservar') }}
              </button>
            </form>
            <p v-else-if="puedeReservar" class="text-sm" :style="{ color: 'var(--texto-suave)' }">
              {{ $t('agenda.reservar.sinMiembros') }}
            </p>

            <p v-if="cargandoRoster" class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
              {{ $t('comun.cargando') }}
            </p>
            <p
              v-else-if="roster.length === 0"
              class="mt-3 text-sm"
              :style="{ color: 'var(--texto-suave)' }"
            >
              {{ $t('agenda.roster.vacio') }}
            </p>
            <ul v-else class="mt-3 space-y-2">
              <li
                v-for="r in roster"
                :key="r.id"
                class="flex items-center justify-between gap-2 text-sm"
              >
                <span class="flex items-center gap-2 min-w-0">
                  <span class="truncate">{{ r.persona ?? '—' }}</span>
                  <span
                    class="tu-badge"
                    :class="{ 'tu-badge-exito': r.estado === 'confirmada', 'tu-badge-aviso': r.estado === 'en_espera' }"
                    >{{ $t(`agenda.roster.${r.estado}`) }}</span
                  >
                  <span v-if="r.asistencia" class="tu-badge">{{ $t(`agenda.roster.${r.asistencia}`) }}</span>
                </span>
                <span v-if="r.estado === 'confirmada'" class="flex items-center gap-2 shrink-0">
                  <button
                    v-if="puedeMarcar"
                    class="tu-enlace"
                    :disabled="accionando"
                    @click="marcar(r.id, 'presente', s.id)"
                  >
                    {{ $t('agenda.roster.marcarPresente') }}
                  </button>
                  <button
                    v-if="puedeMarcar"
                    class="tu-enlace"
                    :disabled="accionando"
                    @click="marcar(r.id, 'ausente', s.id)"
                  >
                    {{ $t('agenda.roster.marcarAusente') }}
                  </button>
                  <button
                    v-if="puedeReservar"
                    class="tu-enlace"
                    style="color: var(--error)"
                    :disabled="accionando"
                    @click="cancelarReserva(r.id, s.id)"
                  >
                    {{ $t('agenda.roster.cancelarReserva') }}
                  </button>
                </span>
              </li>
            </ul>
          </div>
        </li>
      </ul>
    </template>
  </section>
</template>
