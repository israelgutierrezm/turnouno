<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface Instructor {
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
  instructores: Instructor[]
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

const sesiones = ref<SesionItem[]>([])
const rosters = ref<Record<string, ReservaItem[]>>({})
const abierta = ref<string | null>(null)
const error = ref<string | null>(null)

const puedeVerReservas = computed(() => auth.puede('reservas.ver'))
const puedeAsistencia = computed(() => auth.puede('asistencia.registrar'))

function horaLocal(sesion: SesionItem): string {
  return new Intl.DateTimeFormat('es-MX', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: sesion.zona_horaria,
  }).format(new Date(sesion.inicia_en))
}

async function cargar(): Promise<void> {
  error.value = null
  try {
    const { data } = await api.get<{ data: SesionItem[] }>('/api/v1/mis-sesiones')
    sesiones.value = data.data
  } catch {
    error.value = t('miAgenda.error')
  }
}

async function alternarRoster(sesion: SesionItem): Promise<void> {
  if (abierta.value === sesion.id) {
    abierta.value = null
    return
  }
  abierta.value = sesion.id
  if (rosters.value[sesion.id] === undefined) {
    await cargarRoster(sesion.id)
  }
}

async function cargarRoster(sesionId: string): Promise<void> {
  try {
    const { data } = await api.get<{ data: ReservaItem[] }>(`/api/v1/sesiones/${sesionId}/reservas`)
    rosters.value[sesionId] = data.data
  } catch {
    error.value = t('miAgenda.error')
  }
}

async function marcar(reservaId: string, sesionId: string, estado: string): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/reservas/${reservaId}/asistencia`, { estado })
    await cargarRoster(sesionId)
  } catch {
    error.value = t('miAgenda.error')
  }
}

onMounted(cargar)
</script>

<template>
  <section class="space-y-4">
    <h2 class="text-xl font-semibold">{{ t('miAgenda.titulo') }}</h2>
    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
    <p v-if="sesiones.length === 0" class="text-sm text-slate-500">{{ t('miAgenda.sinSesiones') }}</p>

    <ul v-else class="space-y-3">
      <li v-for="sesion in sesiones" :key="sesion.id" class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span>
            <span class="font-medium">{{ horaLocal(sesion) }}</span>
            <span class="text-slate-400"> · {{ sesion.oferta }}</span>
            <span v-if="sesion.capacidad !== null" class="text-xs text-slate-400">
              · {{ t('miAgenda.cupo') }} {{ sesion.capacidad }}
            </span>
          </span>
          <button
            v-if="puedeVerReservas"
            class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100"
            @click="alternarRoster(sesion)"
          >
            {{ abierta === sesion.id ? t('miAgenda.ocultar') : t('miAgenda.verLista') }}
          </button>
        </div>

        <p v-if="sesion.instructores.length > 0" class="mt-1 text-xs text-slate-400">
          {{ t('miAgenda.instructores') }}: {{ sesion.instructores.map((i) => i.nombre).join(', ') }}
        </p>

        <div v-if="puedeVerReservas && abierta === sesion.id" class="mt-3 border-t border-slate-100 pt-2">
          <p v-if="(rosters[sesion.id] ?? []).length === 0" class="text-xs text-slate-400">
            {{ t('miAgenda.sinReservas') }}
          </p>
          <ul v-else class="space-y-1">
            <li
              v-for="reserva in rosters[sesion.id]"
              :key="reserva.id"
              class="flex flex-wrap items-center justify-between gap-2"
            >
              <span class="text-xs">
                {{ reserva.persona }}
                <span
                  class="ml-1"
                  :class="reserva.estado === 'en_espera' ? 'text-amber-600' : 'text-emerald-600'"
                >
                  · {{ t('miAgenda.estados.' + reserva.estado) }}
                </span>
                <span v-if="reserva.asistencia" class="ml-1 text-slate-500">
                  · {{ t('miAgenda.asistencias.' + reserva.asistencia) }}
                </span>
              </span>
              <span v-if="puedeAsistencia && reserva.estado === 'confirmada'" class="flex gap-1">
                <button
                  class="rounded-md bg-emerald-700 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-600"
                  @click="marcar(reserva.id, sesion.id, 'presente')"
                >
                  {{ t('miAgenda.presente') }}
                </button>
                <button
                  class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100"
                  @click="marcar(reserva.id, sesion.id, 'ausente')"
                >
                  {{ t('miAgenda.ausente') }}
                </button>
              </span>
            </li>
          </ul>
        </div>
      </li>
    </ul>
  </section>
</template>
