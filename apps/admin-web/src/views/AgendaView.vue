<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface SucursalItem {
  id: string
  nombre: string
}

interface OfertaItem {
  id: string
  nombre: string
  actividad: string
  capacidad: number | null
}

interface PersonaItem {
  id: string
  nombre: string
  apellidos: string | null
}

interface InstructorSesion {
  persona_id: string
  nombre: string
  rol: string
}

interface SesionItem {
  id: string
  oferta: string
  inicia_en: string
  termina_en: string
  zona_horaria: string
  capacidad: number | null
  estado: string
  instructores: InstructorSesion[]
}

interface ReservaItem {
  id: string
  estado: string
  persona: string
  inicia_en: string
  unidades: number
  asistencia: string | null
}

const { t } = useI18n()
const auth = useAuthStore()

const diasSemana = [
  { valor: 1, clave: 'lun' },
  { valor: 2, clave: 'mar' },
  { valor: 3, clave: 'mie' },
  { valor: 4, clave: 'jue' },
  { valor: 5, clave: 'vie' },
  { valor: 6, clave: 'sab' },
  { valor: 7, clave: 'dom' },
]

const sucursales = ref<SucursalItem[]>([])
const sucursalId = ref('')
const ofertas = ref<OfertaItem[]>([])
const personas = ref<PersonaItem[]>([])
const sesiones = ref<SesionItem[]>([])

const desde = ref(hoyISO())
const hasta = ref(sumaDiasISO(30))

// Formulario de clase recurrente.
const ofertaId = ref('')
const duracion = ref('60')
const capacidad = ref('')
const vigenteDesde = ref(hoyISO())
const generarHasta = ref(sumaDiasISO(30))
const hora = ref('19:00')
const diasSeleccionados = ref<number[]>([])

// Formulario de clase única (ad-hoc).
const ofertaUnicaId = ref('')
const inicioUnica = ref('')
const duracionUnica = ref('60')
const capacidadUnica = ref('')

const instructorSel = ref<Record<string, string>>({})
const error = ref<string | null>(null)

// Reservas (roster) por sesión.
const rosterAbierto = ref<string | null>(null)
const rosters = ref<Record<string, ReservaItem[]>>({})
const reservaPersona = ref<Record<string, string>>({})
const reservaEsperar = ref<Record<string, boolean>>({})

const puedeGestionar = computed(() => auth.puede('agenda.gestionar'))
const puedeVerReservas = computed(() => auth.puede('reservas.ver'))
const puedeReservar = computed(() => auth.puede('reservas.crear'))
const puedeCancelarReserva = computed(() => auth.puede('reservas.cancelar'))
const puedeAsistencia = computed(() => auth.puede('asistencia.registrar'))
const haySucursales = computed(() => sucursales.value.length > 0)
const hayOfertas = computed(() => ofertas.value.length > 0)

function hoyISO(): string {
  return new Date().toISOString().slice(0, 10)
}

function sumaDiasISO(n: number): string {
  const fecha = new Date()
  fecha.setDate(fecha.getDate() + n)
  return fecha.toISOString().slice(0, 10)
}

function fechaHoraLocal(sesion: SesionItem): string {
  return new Intl.DateTimeFormat('es-MX', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: sesion.zona_horaria,
  }).format(new Date(sesion.inicia_en))
}

async function cargarSucursales(): Promise<void> {
  const { data } = await api.get<{ data: SucursalItem[] }>('/api/v1/sucursales')
  sucursales.value = data.data
  if (sucursalId.value === '' && sucursales.value.length > 0) {
    sucursalId.value = sucursales.value[0].id
  }
}

async function cargarOfertas(): Promise<void> {
  const { data } = await api.get<{ data: OfertaItem[] }>('/api/v1/ofertas')
  ofertas.value = data.data
  if (ofertas.value.length > 0) {
    if (ofertaId.value === '') {
      ofertaId.value = ofertas.value[0].id
    }
    if (ofertaUnicaId.value === '') {
      ofertaUnicaId.value = ofertas.value[0].id
    }
  }
}

