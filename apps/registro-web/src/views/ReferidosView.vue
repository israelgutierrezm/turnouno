<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import EncabezadoSeccion from '@/components/EncabezadoSeccion.vue'
import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface Referido {
  id: string
  referidor: string | null
  codigo: string
  referido: string | null
  estado: string
  recompensa: string | null
}
interface Programa {
  recompensa_tipo: string
  recompensa_valor: number
  vigencia_dias: number
  activo: boolean
}
interface Miembro {
  id: string
  nombre: string
  nombre_completo: string
}

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)
const puedeGestionar = computed(() => sesion.puede('referidos.gestionar'))

const referidos = ref<Referido[]>([])
const resumen = ref({ pendientes: 0, convertidos: 0 })
const miembros = ref<Miembro[]>([])
const cargando = ref(true)
const error = ref<string | null>(null)
const accionando = ref(false)

// Programa (valor mostrado en unidades naturales: % o moneda; se envia x100).
const prog = ref({ tipo: 'monto_fijo', valor: 100, vigencia: 90, activo: true })

// Busqueda de codigo de un miembro.
const miembroSel = ref('')
const codigoMostrado = ref<{ persona: string; codigo: string } | null>(null)

function nombreMiembro(m: Miembro): string {
  return m.nombre_completo || m.nombre
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const [r, m] = await Promise.all([
      api.get<{ data: Referido[]; programa: Programa; resumen: { pendientes: number; convertidos: number } }>(`${base.value}/referidos`),
      api.get<{ data: Miembro[] }>(`${base.value}/miembros`, { params: { tipo: 'miembro' } }),
    ])
    referidos.value = r.data.data
    resumen.value = r.data.resumen
    const p = r.data.programa
    prog.value = { tipo: p.recompensa_tipo, valor: p.recompensa_valor / 100, vigencia: p.vigencia_dias, activo: p.activo }
    miembros.value = m.data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

async function guardarPrograma(): Promise<void> {
  accionando.value = true
  error.value = null
  try {
    await api.put(`${base.value}/referidos/programa`, {
      recompensa_tipo: prog.value.tipo,
      recompensa_valor: Math.round(Number(prog.value.valor) * 100),
      vigencia_dias: Number(prog.value.vigencia) || 1,
      activo: prog.value.activo,
    })
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

async function verCodigo(): Promise<void> {
  if (miembroSel.value === '') {
    return
  }
  accionando.value = true
  error.value = null
  codigoMostrado.value = null
  try {
    const { data } = await api.get<{ data: { persona: string; codigo: string } }>(
      `${base.value}/miembros/${miembroSel.value}/codigo-referido`,
    )
    codigoMostrado.value = data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 sm:px-6 py-8">
    <EncabezadoSeccion icono="referidos" :titulo="$t('referidos.titulo')" :subtitulo="$t('referidos.subtitulo')" />

    <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="tu-card p-4 text-center">
        <div class="text-2xl font-extrabold">{{ resumen.pendientes }}</div>
        <div class="text-xs mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('referidos.resumen.pendientes') }}</div>
      </div>
      <div class="tu-card p-4 text-center">
        <div class="text-2xl font-extrabold">{{ resumen.convertidos }}</div>
        <div class="text-xs mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('referidos.resumen.convertidos') }}</div>
      </div>
    </div>

    <p v-if="error" class="mt-4 text-sm" style="color: var(--error)">{{ error }}</p>
    <p v-if="cargando" class="mt-6 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>

    <template v-else>
      <!-- Programa -->
      <div v-if="puedeGestionar" class="mt-6 tu-card p-4">
        <h2 class="font-bold">{{ $t('referidos.programa.titulo') }}</h2>
        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="guardarPrograma">
          <div>
            <label class="tu-label" for="rp-tipo">{{ $t('referidos.programa.tipo') }}</label>
            <select id="rp-tipo" v-model="prog.tipo" class="tu-input">
              <option value="monto_fijo">{{ $t('referidos.programa.tipos.monto_fijo') }}</option>
              <option value="porcentaje">{{ $t('referidos.programa.tipos.porcentaje') }}</option>
            </select>
          </div>
          <div>
            <label class="tu-label" for="rp-valor">
              {{ prog.tipo === 'porcentaje' ? $t('referidos.programa.valorPorcentaje') : $t('referidos.programa.valorMonto') }}
            </label>
            <input id="rp-valor" v-model.number="prog.valor" type="number" min="1" step="0.01" class="tu-input" />
          </div>
          <div>
            <label class="tu-label" for="rp-vig">{{ $t('referidos.programa.vigencia') }}</label>
            <input id="rp-vig" v-model.number="prog.vigencia" type="number" min="1" class="tu-input" />
          </div>
          <label class="flex items-center gap-2 text-sm sm:pt-6">
            <input v-model="prog.activo" type="checkbox" />
            {{ $t('referidos.programa.activo') }}
          </label>
          <div class="sm:col-span-2">
            <button class="tu-btn tu-btn-primario" type="submit" :disabled="accionando">{{ $t('referidos.programa.guardar') }}</button>
          </div>
        </form>
      </div>

      <!-- Código de un miembro -->
      <div class="mt-6 tu-card p-4">
        <h2 class="font-bold">{{ $t('referidos.codigo.titulo') }}</h2>
        <p class="text-sm mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('referidos.codigo.ayuda') }}</p>
        <div class="mt-3 flex flex-wrap items-end gap-2">
          <div class="grow min-w-[12rem]">
            <select v-model="miembroSel" class="tu-input">
              <option value="">{{ $t('referidos.codigo.elegir') }}</option>
              <option v-for="m in miembros" :key="m.id" :value="m.id">{{ nombreMiembro(m) }}</option>
            </select>
          </div>
          <button class="tu-btn tu-btn-fantasma" type="button" :disabled="accionando || miembroSel === ''" @click="verCodigo">
            {{ $t('referidos.codigo.ver') }}
          </button>
        </div>
        <div v-if="codigoMostrado" class="mt-3 rounded-lg p-3" :style="{ background: 'var(--fondo-suave)' }">
          <div class="text-xl font-extrabold tracking-widest">{{ codigoMostrado.codigo }}</div>
          <p class="text-xs mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('referidos.codigo.copia') }}</p>
        </div>
      </div>

      <!-- Lista de referidos -->
      <h2 class="mt-8 font-bold text-lg">{{ $t('referidos.lista.titulo') }}</h2>
      <p v-if="referidos.length === 0" class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('referidos.lista.vacio') }}</p>
      <div v-else class="mt-3 tu-card overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left" :style="{ color: 'var(--texto-suave)' }">
              <th class="px-4 py-2 font-medium">{{ $t('referidos.lista.colReferidor') }}</th>
              <th class="px-4 py-2 font-medium">{{ $t('referidos.lista.colReferido') }}</th>
              <th class="px-4 py-2 font-medium">{{ $t('referidos.lista.colEstado') }}</th>
              <th class="px-4 py-2 font-medium">{{ $t('referidos.lista.colCupon') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in referidos" :key="r.id" class="border-t" :style="{ borderColor: 'var(--borde)' }">
              <td class="px-4 py-2 font-semibold">{{ r.referidor ?? '—' }}</td>
              <td class="px-4 py-2">{{ r.referido ?? '—' }}</td>
              <td class="px-4 py-2">
                <span class="tu-badge" :class="r.estado === 'convertido' ? 'tu-badge-exito' : 'tu-badge-aviso'">
                  {{ $t(`referidos.estados.${r.estado}`) }}
                </span>
              </td>
              <td class="px-4 py-2 font-mono">{{ r.recompensa ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>
