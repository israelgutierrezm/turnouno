<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface SucursalItem {
  id: string
  nombre: string
}

interface InstalacionItem {
  id: string
  nombre: string
}

interface RecursoItem {
  id: string
  nombre: string
  tipo: string | null
  modo: string
  capacidad: number
  estado: string
  padre: { id: string; nombre: string } | null
}

interface InstalacionDetalle {
  id: string
  nombre: string
  recursos: RecursoItem[]
}

const { t } = useI18n()
const auth = useAuthStore()

const sucursales = ref<SucursalItem[]>([])
const sucursalId = ref('')
const instalaciones = ref<InstalacionItem[]>([])
const instalacionId = ref('')
const detalle = ref<InstalacionDetalle | null>(null)

const nombreInstalacion = ref('')
const nombreRecurso = ref('')
const modoRecurso = ref('unidad')
const capacidadRecurso = ref('1')
const tipoRecurso = ref('')
const padreId = ref('')
const error = ref<string | null>(null)

const puedeGestionar = computed(() => auth.puede('recursos.gestionar'))
const haySucursales = computed(() => sucursales.value.length > 0)
const recursos = computed<RecursoItem[]>(() => detalle.value?.recursos ?? [])

async function cargarSucursales(): Promise<void> {
  const { data } = await api.get<{ data: SucursalItem[] }>('/api/v1/sucursales')
  sucursales.value = data.data
  if (sucursalId.value === '' && sucursales.value.length > 0) {
    sucursalId.value = sucursales.value[0].id
  }
}

async function cargarInstalaciones(): Promise<void> {
  if (sucursalId.value === '') {
    instalaciones.value = []
    return
  }
  const { data } = await api.get<{ data: InstalacionItem[] }>(
    `/api/v1/sucursales/${sucursalId.value}/instalaciones`,
  )
  instalaciones.value = data.data
  if (!instalaciones.value.some((instalacion) => instalacion.id === instalacionId.value)) {
    instalacionId.value = instalaciones.value[0]?.id ?? ''
  }
  await cargarDetalle()
}

async function cargarDetalle(): Promise<void> {
  if (instalacionId.value === '') {
    detalle.value = null
    return
  }
  const { data } = await api.get<{ data: InstalacionDetalle }>(
    `/api/v1/instalaciones/${instalacionId.value}`,
  )
  detalle.value = data.data
}

async function crearInstalacion(): Promise<void> {
  error.value = null
  try {
    const { data } = await api.post<{ data: InstalacionItem }>(
      `/api/v1/sucursales/${sucursalId.value}/instalaciones`,
      { nombre: nombreInstalacion.value },
    )
    nombreInstalacion.value = ''
    await cargarInstalaciones()
    instalacionId.value = data.data.id
    await cargarDetalle()
  } catch {
    error.value = t('recursos.errorGenerico')
  }
}

async function crearRecurso(): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/instalaciones/${instalacionId.value}/recursos`, {
      nombre: nombreRecurso.value,
      modo: modoRecurso.value,
      capacidad: capacidadRecurso.value === '' ? 1 : Number(capacidadRecurso.value),
      tipo: tipoRecurso.value || null,
      recurso_padre_id: padreId.value || null,
    })
    nombreRecurso.value = ''
    tipoRecurso.value = ''
    padreId.value = ''
    await cargarDetalle()
  } catch {
    error.value = t('recursos.errorGenerico')
  }
}

watch(sucursalId, () => {
  void cargarInstalaciones()
})

watch(instalacionId, () => {
  void cargarDetalle()
})

onMounted(async () => {
  await cargarSucursales()
  await cargarInstalaciones()
})
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('recursos.titulo') }}</h2>

    <p v-if="!haySucursales" class="text-sm text-slate-500">{{ t('recursos.sinSucursales') }}</p>

    <template v-else>
      <div class="flex flex-wrap gap-4">
        <label class="text-sm">
          <span class="text-slate-600">{{ t('recursos.sucursal') }}</span>
          <select v-model="sucursalId" class="mt-1 w-56 rounded-md border border-slate-300 px-2 py-2">
            <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
              {{ sucursal.nombre }}
            </option>
          </select>
        </label>
        <label v-if="instalaciones.length" class="text-sm">
          <span class="text-slate-600">{{ t('recursos.instalacion') }}</span>
          <select
            v-model="instalacionId"
            class="mt-1 w-56 rounded-md border border-slate-300 px-2 py-2"
          >
            <option v-for="instalacion in instalaciones" :key="instalacion.id" :value="instalacion.id">
              {{ instalacion.nombre }}
            </option>
          </select>
        </label>
      </div>

      <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

      <form
        v-if="puedeGestionar"
        class="flex items-end gap-3 rounded-lg border border-slate-200 bg-white p-4"
        @submit.prevent="crearInstalacion"
      >
        <label class="flex-1 text-sm">
          <span class="text-slate-600">{{ t('recursos.nuevaInstalacion') }}</span>
          <input
            v-model="nombreInstalacion"
            required
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
          />
        </label>
        <button
          type="submit"
          class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
        >
          {{ t('recursos.crear') }}
        </button>
      </form>

      <p v-if="!instalaciones.length" class="text-sm text-slate-500">
        {{ t('recursos.sinInstalaciones') }}
      </p>

      <div v-else-if="detalle" class="grid gap-4 md:grid-cols-2">
        <ul class="divide-y self-start rounded-lg border border-slate-200 bg-white">
          <li
            v-for="recurso in recursos"
            :key="recurso.id"
            class="flex items-center justify-between px-4 py-3 text-sm"
          >
            <span>
              <span v-if="recurso.padre" class="text-slate-400">{{ recurso.padre.nombre }} · </span>
              <span class="font-medium">{{ recurso.nombre }}</span>
            </span>
            <span class="text-xs text-slate-500">{{ recurso.modo }} · {{ recurso.capacidad }}</span>
          </li>
          <li v-if="!recursos.length" class="px-4 py-3 text-sm text-slate-500">—</li>
        </ul>

        <form
          v-if="puedeGestionar"
          class="space-y-2 rounded-lg border border-slate-200 bg-white p-4"
          @submit.prevent="crearRecurso"
        >
          <h3 class="text-sm font-medium">{{ t('recursos.nuevoRecurso') }}</h3>
          <input
            v-model="nombreRecurso"
            :placeholder="t('recursos.nombre')"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
          />
          <div class="grid grid-cols-2 gap-2">
            <select v-model="modoRecurso" class="rounded-md border border-slate-300 px-2 py-2 text-sm">
              <option value="unidad">{{ t('recursos.unidad') }}</option>
              <option value="pool">{{ t('recursos.pool') }}</option>
            </select>
            <input
              v-model="capacidadRecurso"
              type="number"
              :placeholder="t('recursos.capacidad')"
              class="rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
          </div>
          <input
            v-model="tipoRecurso"
            :placeholder="t('recursos.tipo')"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
          />
          <label class="block text-xs text-slate-600">
            {{ t('recursos.padre') }}
            <select v-model="padreId" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
              <option value="">{{ t('recursos.sinPadre') }}</option>
              <option v-for="recurso in recursos" :key="recurso.id" :value="recurso.id">
                {{ recurso.nombre }}
              </option>
            </select>
          </label>
          <button
            type="submit"
            class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
          >
            {{ t('recursos.agregar') }}
          </button>
        </form>
      </div>
    </template>
  </section>
</template>
