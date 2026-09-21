<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface Resumen {
  id: string
  nombre_completo: string
  email: string | null
  tipo: string
  activo: boolean
  asistencias: number
  primera_vez: boolean
  saldo_creditos: number
  saldo_unidades: number
  membresia: { estado: string; valido_hasta: string | null }
  adeudo: boolean
  documentos_pendientes: number
  proxima_reserva: { clase: string | null; inicia_en: string; zona_horaria: string | null } | null
  alertas: string[]
}

const props = defineProps<{ personaId: string; nombre: string }>()
const emit = defineEmits<{ (e: 'cerrar'): void }>()

const sesionStore = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesionStore.slug}`)

const resumen = ref<Resumen | null>(null)
const cargando = ref(true)
const error = ref<string | null>(null)

// Chip ámbar para "por vencer"; roja para el resto de alertas.
function estiloAlerta(codigo: string): Record<string, string> {
  return codigo === 'membresia_por_vencer'
    ? { background: 'var(--aviso-suave)', color: 'var(--aviso)' }
    : { background: 'var(--error-suave)', color: 'var(--error)' }
}

function fecha(iso: string, zona: string | null): string {
  return new Intl.DateTimeFormat('es-MX', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: zona ?? undefined,
  }).format(new Date(iso))
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: Resumen }>(`${base.value}/miembros/${props.personaId}/resumen`)
    resumen.value = data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

watch(() => props.personaId, cargar, { immediate: true })
</script>

<template>
  <div class="fixed inset-0 z-50 flex justify-end">
    <div class="absolute inset-0 bg-black/40" @click="emit('cerrar')" />
    <aside
      class="relative flex h-full w-full max-w-md flex-col overflow-y-auto"
      :style="{ background: 'var(--superficie)', boxShadow: 'var(--sombra)' }"
    >
      <header class="sticky top-0 z-10 border-b px-5 py-4" :style="{ background: 'var(--superficie)', borderColor: 'var(--borde)' }">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-lg font-bold truncate">{{ resumen?.nombre_completo ?? nombre }}</p>
            <p v-if="resumen?.email" class="text-sm truncate" :style="{ color: 'var(--texto-suave)' }">{{ resumen.email }}</p>
          </div>
          <button type="button" class="tu-icono-btn shrink-0" :aria-label="$t('recepcion.panel.cerrar')" @click="emit('cerrar')">
            <span aria-hidden="true">✕</span>
          </button>
        </div>
      </header>

      <div class="flex-1 px-5 py-4">
        <p v-if="error" class="mb-3 text-sm" style="color: var(--error)">{{ error }}</p>
        <p v-if="cargando" class="text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>

        <template v-else-if="resumen">
          <!-- Alertas -->
          <div class="flex flex-wrap gap-2">
            <span v-if="resumen.alertas.length === 0" class="tu-badge tu-badge-exito">{{ $t('recepcion.miembro.sinAlertas') }}</span>
            <span v-for="a in resumen.alertas" :key="a" class="tu-badge" :style="estiloAlerta(a)">{{ $t(`recepcion.alertas.${a}`) }}</span>
          </div>

          <!-- Datos operativos -->
          <dl class="mt-4 space-y-2.5 text-sm">
            <div class="flex items-center justify-between gap-3">
              <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('recepcion.miembro.membresia') }}</dt>
              <dd class="text-right font-medium">
                {{ $t(`recepcion.membresia.${resumen.membresia.estado}`) }}
                <span v-if="resumen.membresia.valido_hasta" :style="{ color: 'var(--texto-suave)' }"> · {{ resumen.membresia.valido_hasta }}</span>
              </dd>
            </div>
            <div class="flex items-center justify-between gap-3">
              <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('recepcion.miembro.saldo') }}</dt>
              <dd class="text-right font-medium">{{ $t('recepcion.miembro.creditos', { n: resumen.saldo_creditos }) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
              <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('recepcion.miembro.proxima') }}</dt>
              <dd class="text-right font-medium">
                <template v-if="resumen.proxima_reserva">
                  {{ resumen.proxima_reserva.clase ?? '—' }}
                  <span :style="{ color: 'var(--texto-suave)' }"> · {{ fecha(resumen.proxima_reserva.inicia_en, resumen.proxima_reserva.zona_horaria) }}</span>
                </template>
                <span v-else :style="{ color: 'var(--texto-suave)' }">{{ $t('recepcion.miembro.sinProxima') }}</span>
              </dd>
            </div>
            <div class="flex items-center justify-between gap-3">
              <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('recepcion.miembro.asistencias') }}</dt>
              <dd class="text-right font-medium">
                {{ resumen.asistencias }}
                <span v-if="resumen.primera_vez" class="tu-badge tu-badge-aviso ml-1">{{ $t('agenda.roster.primeraVez') }}</span>
              </dd>
            </div>
            <div v-if="resumen.documentos_pendientes > 0" class="flex items-center justify-between gap-3">
              <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('recepcion.miembro.documentos') }}</dt>
              <dd class="text-right font-medium" :style="{ color: 'var(--aviso)' }">{{ resumen.documentos_pendientes }}</dd>
            </div>
          </dl>
        </template>
      </div>
    </aside>
  </div>
</template>
