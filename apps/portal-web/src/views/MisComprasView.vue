<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'

interface PagoItem {
  estado: string
  proveedor: string
  metodo: string | null
}

interface LineaItem {
  producto: string
  cantidad: number
}

interface OrdenItem {
  id: string
  estado: string
  total_minor: number
  moneda: string
  creada_en: string | null
  lineas: LineaItem[]
  pagos: PagoItem[]
}

const { t } = useI18n()

const ordenes = ref<OrdenItem[]>([])
const error = ref<string | null>(null)

const estadoClase: Record<string, string> = {
  pendiente: 'bg-amber-100 text-amber-700',
  pagada: 'bg-emerald-100 text-emerald-700',
  cancelada: 'bg-slate-200 text-slate-600',
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
    const { data } = await api.get<{ data: OrdenItem[] }>('/api/v1/mi/ordenes')
    ordenes.value = data.data
  } catch {
    error.value = t('compras.error')
  }
}

onMounted(cargar)
</script>

<template>
  <section class="space-y-4">
    <h1 class="text-xl font-semibold">{{ t('compras.titulo') }}</h1>
    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
    <p v-if="ordenes.length === 0" class="text-sm text-slate-500">{{ t('compras.sinCompras') }}</p>

    <ul v-else class="space-y-3">
      <li v-for="orden in ordenes" :key="orden.id" class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="font-medium">{{ dinero(orden.total_minor, orden.moneda) }}</span>
          <span
            class="rounded px-2 py-0.5 text-xs font-medium"
            :class="estadoClase[orden.estado] ?? 'bg-slate-100 text-slate-600'"
          >
            {{ t('compras.estados.' + orden.estado) }}
          </span>
        </div>
        <p v-if="orden.creada_en" class="mt-0.5 text-xs text-slate-400">{{ fecha(orden.creada_en) }}</p>
        <ul class="mt-2 space-y-0.5 text-xs text-slate-500">
          <li v-for="(linea, i) in orden.lineas" :key="i">{{ linea.cantidad }}× {{ linea.producto }}</li>
        </ul>
        <p v-if="orden.pagos.length > 0" class="mt-1 text-xs text-slate-400">
          {{ orden.pagos[0].proveedor }}
          <span v-if="orden.pagos[0].metodo"> · {{ orden.pagos[0].metodo }}</span>
          · {{ t('compras.estadosPago.' + orden.pagos[0].estado) }}
        </p>
      </li>
    </ul>
  </section>
</template>