async function cargarPersonas(): Promise<void> {
  const { data } = await api.get<{ data: PersonaItem[] }>('/api/v1/personas')
  personas.value = data.data
}

async function cargarSesiones(): Promise<void> {
  if (sucursalId.value === '') {
    sesiones.value = []
    return
  }
  const { data } = await api.get<{ data: SesionItem[] }>(
    `/api/v1/sucursales/${sucursalId.value}/sesiones?desde=${desde.value}&hasta=${hasta.value}`,
  )
  sesiones.value = data.data
}

async function crearClaseRecurrente(): Promise<void> {
  error.value = null
  if (diasSeleccionados.value.length === 0) {
    error.value = t('agenda.sinDias')
    return
  }
  try {
    const capacidadNum = capacidad.value === '' ? null : Number(capacidad.value)
    const { data } = await api.post<{ data: { id: string } }>(
      `/api/v1/ofertas/${ofertaId.value}/plantillas-horario`,
      {
        sucursal_id: sucursalId.value,
        duracion_minutos: Number(duracion.value),
        capacidad: capacidadNum,
        vigente_desde: vigenteDesde.value,
        reglas: diasSeleccionados.value.map((dia) => ({
          dia_semana: dia,
          hora_inicio: hora.value,
        })),
      },
    )
    await api.post(`/api/v1/plantillas-horario/${data.data.id}/sesiones`, {
      desde: vigenteDesde.value,
      hasta: generarHasta.value,
    })
    diasSeleccionados.value = []
    await cargarSesiones()
  } catch {
    error.value = t('agenda.errorGenerico')
  }
}

async function crearClaseUnica(): Promise<void> {
  error.value = null
  if (ofertaUnicaId.value === '' || inicioUnica.value === '') {
    error.value = t('agenda.errorGenerico')
    return
  }
  try {
    const capacidadNum = capacidadUnica.value === '' ? null : Number(capacidadUnica.value)
    await api.post(`/api/v1/sucursales/${sucursalId.value}/sesiones`, {
      oferta_id: ofertaUnicaId.value,
      // datetime-local entrega "YYYY-MM-DDTHH:mm"; el backend lo interpreta en la zona de la sucursal.
      inicia_en_local: inicioUnica.value.replace('T', ' '),
      duracion_minutos: Number(duracionUnica.value),
      capacidad: capacidadNum,
    })
    inicioUnica.value = ''
    capacidadUnica.value = ''
    await cargarSesiones()
  } catch {
    error.value = t('agenda.errorGenerico')
  }
}

async function cancelar(sesionId: string): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/sesiones/${sesionId}/cancelar`)
    await cargarSesiones()
  } catch {
    error.value = t('agenda.errorGenerico')
  }
}

async function asignar(sesionId: string): Promise<void> {
  error.value = null
  const personaId = instructorSel.value[sesionId]
  if (!personaId) {
    return
  }
  try {
    await api.post(`/api/v1/sesiones/${sesionId}/asignaciones`, { persona_id: personaId })
    instructorSel.value[sesionId] = ''
    await cargarSesiones()
  } catch {
    error.value = t('agenda.errorGenerico')
  }
}

async function cargarRoster(sesionId: string): Promise<void> {
  const { data } = await api.get<{ data: ReservaItem[] }>(`/api/v1/sesiones/${sesionId}/reservas`)
  rosters.value[sesionId] = data.data
}

async function alternarRoster(sesionId: string): Promise<void> {
  if (rosterAbierto.value === sesionId) {
    rosterAbierto.value = null
    return
  }
  rosterAbierto.value = sesionId
  if (puedeVerReservas.value) {
    await cargarRoster(sesionId)
  }
}

async function reservar(sesionId: string): Promise<void> {
  error.value = null
  const personaId = reservaPersona.value[sesionId]
  if (!personaId) {
    return
  }
  try {
    await api.post(`/api/v1/sesiones/${sesionId}/reservas`, {
      persona_id: personaId,
      esperar: reservaEsperar.value[sesionId] ?? false,
    })
    reservaPersona.value[sesionId] = ''
    await cargarRoster(sesionId)
  } catch {
    error.value = t('agenda.reservas.errorReservar')
  }
}

async function cancelarReserva(reservaId: string, sesionId: string): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/reservas/${reservaId}/cancelar`)
    await cargarRoster(sesionId)
  } catch {
    error.value = t('agenda.errorGenerico')
  }
}

