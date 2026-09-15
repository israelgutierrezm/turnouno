<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface DerechoItem {
  producto: string
  ilimitado: boolean
  saldo_creditos: number
  disponible_creditos: number
}

interface ReservaItem {
  id: string
  oferta: string
  inicia_en: string
  zona_horaria: string
  estado: string
}

const { t } = useI18n()
const auth = useAuthStore()

const derechos = ref<DerechoItem[]>([])
const reservas = ref<ReservaItem[]>([])

function fechaLocal(reserva: ReservaItem): string {
  return new Intl.DateTimeFormat('es-MX', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: reserva.zona_horaria,
  }).format(new Date(reserva.inicia_en))
}

async function cargar(): Promise<void> {
  const { data } = await api.get<{ data: { derechos: DerechoItem[]; reservas: ReservaItem[] } }>('/api/v1/mi/perfil')
  derechos.value = data.data.derechos
  reservas.value = data.data.reservas
}

onMounted(cargar)
</script>

<template>
  <section class="space-y-6">
    <h1 class="text-xl font-semibold">{{ t('perfil.titulo', { nombre: auth.nombre }) }}</h1>

    <div class="space-y-2">
      <h2 class="text-sm font-medium text-slate-700">{{ t('perfil.derechos') }}</h2>
      <p v-if="derechos.length === 0" class="text-sm text-slate-500">{{ t('perfil.sinDerechos') }}</p>
      <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
        <li
          v-for="(derecho, i) in derechos"
          :key="i"
          class="flex items-center justify-between px-4 py-3 text-sm"
        >
          <span class="font-medium">{{ derecho.producto }}</span>
          <span class="text-slate-500">
            <template v-if="derecho.ilimitado">{{ t('perfil.ilimitado') }}</template>
            <template v-else>
              {{ t('perfil.disponible') }}: {{ derecho.disponible_creditos }}
            </template>
          </span>
        </li>
      </ul>
    </div>

    <div class="space-y-2">
      <h2 class="text-sm font-medium text-slate-700">{{ t('perfil.reservas') }}</h2>
      <p v-if="reservas.length === 0" class="text-sm text-slate-500">{{ t('perfil.sinReservas') }}</p>
      <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
        <li
          v-for="reserva in reservas"
          :key="reserva.id"
          class="flex items-center justify-between px-4 py-3 text-sm"
        >
          <span>
            <span class="font-medium">{{ fechaLocal(reserva) }}</span>
            <span class="text-slate-400"> · {{ reserva.oferta }}</span>
          </span>
          <span class="text-xs" :class="reserva.estado === 'en_espera' ? 'text-amber-600' : 'text-emerald-600'">
            {{ t('perfil.estados.' + reserva.estado) }}
          </span>
        </li>
      </ul>
    </div>
  </section>
</template>
