<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface PagoItem {
  id: string
  estado: string
  proveedor: string
  metodo: string | null
}

interface LineaItem {
  producto: string
  cantidad: number
  subtotal_minor: number
}

interface OrdenItem {
  id: string
  estado: string
  total_minor: number
  moneda: string
  comprador: string
  creada_en: string | null
  lineas: LineaItem[]
  pagos: PagoItem[]
}

const { t } = useI18n()
const auth = useAuthStore()

const ordenes = ref<OrdenItem[]>([])
const error = ref<string | null>(null)

const puedeReembolsar = computed(() => auth.puede('pagos.reembolsar'))

const estadoOrdenClase: Record<string, string> = {
  pendiente: 'bg-amber-100 text-amber-700',
  pagada: 'bg-emerald-100 text-emerald-700',
  cancelada: 'bg-slate-200 text-slate-600',
}

const estadoPagoClase: Record<string, string> = {
  pendiente: 'text-amber-600',
  aprobado: 'text-emerald-600',
  rechazado: 'text-red-600',
  reembolsado: 'text-slate-500',
}

function dinero(minor: number, moneda: string): string {
  return `${(minor / 100).toFixed(2)} ${moneda}`
}

function fecha(iso: string | null): string {
  if (iso === null) {
    return ''
  }
  return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
}

async function cargar(): Promise<void> {
  error.value = null
  try {
    const { data } = await api.get<{ data: OrdenItem[] }>('/api/v1/ordenes')
    ordenes.value = data.data
  } catch {
    error.value = t('ordenes.error')
  }
}

async function reembolsar(pago: PagoItem): Promise<void> {
  error.value = null
  if (!window.confirm(t('ordenes.confirmarReembolso'))) {
    return
  }
  try {
    await api.post(`/api/v1/pagos/${pago.id}/reembolso`)
    await cargar()
  } catch (e) {
    const codigo = axios.isAxiosError(e) ? (e.response?.data as { code?: string })?.code : undefined
    if (codigo === 'REFUND_BLOCKED_USED') {
      error.value = t('ordenes.reembolsoBloqueado')
    } else if (codigo === 'PAYMENT_NOT_REFUNDABLE') {
      error.value = t('ordenes.noReembolsable')
    } else {
      error.value = t('ordenes.error')
    }
  }
}

onMounted(cargar)
</script>

<template>
  <section class="space-y-4">
    <h2 class="text-xl font-semibold">{{ t('ordenes.titulo') }}</h2>
    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
    <p v-if="ordenes.length === 0" class="text-sm text-slate-500">{{ t('ordenes.sinOrdenes') }}</p>

    <ul v-else class="space-y-3">
      <li v-for="orden in ordenes" :key="orden.id" class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="font-medium">{{ orden.comprador }}</span>
          <span class="flex items-center gap-2">
            <span class="font-medium">{{ dinero(orden.total_minor, orden.moneda) }}</span>
            <span
              class="rounded px-2 py-0.5 text-xs font-medium"
              :class="estadoOrdenClase[orden.estado] ?? 'bg-slate-100 text-slate-600'"
            >
              {{ t('ordenes.estados.' + orden.estado) }}
            </span>
          </span>
        </div>
        <p v-if="orden.creada_en" class="mt-0.5 text-xs text-slate-400">{{ fecha(orden.creada_en) }}</p>

        <ul class="mt-2 space-y-0.5 text-xs text-slate-500">
          <li v-for="(linea, i) in orden.lineas" :key="i">
            {{ linea.cantidad }}× {{ linea.producto }} · {{ dinero(linea.subtotal_minor, orden.moneda) }}
          </li>
        </ul>

        <div class="mt-3 border-t border-slate-100 pt-2">
          <p class="text-xs font-medium text-slate-500">{{ t('ordenes.pagos') }}</p>
          <p v-if="orden.pagos.length === 0" class="text-xs text-slate-400">{{ t('ordenes.sinPagos') }}</p>
          <ul v-else class="mt-1 space-y-1">
            <li v-for="pago in orden.pagos" :key="pago.id" class="flex items-center justify-between gap-2">
              <span class="text-xs">
                <span class="capitalize">{{ pago.proveedor }}</span>
                <span v-if="pago.metodo" class="text-slate-400"> · {{ pago.metodo }}</span>
                ·
                <span :class="estadoPagoClase[pago.estado] ?? 'text-slate-500'">
                  {{ t('ordenes.estadosPago.' + pago.estado) }}
                </span>
              </span>
              <button
                v-if="puedeReembolsar && pago.estado === 'aprobado'"
                class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                @click="reembolsar(pago)"
              >
                {{ t('ordenes.reembolsar') }}
              </button>
            </li>
          </ul>
        </div>
      </li>
    </ul>
  </section>
</template>