async function marcarAsistencia(reservaId: string, sesionId: string, estado: string): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/reservas/${reservaId}/asistencia`, { estado })
    await cargarRoster(sesionId)
  } catch {
    error.value = t('agenda.errorGenerico')
  }
}

watch(sucursalId, () => {
  void cargarSesiones()
})

onMounted(async () => {
  await Promise.all([cargarSucursales(), cargarOfertas(), cargarPersonas()])
  await cargarSesiones()
})
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('agenda.titulo') }}</h2>

    <p v-if="!haySucursales" class="text-sm text-slate-500">{{ t('agenda.sinSucursales') }}</p>

    <template v-else>
      <div class="flex flex-wrap items-end gap-4">
        <label class="text-sm">
          <span class="text-slate-600">{{ t('agenda.sucursal') }}</span>
          <select v-model="sucursalId" class="mt-1 w-56 rounded-md border border-slate-300 px-2 py-2">
            <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
              {{ sucursal.nombre }}
            </option>
          </select>
        </label>
        <label class="text-sm">
          <span class="text-slate-600">{{ t('agenda.desde') }}</span>
          <input v-model="desde" type="date" class="mt-1 rounded-md border border-slate-300 px-2 py-2" />
        </label>
        <label class="text-sm">
          <span class="text-slate-600">{{ t('agenda.hasta') }}</span>
          <input v-model="hasta" type="date" class="mt-1 rounded-md border border-slate-300 px-2 py-2" />
        </label>
        <button
          class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
          @click="cargarSesiones"
        >
          {{ t('agenda.ver') }}
        </button>
      </div>

      <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Agenda -->
        <div class="space-y-3">
          <h3 class="text-sm font-medium text-slate-700">{{ t('agenda.sesiones') }}</h3>
          <p v-if="sesiones.length === 0" class="text-sm text-slate-500">{{ t('agenda.sinSesiones') }}</p>
          <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
            <li v-for="sesion in sesiones" :key="sesion.id" class="space-y-2 px-4 py-3 text-sm">
              <div class="flex items-center justify-between">
                <span>
                  <span class="font-medium">{{ fechaHoraLocal(sesion) }}</span>
                  <span class="text-slate-400"> · {{ sesion.oferta }}</span>
                </span>
                <span
                  class="text-xs"
                  :class="sesion.estado === 'cancelada' ? 'text-red-600' : 'text-slate-500'"
                >
                  {{ t('agenda.estados.' + sesion.estado) }}
                  <template v-if="sesion.capacidad !== null"> · {{ sesion.capacidad }}</template>
                </span>
              </div>
              <p v-if="sesion.instructores.length" class="text-xs text-slate-500">
                {{ t('agenda.instructores') }}:
                {{ sesion.instructores.map((i) => i.nombre).join(', ') }}
              </p>
              <div
                v-if="puedeGestionar && sesion.estado === 'programada'"
                class="flex flex-wrap items-center gap-2"
              >
                <select
                  v-model="instructorSel[sesion.id]"
                  class="rounded-md border border-slate-300 px-2 py-1 text-xs"
                >
                  <option value="">{{ t('agenda.elegirInstructor') }}</option>
                  <option v-for="persona in personas" :key="persona.id" :value="persona.id">
                    {{ persona.nombre }} {{ persona.apellidos }}
                  </option>
                </select>
                <button
                  class="rounded-md bg-slate-700 px-2 py-1 text-xs font-medium text-white hover:bg-slate-600"
                  @click="asignar(sesion.id)"
                >
                  {{ t('agenda.asignar') }}
                </button>
                <button
                  class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                  @click="cancelar(sesion.id)"
                >
                  {{ t('agenda.cancelar') }}
                </button>
              </div>

              <div v-if="puedeVerReservas && sesion.estado === 'programada'">
                <button
                  class="text-xs font-medium text-slate-600 hover:text-slate-900"
                  @click="alternarRoster(sesion.id)"
                >
                  {{ rosterAbierto === sesion.id ? '▾' : '▸' }} {{ t('agenda.reservas.titulo') }}
                </button>

                <div v-if="rosterAbierto === sesion.id" class="mt-2 space-y-2 rounded-md bg-slate-50 p-3">
                  <ul v-if="(rosters[sesion.id]?.length ?? 0) > 0" class="space-y-1">
                    <li
                      v-for="reserva in rosters[sesion.id]"
                      :key="reserva.id"
                      class="flex flex-wrap items-center justify-between gap-2"
                    >
                      <span>
                        {{ reserva.persona }}
                        <span
                          class="text-xs"
                          :class="reserva.estado === 'en_espera' ? 'text-amber-600' : 'text-slate-400'"
                        >
                          · {{ t('agenda.reservas.estados.' + reserva.estado) }}
                        </span>
                        <span v-if="reserva.asistencia" class="text-xs text-emerald-600">
                          · {{ t('agenda.reservas.asistencias.' + reserva.asistencia) }}
                        </span>
                      </span>
                      <span class="flex items-center gap-1">
                        <template v-if="puedeAsistencia && reserva.estado === 'confirmada'">
                          <button
                            class="rounded border border-emerald-300 px-1.5 py-0.5 text-xs text-emerald-700 hover:bg-emerald-50"
                            @click="marcarAsistencia(reserva.id, sesion.id, 'presente')"
                          >
                            {{ t('agenda.reservas.presente') }}
                          </button>
                          <button
                            class="rounded border border-slate-300 px-1.5 py-0.5 text-xs text-slate-600 hover:bg-slate-100"
                            @click="marcarAsistencia(reserva.id, sesion.id, 'ausente')"
                          >
                            {{ t('agenda.reservas.ausente') }}
                          </button>
                        </template>
                        <button
                          v-if="puedeCancelarReserva"
                          class="rounded border border-red-300 px-1.5 py-0.5 text-xs text-red-700 hover:bg-red-50"
                          @click="cancelarReserva(reserva.id, sesion.id)"
                        >
                          {{ t('agenda.reservas.cancelar') }}
                        </button>
                      </span>
                    </li>
                  </ul>
                  <p v-else class="text-xs text-slate-500">{{ t('agenda.reservas.sinReservas') }}</p>

                  <form
                    v-if="puedeReservar"
                    class="flex flex-wrap items-center gap-2 border-t border-slate-200 pt-2"
                    @submit.prevent="reservar(sesion.id)"
                  >
                    <select
                      v-model="reservaPersona[sesion.id]"
                      class="rounded-md border border-slate-300 px-2 py-1 text-xs"
                    >
                      <option value="">{{ t('agenda.reservas.elegirPersona') }}</option>
                      <option v-for="persona in personas" :key="persona.id" :value="persona.id">
                        {{ persona.nombre }} {{ persona.apellidos }}
                      </option>
                    </select>
                    <label class="flex items-center gap-1 text-xs text-slate-600">
                      <input v-model="reservaEsperar[sesion.id]" type="checkbox" />
                      {{ t('agenda.reservas.esperar') }}
                    </label>
                    <button
                      type="submit"
                      class="rounded-md bg-slate-800 px-2 py-1 text-xs font-medium text-white hover:bg-slate-700"
                    >
                      {{ t('agenda.reservas.reservar') }}
                    </button>
                  </form>
                </div>
              </div>
            </li>
          </ul>
        </div>

        <!-- Nueva clase recurrente -->
        <div class="space-y-3">
          <h3 class="text-sm font-medium text-slate-700">{{ t('agenda.nuevaClase') }}</h3>
          <p v-if="!hayOfertas" class="text-sm text-slate-500">{{ t('agenda.sinOfertas') }}</p>
          <form
            v-else-if="puedeGestionar"
            class="space-y-3 rounded-lg border border-slate-200 bg-white p-4"
            @submit.prevent="crearClaseRecurrente"
          >
            <label class="block text-sm">
              <span class="text-slate-600">{{ t('agenda.oferta') }}</span>
              <select v-model="ofertaId" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                <option v-for="oferta in ofertas" :key="oferta.id" :value="oferta.id">
                  {{ oferta.nombre }} · {{ oferta.actividad }}
                </option>
              </select>
            </label>

            <div class="space-y-1">
              <span class="text-sm text-slate-600">{{ t('agenda.dias') }}</span>
              <div class="flex flex-wrap gap-2">
                <label
                  v-for="dia in diasSemana"
                  :key="dia.valor"
                  class="flex items-center gap-1 text-xs text-slate-600"
                >
                  <input v-model="diasSeleccionados" type="checkbox" :value="dia.valor" />
                  {{ t('agenda.diasCorto.' + dia.clave) }}
                </label>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <label class="text-sm">
                <span class="text-slate-600">{{ t('agenda.hora') }}</span>
                <input v-model="hora" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm" />
              </label>
              <label class="text-sm">
                <span class="text-slate-600">{{ t('agenda.duracion') }}</span>
                <input v-model="duracion" type="number" min="1" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm" />
              </label>
            </div>

            <label class="block text-sm">
              <span class="text-slate-600">{{ t('agenda.capacidad') }}</span>
              <input
                v-model="capacidad"
                type="number"
                min="1"
                :placeholder="t('agenda.capacidadOferta')"
                class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
              />
            </label>

            <div class="grid grid-cols-2 gap-2">
              <label class="text-sm">
                <span class="text-slate-600">{{ t('agenda.vigenteDesde') }}</span>
                <input v-model="vigenteDesde" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm" />
              </label>
              <label class="text-sm">
                <span class="text-slate-600">{{ t('agenda.generarHasta') }}</span>
                <input v-model="generarHasta" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm" />
              </label>
            </div>

            <button
              type="submit"
              class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
            >
              {{ t('agenda.crearYGenerar') }}
            </button>
          </form>

          <!-- Nueva clase única (ad-hoc) -->
          <h3 class="pt-2 text-sm font-medium text-slate-700">{{ t('agenda.nuevaClaseUnica') }}</h3>
          <form
            v-if="puedeGestionar && hayOfertas"
            class="space-y-3 rounded-lg border border-slate-200 bg-white p-4"
            @submit.prevent="crearClaseUnica"
          >
            <label class="block text-sm">
              <span class="text-slate-600">{{ t('agenda.oferta') }}</span>
              <select v-model="ofertaUnicaId" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                <option v-for="oferta in ofertas" :key="oferta.id" :value="oferta.id">
                  {{ oferta.nombre }} · {{ oferta.actividad }}
                </option>
              </select>
            </label>
            <label class="block text-sm">
              <span class="text-slate-600">{{ t('agenda.fechaHora') }}</span>
              <input
                v-model="inicioUnica"
                type="datetime-local"
                class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
              />
            </label>
            <div class="grid grid-cols-2 gap-2">
              <label class="text-sm">
                <span class="text-slate-600">{{ t('agenda.duracion') }}</span>
                <input v-model="duracionUnica" type="number" min="1" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm" />
              </label>
              <label class="text-sm">
                <span class="text-slate-600">{{ t('agenda.capacidad') }}</span>
                <input
                  v-model="capacidadUnica"
                  type="number"
                  min="1"
                  :placeholder="t('agenda.capacidadOferta')"
                  class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                />
              </label>
            </div>
            <button
              type="submit"
              class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
            >
              {{ t('agenda.crearUnica') }}
            </button>
          </form>
        </div>
      </div>
    </template>
  </section>
</template>
