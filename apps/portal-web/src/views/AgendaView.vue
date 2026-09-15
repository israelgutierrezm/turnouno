<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'

interface SesionItem {
  id: string
  oferta: string
  inicia_en: string
  zona_horaria: string
  capacidad: number | null
  estado: string
}

const { t } = useI18n()

const sesiones = ref<SesionItem[]>([])
const reservadas = ref<Set<string>>(new Set())
const error = ref<string | null>(null)

function fechaLocal(sesion: SesionItem): string {
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
  const { data } = await api.get<{ data: SesionItem[] }>('/api/v1/mi/agenda')
  sesiones.value = data.data
}

async function reservar(sesion: SesionItem, esperar: boolean): Promise<void> {
  error.value = null
  try {
    await api.post('/api/v1/mi/reservas', { sesion_id: sesion.id, esperar })
    reservadas.value.add(sesion.id)
  } catch {
    error.value = t('agenda.error')
  }
}

onMounted(cargar)
</script>

<template>
  <section class="space-y-4">
    <h1 class="text-xl font-semibold">{{ t('agenda.titulo') }}</h1>
    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
    <p v-if="sesiones.length === 0" class="text-sm text-slate-500">{{ t('agenda.sinClases') }}</p>

    <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
      <li
        v-for="sesion in sesiones"
        :key="sesion.id"
        class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
      >
        <span>
          <span class="font-medium">{{ fechaLocal(sesion) }}</span>
          <span class="text-slate-400"> · {{ sesion.oferta }}</span>
          <span v-if="sesion.capacidad !== null" class="text-xs text-slate-400">
            · {{ t('agenda.cupo') }} {{ sesion.capacidad }}
          </span>
        </span>
        <span class="flex items-center gap-2">
          <span v-if="reservadas.has(sesion.id)" class="text-xs font-medium text-emerald-600">
            {{ t('agenda.reservado') }}
          </span>
          <template v-else>
            <button
              class="rounded-md bg-slate-800 px-3 py-1 text-xs font-medium text-white hover:bg-slate-700"
              @click="reservar(sesion, false)"
            >
              {{ t('agenda.reservar') }}
            </button>
            <button
              class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100"
              @click="reservar(sesion, true)"
            >
              {{ t('agenda.esperar') }}
            </button>
          </template>
        </span>
      </li>
    </ul>
  </section>
</template>
