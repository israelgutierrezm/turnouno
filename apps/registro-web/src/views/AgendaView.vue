<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

import EncabezadoSeccion from '@/components/EncabezadoSeccion.vue'
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
  instructor_id: string | null
  inicia_en: string
  termina_en: string
  zona_horaria: string
  capacidad: number | null
  ocupados: number
  estado: string
}
interface Miembro {
  id: string
  nombre: string
  nombre_completo: string
}
interface Reserva {
  id: string
  estado: string
  persona: string | null
  unidades: number
  asistencia: string | null
}
interface Checkin {
  id: string
  proveedor: string
  usuario: string | null
  estado: string
  registrado_en: string
}

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)
const puedeGestionar = computed(() => sesion.puede('agenda.gestionar'))
const puedeReservar = computed(() => sesion.puede('reservas.gestionar'))
const puedeMarcar = computed(() => sesion.puede('asistencia.marcar'))
const puedeCheckin = computed(() => sesion.puede('checkins.registrar'))

const ofertas = ref<Oferta[]>([])
const sucursales = ref<Sucursal[]>([])
const sesiones = ref<Sesion[]>([])
const miembros = ref<Miembro[]>([])
const instructores = ref<{ id: string; nombre: string }[]>([])
const cargando = ref(true)
const cargandoSesiones = ref(false)
const error = ref<string | null>(null)

// ---- Calendario (semana / dia) ----
type Vista = 'semana' | 'dia'
const vista = ref<Vista>('semana')
const semanaInicio = ref(lunesDe(new Date()))
const diaSel = ref(isoDe(new Date()))
const sucursalFiltro = ref('')
const instructorFiltro = ref('')

