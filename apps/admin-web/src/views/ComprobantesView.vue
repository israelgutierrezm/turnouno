<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface ComprobantePendiente {
  id: string
  persona: string
  total_minor: number
  moneda: string
  comprobante: boolean
  subido_en: string | null
}

const { t } = useI18n()
const auth = useAuthStore()

const pendientes = ref<ComprobantePendiente[]>([])
const error = ref<string | null>(null)

const puedeRevisar = computed(() => auth.puede('pagos.crear'))

function importe(pago: ComprobantePendiente): string {
  return `${(pago.total_minor / 100).toFixed(2)} ${pago.moneda}`
}

async function cargar(): Promise<void> {
  const { data } = await api.get<{ data: ComprobantePendiente[] }>('/api/v1/pagos/ventanilla/pendientes')
  pendientes.value = data.data
}

async function verComprobante(id: string): Promise<void> {
  error.value = null
  try {
    const { data } = await api.get(`/api/v1/pagos/${id}/comprobante`, { responseType: 'blob' })
    const url = URL.createObjectURL(data as Blob)
    window.open(url, '_blank')
  } catch {
    error.value = t('comprobantes.errorVer')
  }
}

async function aprobar(id: string): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/pagos/${id}/aprobar`)
    await cargar()
  } catch {
    error.value = t('comprobantes.errorAprobar')
  }
}

async function rechazar(id: string): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/pagos/${id}/rechazar`)
    await cargar()
  } catch {
    error.value = t('comprobantes.errorGenerico')
  }
}

onMounted(cargar)
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('comprobantes.titulo') }}</h2>

    <p v-if="!puedeRevisar" class="text-sm text-slate-500">{{ t('comprobantes.sinPermiso') }}</p>

    <template v-else>
      <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
      <p v-if="pendientes.length === 0" class="text-sm text-slate-500">{{ t('comprobantes.vacio') }}</p>

      <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
        <li
          v-for="pago in pendientes"
          :key="pago.id"
          class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
        >
          <span>
            <span class="font-medium">{{ pago.persona }}</span>
            <span class="text-slate-500"> · {{ importe(pago) }}</span>
            <span
              class="ml-2 text-xs"
              :class="pago.comprobante ? 'text-emerald-600' : 'text-amber-600'"
            >
              {{ pago.comprobante ? t('comprobantes.subido') : t('comprobantes.sinSubir') }}
            </span>
          </span>
          <span class="flex items-center gap-2">
            <button
              v-if="pago.comprobante"
              class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
              @click="verComprobante(pago.id)"
            >
              {{ t('comprobantes.ver') }}
            </button>
            <button
              class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
              :disabled="!pago.comprobante"
              @click="aprobar(pago.id)"
            >
              {{ t('comprobantes.aprobar') }}
            </button>
            <button
              class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
              @click="rechazar(pago.id)"
            >
              {{ t('comprobantes.rechazar') }}
            </button>
          </span>
        </li>
      </ul>
    </template>
  </section>
</template>
