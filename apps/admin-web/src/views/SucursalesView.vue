<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface Sucursal {
  id: string
  nombre: string
  slug: string
  zona_horaria: string | null
  estado: string
}

interface Organizacion {
  id: string
  nombre: string
}

const { t } = useI18n()
const auth = useAuthStore()

const sucursales = ref<Sucursal[]>([])
const organizaciones = ref<Organizacion[]>([])
const nombre = ref('')
const organizacionId = ref('')
const zonaHoraria = ref('America/Mexico_City')
const cargando = ref(false)
const error = ref<string | null>(null)

const puedeGestionar = computed(() => auth.puede('sucursales.gestionar'))
const hayOrganizaciones = computed(() => organizaciones.value.length > 0)

async function cargar(): Promise<void> {
  cargando.value = true
  try {
    const [resSucursales, resOrganizaciones] = await Promise.all([
      api.get<{ data: Sucursal[] }>('/api/v1/sucursales'),
      api.get<{ data: Organizacion[] }>('/api/v1/organizaciones'),
    ])
    sucursales.value = resSucursales.data.data
    organizaciones.value = resOrganizaciones.data.data
    if (organizacionId.value === '' && organizaciones.value.length > 0) {
      organizacionId.value = organizaciones.value[0].id
    }
  } finally {
    cargando.value = false
  }
}

async function crear(): Promise<void> {
  error.value = null
  try {
    await api.post('/api/v1/sucursales', {
      organizacion_id: organizacionId.value,
      nombre: nombre.value,
      zona_horaria: zonaHoraria.value,
    })
    nombre.value = ''
    await cargar()
  } catch {
    error.value = t('sucursales.errorCrear')
  }
}

onMounted(() => void cargar())
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('sucursales.titulo') }}</h2>

    <form
      v-if="puedeGestionar"
      class="space-y-3 rounded-lg border border-slate-200 bg-white p-4"
      @submit.prevent="crear"
    >
      <h3 class="text-sm font-medium">{{ t('sucursales.nueva') }}</h3>
      <p v-if="!hayOrganizaciones" class="text-sm text-amber-700">
        {{ t('sucursales.sinOrganizacion') }}
      </p>
      <template v-else>
        <div class="grid gap-3 sm:grid-cols-3">
          <label class="text-sm">
            <span class="text-slate-600">{{ t('sucursales.organizacion') }}</span>
            <select
              v-model="organizacionId"
              class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2"
            >
              <option v-for="organizacion in organizaciones" :key="organizacion.id" :value="organizacion.id">
                {{ organizacion.nombre }}
              </option>
            </select>
          </label>
          <label class="text-sm">
            <span class="text-slate-600">{{ t('sucursales.nombre') }}</span>
            <input
              v-model="nombre"
              required
              class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2"
            />
          </label>
          <label class="text-sm">
            <span class="text-slate-600">{{ t('sucursales.zonaHoraria') }}</span>
            <input
              v-model="zonaHoraria"
              class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2"
            />
          </label>
        </div>
        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
        <button
          type="submit"
          class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
        >
          {{ t('sucursales.crear') }}
        </button>
      </template>
    </form>

    <div v-if="cargando" class="text-sm text-slate-500">{{ t('comun.cargando') }}</div>
    <p v-else-if="sucursales.length === 0" class="text-sm text-slate-500">
      {{ t('sucursales.vacio') }}
    </p>
    <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
      <li
        v-for="sucursal in sucursales"
        :key="sucursal.id"
        class="flex items-center justify-between px-4 py-3 text-sm"
      >
        <span class="font-medium">{{ sucursal.nombre }}</span>
        <span class="text-slate-500">{{ sucursal.zona_horaria }} · {{ sucursal.estado }}</span>
      </li>
    </ul>
  </section>
</template>