function pad2(n: number): string {
  return n < 10 ? `0${n}` : `${n}`
}
function isoDe(d: Date): string {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`
}
function lunesDe(d: Date): Date {
  const base = new Date(d.getFullYear(), d.getMonth(), d.getDate())
  const dow = (base.getDay() + 6) % 7 // 0 = lunes
  base.setDate(base.getDate() - dow)
  return base
}
function sumarDias(d: Date, n: number): Date {
  const r = new Date(d)
  r.setDate(r.getDate() + n)
  return r
}

const dias = computed(() =>
  Array.from({ length: 7 }, (_, i) => {
    const fecha = sumarDias(semanaInicio.value, i)
    return {
      fecha,
      iso: isoDe(fecha),
      nombre: new Intl.DateTimeFormat('es-MX', { weekday: 'short' }).format(fecha),
      dia: fecha.getDate(),
      esHoy: isoDe(fecha) === isoDe(new Date()),
    }
  }),
)

const rangoTexto = computed(() => {
  const a = semanaInicio.value
  const b = sumarDias(a, 6)
  const fmt = (d: Date, opts: Intl.DateTimeFormatOptions): string =>
    new Intl.DateTimeFormat('es-MX', opts).format(d)
  return `${fmt(a, { day: 'numeric', month: 'short' })} – ${fmt(b, { day: 'numeric', month: 'short', year: 'numeric' })}`
})

function fechaLocalSesion(iso: string, zona: string): string {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: zona,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(iso))
}
function horaCorta(iso: string, zona: string): string {
  return new Intl.DateTimeFormat('es-MX', {
    timeZone: zona,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(iso))
}
function nombreMiembro(m: Miembro): string {
  return m.nombre_completo || m.nombre
}

// Instructor se filtra en cliente (el server ya filtra por sucursal + rango).
const sesionesVisibles = computed(() =>
  instructorFiltro.value === ''
    ? sesiones.value
    : sesiones.value.filter((s) => s.instructor_id === instructorFiltro.value),
)
function sesionesDe(iso: string): Sesion[] {
  return sesionesVisibles.value
    .filter((s) => fechaLocalSesion(s.inicia_en, s.zona_horaria) === iso)
    .sort((a, b) => a.inicia_en.localeCompare(b.inicia_en))
}
const diaSelInfo = computed(() => dias.value.find((d) => d.iso === diaSel.value) ?? dias.value[0])

function completo(s: Sesion): boolean {
  return s.capacidad !== null && s.ocupados >= s.capacidad
}

async function cargarReferencias(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const [o, s, m] = await Promise.all([
      api.get<{ data: Oferta[] }>(`${base.value}/ofertas`),
      api.get<{ data: Sucursal[] }>(`${base.value}/sucursales`),
      api.get<{ data: Miembro[] }>(`${base.value}/miembros`, { params: { tipo: 'miembro' } }),
    ])
    ofertas.value = o.data.data
    sucursales.value = s.data.data
    miembros.value = m.data.data
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

async function cargarSesiones(): Promise<void> {
  cargandoSesiones.value = true
  error.value = null
  try {
    const params: Record<string, string> = {
      desde: dias.value[0].iso,
      hasta: dias.value[6].iso,
    }
    if (sucursalFiltro.value !== '') {
      params.sucursal_id = sucursalFiltro.value
    }
    const { data } = await api.get<{ data: Sesion[] }>(`${base.value}/sesiones`, { params })
    sesiones.value = data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargandoSesiones.value = false
  }
}

// Recarga las sesiones al cambiar de semana o de sucursal.
watch([semanaInicio, sucursalFiltro], cargarSesiones)

function irSemana(delta: number): void {
  // Preserva el día de la semana seleccionado (para que la vista de día en móvil
  // avance al día equivalente de la nueva semana, no se quede en la anterior).
  const offset = dias.value.findIndex((d) => d.iso === diaSel.value)
  semanaInicio.value = sumarDias(semanaInicio.value, delta * 7)
  diaSel.value = isoDe(sumarDias(semanaInicio.value, offset >= 0 ? offset : 0))
}
function irHoy(): void {
  semanaInicio.value = lunesDe(new Date())
  diaSel.value = isoDe(new Date())
}

// ---- Panel de detalle (reservas + asistencia + check-ins) ----
const detalle = ref<Sesion | null>(null)
const roster = ref<Reserva[]>([])
const cargandoRoster = ref(false)
const reservarModel = ref({ miembroId: '', esperar: false })
const accionando = ref(false)
const checkins = ref<Checkin[]>([])
const checkinModel = ref({ proveedor: 'wellhub', codigo: '' })
const registrandoCheckin = ref(false)
const okCheckin = ref(false)

async function abrirDetalle(s: Sesion): Promise<void> {
  detalle.value = s
  roster.value = []
  checkins.value = []
  reservarModel.value = { miembroId: '', esperar: false }
  checkinModel.value = { proveedor: 'wellhub', codigo: '' }
  okCheckin.value = false
  await cargarRoster(s.id)
  if (puedeCheckin.value) {
    await cargarCheckins(s.id)
  }
}
function cerrarDetalle(): void {
  detalle.value = null
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
async function cargarCheckins(id: string): Promise<void> {
  try {
    const { data } = await api.get<{ data: Checkin[] }>(`${base.value}/sesiones/${id}/checkins`)
    checkins.value = data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  }
}

async function refrescarTras(sesionId: string): Promise<void> {
  await Promise.all([cargarRoster(sesionId), cargarSesiones()])
  // Refresca el encabezado del panel (ocupados/cupo) con la sesion actualizada.
  const actualizada = sesiones.value.find((s) => s.id === sesionId)
  if (actualizada !== undefined && detalle.value !== null) {
    detalle.value = actualizada
  }
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
    await refrescarTras(id)
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
async function aceptar(reservaId: string, sesionId: string): Promise<void> {
  accionando.value = true
  error.value = null
  try {
    await api.post(`${base.value}/reservas/${reservaId}/aceptar`, {})
    await refrescarTras(sesionId)
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
    await refrescarTras(sesionId)
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
    cerrarDetalle()
    await cargarSesiones()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}
async function registrarCheckin(id: string): Promise<void> {
  registrandoCheckin.value = true
  okCheckin.value = false
  error.value = null
  try {
    await api.post(`${base.value}/checkins`, {
      proveedor: checkinModel.value.proveedor,
      sesion_id: id,
      codigo: checkinModel.value.codigo,
    })
    checkinModel.value.codigo = ''
    okCheckin.value = true
    await cargarCheckins(id)
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    registrandoCheckin.value = false
  }
}

// ---- Nueva clase (modal) ----
const mostrarNueva = ref(false)
const form = ref({ ofertaId: '', sucursalId: '', instructorId: '', fecha: '', duracion: '60', capacidad: '' })
const creando = ref(false)

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
    mostrarNueva.value = false
    await cargarSesiones()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    creando.value = false
  }
}

onMounted(async () => {
  await cargarReferencias()
  await cargarSesiones()
})
</script>

<template>
  <section class="px-4 sm:px-6 py-8">
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <EncabezadoSeccion icono="agenda" :titulo="$t('agenda.titulo')" :subtitulo="$t('agenda.subtitulo')" />
      <button v-if="puedeGestionar" class="tu-btn tu-btn-primario" @click="mostrarNueva = true">
        + {{ $t('agenda.nuevaClase') }}
      </button>
    </div>

    <p v-if="cargando" class="mt-8" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>
    <p v-if="error" class="mt-4 text-sm" style="color: var(--error)">{{ error }}</p>

    <template v-if="!cargando">
      <!-- Barra de herramientas: filtros + navegacion + vista -->
      <div class="mt-6 flex flex-wrap items-center gap-2 sm:gap-3">
        <select v-model="sucursalFiltro" class="tu-input w-auto" :aria-label="$t('agenda.nueva.sucursal')">
          <option value="">{{ $t('agenda.todasSucursales') }}</option>
          <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
        </select>
        <select
          v-if="instructores.length > 0"
          v-model="instructorFiltro"
          class="tu-input w-auto"
          :aria-label="$t('agenda.nueva.instructor')"
        >
          <option value="">{{ $t('agenda.todosInstructores') }}</option>
          <option v-for="i in instructores" :key="i.id" :value="i.id">{{ i.nombre }}</option>
        </select>

        <div class="flex items-center gap-1 ml-auto">
          <button class="tu-icono-btn" :aria-label="$t('agenda.semanaAnterior')" @click="irSemana(-1)">‹</button>
          <button class="tu-btn tu-btn-fantasma px-3 py-1.5" @click="irHoy">{{ $t('agenda.hoy') }}</button>
          <button class="tu-icono-btn" :aria-label="$t('agenda.semanaSiguiente')" @click="irSemana(1)">›</button>
          <span class="text-sm font-medium ml-1 hidden sm:inline" :style="{ color: 'var(--texto-suave)' }">{{ rangoTexto }}</span>
        </div>

        <!-- Alternar vista (solo escritorio; movil siempre es dia) -->
        <div class="hidden lg:inline-flex rounded-xl overflow-hidden border" :style="{ borderColor: 'var(--borde)' }">
          <button
            class="px-3 py-1.5 text-sm"
            :style="vista === 'semana' ? { background: 'var(--primario)', color: '#fff' } : {}"
            @click="vista = 'semana'"
          >
            {{ $t('agenda.vistaSemana') }}
          </button>
          <button
            class="px-3 py-1.5 text-sm"
            :style="vista === 'dia' ? { background: 'var(--primario)', color: '#fff' } : {}"
            @click="vista = 'dia'"
          >
            {{ $t('agenda.vistaDia') }}
          </button>
        </div>
      </div>

      <p v-if="cargandoSesiones" class="mt-4 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>

      <!-- ===== Vista SEMANA (escritorio) ===== -->
      <div v-if="vista === 'semana'" class="mt-4 hidden lg:grid grid-cols-7 gap-2">
        <div
          v-for="d in dias"
          :key="d.iso"
          class="rounded-xl border overflow-hidden flex flex-col"
          :style="{ borderColor: 'var(--borde)', background: 'var(--superficie)' }"
        >
          <div
            class="px-2 py-2 text-center border-b"
            :style="{
              borderColor: 'var(--borde)',
              background: d.esHoy ? 'var(--primario-suave)' : 'transparent',
            }"
          >
            <div class="text-xs uppercase" :style="{ color: 'var(--texto-suave)' }">{{ d.nombre }}</div>
            <div class="text-lg font-bold" :style="{ color: d.esHoy ? 'var(--primario-fuerte)' : 'var(--texto)' }">
              {{ d.dia }}
            </div>
          </div>
          <div class="p-1.5 space-y-1.5 min-h-[8rem]">
            <button
              v-for="s in sesionesDe(d.iso)"
              :key="s.id"
              class="tu-clase w-full text-left"
              :class="{ 'opacity-60': s.estado !== 'programada' }"
              @click="abrirDetalle(s)"
            >
              <div class="flex items-center justify-between gap-1">
                <span class="font-semibold text-[13px]">{{ horaCorta(s.inicia_en, s.zona_horaria) }}</span>
                <span
                  class="tu-badge text-[11px]"
                  :class="completo(s) ? 'tu-badge-aviso' : 'tu-badge-exito'"
                >{{ s.capacidad !== null ? `${s.ocupados}/${s.capacidad}` : s.ocupados }}</span>
              </div>
              <div class="text-[13px] font-medium truncate">{{ s.oferta ?? '—' }}</div>
              <div v-if="s.instructor" class="text-[11px] truncate" :style="{ color: 'var(--texto-suave)' }">
                {{ s.instructor }}
              </div>
            </button>
            <p v-if="sesionesDe(d.iso).length === 0" class="text-[11px] text-center py-2" :style="{ color: 'var(--texto-suave)' }">
              —
            </p>
          </div>
        </div>
      </div>

      <!-- ===== Vista DIA (movil siempre; escritorio si vista dia) ===== -->
      <div :class="vista === 'dia' ? 'mt-4' : 'mt-4 lg:hidden'">
        <!-- Tira de dias -->
        <div class="flex gap-1.5 overflow-x-auto pb-2">
          <button
            v-for="d in dias"
            :key="d.iso"
            class="flex-1 min-w-[3rem] rounded-xl border py-2 text-center"
            :style="
              diaSel === d.iso
                ? { background: 'var(--primario)', color: '#fff', borderColor: 'var(--primario)' }
                : { borderColor: 'var(--borde)', background: 'var(--superficie)' }
            "
            @click="diaSel = d.iso"
          >
            <div class="text-[11px] uppercase opacity-80">{{ d.nombre }}</div>
            <div class="text-base font-bold">{{ d.dia }}</div>
          </button>
        </div>

        <ul class="mt-3 space-y-2">
          <li
            v-for="s in sesionesDe(diaSelInfo.iso)"
            :key="s.id"
          >
            <button
              class="tu-card w-full text-left p-3 flex items-center justify-between gap-3"
              :class="{ 'opacity-60': s.estado !== 'programada' }"
              @click="abrirDetalle(s)"
            >
              <div class="min-w-0">
                <div class="font-semibold">{{ horaCorta(s.inicia_en, s.zona_horaria) }} · {{ s.oferta ?? '—' }}</div>
                <div v-if="s.instructor" class="text-sm truncate" :style="{ color: 'var(--texto-suave)' }">{{ s.instructor }}</div>
              </div>
              <span class="tu-badge shrink-0" :class="completo(s) ? 'tu-badge-aviso' : 'tu-badge-exito'">
                {{ s.capacidad !== null ? `${s.ocupados}/${s.capacidad}` : s.ocupados }}
              </span>
            </button>
          </li>
        </ul>
        <p v-if="sesionesDe(diaSelInfo.iso).length === 0" class="mt-6 text-center text-sm" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('agenda.sinClasesDia') }}
        </p>
      </div>
    </template>

    <!-- ===== Panel de detalle (drawer) ===== -->
    <div v-if="detalle" class="fixed inset-0 z-50 flex justify-end">
      <div class="absolute inset-0 bg-black/50" @click="cerrarDetalle" />
      <aside
        class="relative w-full max-w-md h-full overflow-y-auto p-5 shadow-xl"
        :style="{ background: 'var(--superficie)' }"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold">{{ detalle.oferta ?? '—' }}</h2>
            <p class="text-sm" :style="{ color: 'var(--texto-suave)' }">
              {{ horaCorta(detalle.inicia_en, detalle.zona_horaria) }}–{{ horaCorta(detalle.termina_en, detalle.zona_horaria) }}
              <span v-if="detalle.instructor"> · {{ detalle.instructor }}</span>
            </p>
            <span class="tu-badge mt-1 inline-block" :class="completo(detalle) ? 'tu-badge-aviso' : 'tu-badge-exito'">
              {{ detalle.capacidad !== null ? `${detalle.ocupados}/${detalle.capacidad}` : `${detalle.ocupados}` }}
              <span v-if="completo(detalle)"> · {{ $t('agenda.completo') }}</span>
            </span>
          </div>
          <button class="tu-icono-btn" :aria-label="$t('agenda.cerrarDetalle')" @click="cerrarDetalle">✕</button>
        </div>

        <div v-if="detalle.estado === 'programada'" class="mt-4">
          <!-- Reservar -->
          <form
            v-if="puedeReservar && miembros.length > 0"
            class="flex flex-wrap items-end gap-2"
            @submit.prevent="reservar(detalle.id)"
          >
            <div class="flex-1 min-w-[160px]">
              <label class="tu-label" for="rm">{{ $t('agenda.reservar.miembro') }}</label>
              <select id="rm" v-model="reservarModel.miembroId" class="tu-input" required>
                <option value="" disabled>{{ $t('agenda.reservar.elegir') }}</option>
                <option v-for="m in miembros" :key="m.id" :value="m.id">{{ nombreMiembro(m) }}</option>
              </select>
            </div>
            <label class="flex items-center gap-1.5 text-sm pb-2.5">
              <input v-model="reservarModel.esperar" type="checkbox" />
              {{ $t('agenda.reservar.esperar') }}
            </label>
            <button class="tu-btn tu-btn-primario" type="submit" :disabled="accionando || reservarModel.miembroId === ''">
              {{ $t('agenda.reservar.reservar') }}
            </button>
          </form>
        </div>

        <!-- Roster -->
        <div class="mt-4 border-t pt-4" :style="{ borderColor: 'var(--borde)' }">
          <h3 class="font-semibold text-sm">{{ $t('agenda.sesion.reservas') }}</h3>
          <p v-if="cargandoRoster" class="mt-2 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>
          <p v-else-if="roster.length === 0" class="mt-2 text-sm" :style="{ color: 'var(--texto-suave)' }">
            {{ $t('agenda.roster.vacio') }}
          </p>
          <ul v-else class="mt-2 space-y-2">
            <li v-for="r in roster" :key="r.id" class="flex items-center justify-between gap-2 text-sm">
              <span class="flex items-center gap-2 min-w-0">
                <span class="truncate">{{ r.persona ?? '—' }}</span>
                <span
                  class="tu-badge"
                  :class="{
                    'tu-badge-exito': r.estado === 'confirmada',
                    'tu-badge-aviso': r.estado === 'en_espera' || r.estado === 'ofrecida',
                  }"
                >{{ $t(`agenda.roster.${r.estado}`) }}</span>
                <span v-if="r.asistencia" class="tu-badge">{{ $t(`agenda.roster.${r.asistencia}`) }}</span>
              </span>
              <span class="flex items-center gap-2 shrink-0">
                <button
                  v-if="r.estado === 'ofrecida' && puedeReservar"
                  class="tu-enlace"
                  :disabled="accionando"
                  @click="aceptar(r.id, detalle.id)"
                >
                  {{ $t('agenda.roster.aceptar') }}
                </button>
                <template v-if="r.estado === 'confirmada'">
                  <button v-if="puedeMarcar" class="tu-enlace" :disabled="accionando" @click="marcar(r.id, 'presente', detalle.id)">
                    {{ $t('agenda.roster.marcarPresente') }}
                  </button>
                  <button v-if="puedeMarcar" class="tu-enlace" :disabled="accionando" @click="marcar(r.id, 'ausente', detalle.id)">
                    {{ $t('agenda.roster.marcarAusente') }}
                  </button>
                </template>
                <button
                  v-if="puedeReservar && r.estado !== 'cancelada'"
                  class="tu-enlace"
                  style="color: var(--error)"
                  :disabled="accionando"
                  @click="cancelarReserva(r.id, detalle.id)"
                >
                  {{ $t('agenda.roster.cancelarReserva') }}
                </button>
              </span>
            </li>
          </ul>
        </div>

        <!-- Check-ins de bienestar -->
        <div v-if="puedeCheckin" class="mt-4 border-t pt-4" :style="{ borderColor: 'var(--borde)' }">
          <h3 class="font-semibold text-sm">{{ $t('checkins.titulo') }}</h3>
          <form class="mt-2 flex flex-wrap items-end gap-2" @submit.prevent="registrarCheckin(detalle.id)">
            <div class="min-w-[130px]">
              <label class="tu-label" for="cp">{{ $t('checkins.proveedor') }}</label>
              <select id="cp" v-model="checkinModel.proveedor" class="tu-input">
                <option value="wellhub">{{ $t('integraciones.proveedores.wellhub') }}</option>
                <option value="totalpass">{{ $t('integraciones.proveedores.totalpass') }}</option>
              </select>
            </div>
            <div class="flex-1 min-w-[150px]">
              <label class="tu-label" for="cc">{{ $t('checkins.codigo') }}</label>
              <input id="cc" v-model="checkinModel.codigo" class="tu-input" autocomplete="off" required />
            </div>
            <button class="tu-btn tu-btn-primario" type="submit" :disabled="registrandoCheckin || checkinModel.codigo === ''">
              {{ registrandoCheckin ? $t('checkins.registrando') : $t('checkins.registrar') }}
            </button>
          </form>
          <p v-if="okCheckin" class="mt-2 text-sm" :style="{ color: 'var(--exito)' }">{{ $t('checkins.ok') }}</p>
          <ul v-if="checkins.length > 0" class="mt-3 space-y-2">
            <li v-for="c in checkins" :key="c.id" class="flex items-center justify-between gap-2 text-sm">
              <span class="truncate">{{ c.usuario ?? '—' }}</span>
              <span class="tu-badge tu-badge-exito shrink-0">{{ $t(`checkins.${c.estado}`) }}</span>
            </li>
          </ul>
        </div>

        <!-- Cancelar clase -->
        <div v-if="puedeGestionar && detalle.estado === 'programada'" class="mt-5 border-t pt-4" :style="{ borderColor: 'var(--borde)' }">
          <button class="tu-enlace text-sm" style="color: var(--error)" :disabled="accionando" @click="cancelarSesion(detalle.id)">
            {{ $t('agenda.sesion.cancelar') }}
          </button>
        </div>
      </aside>
    </div>

    <!-- ===== Modal Nueva clase ===== -->
    <div v-if="mostrarNueva" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="mostrarNueva = false" />
      <div class="relative tu-card w-full max-w-lg p-6">
        <div class="flex items-center justify-between">
          <h2 class="font-bold text-lg">{{ $t('agenda.nueva.titulo') }}</h2>
          <button class="tu-icono-btn" :aria-label="$t('agenda.cerrarDetalle')" @click="mostrarNueva = false">✕</button>
        </div>
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
          <div v-if="instructores.length > 0" class="sm:col-span-2">
            <label class="tu-label" for="ai">{{ $t('agenda.nueva.instructor') }}</label>
            <select id="ai" v-model="form.instructorId" class="tu-input">
              <option value="">{{ $t('agenda.nueva.sinInstructor') }}</option>
              <option v-for="i in instructores" :key="i.id" :value="i.id">{{ i.nombre }}</option>
            </select>
          </div>
          <div class="sm:col-span-2 flex justify-end gap-2">
            <button type="button" class="tu-btn tu-btn-fantasma" @click="mostrarNueva = false">{{ $t('comun.cancelar') }}</button>
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
    </div>
  </section>
</template>

<style scoped>
.tu-clase {
  border-radius: 0.6rem;
  padding: 0.4rem 0.5rem;
  background: var(--primario-suave);
  border: 1px solid transparent;
  cursor: pointer;
  transition:
    border-color 0.15s ease,
    transform 0.05s ease;
}
.tu-clase:hover {
  border-color: var(--primario);
}
.tu-clase:active {
  transform: scale(0.99);
}
</style>
