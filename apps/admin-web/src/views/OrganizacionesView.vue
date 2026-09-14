<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface Organizacion {
  id: string
  nombre: string
  slug: string
}

const { t } = useI18n()
const auth = useAuthStore()

const organizaciones = ref<Organizacion[]>([])
const nombre = ref('')
const cargando = ref(false)
const error = ref<string | null>(null)

const puedeGestionar = computed(() => auth.puede('organizaciones.gestionar'))

async function cargar(): Promise<void> {
  cargando.value = true
  try {
    const { data } = await api.get<{ data: Organizacion[] }>('/api/v1/organizaciones')
    organizaciones.value = data.data
  } finally {
    cargando.value = false
  }
}

async function crear(): Promise<void> {
  error.value = null
  try {
    await api.post('/api/v1/organizaciones', { nombre: nombre.value })
    nombre.value = ''
    await cargar()
  } catch {
    error.value = t('organizaciones.errorCrear')
  }
}

onMounted(() => void cargar())
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('organizaciones.titulo') }}</h2>

    <form
      v-if="puedeGestionar"
      class="flex items-end gap-3 rounded-lg border border-slate-200 bg-white p-4"
      @submit.prevent="crear"
    >
      <label class="flex-1 text-sm">
        <span class="text-slate-600">{{ t('organizaciones.nombre') }}</span>
        <input
          v-model="nombre"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
        />
      </label>
      <button
        type="submit"
        class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
      >
        {{ t('organizaciones.crear') }}
      </button>
    </form>

    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

    <div v-if="cargando" class="text-sm text-slate-500">{{ t('comun.cargando') }}</div>
    <p v-else-if="organizaciones.length === 0" class="text-sm text-slate-500">
      {{ t('organizaciones.vacio') }}
    </p>
    <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
      <li
        v-for="organizacion in organizaciones"
        :key="organizacion.id"
        class="px-4 py-3 text-sm font-medium"
      >
        {{ organizacion.nombre }}
      </li>
    </ul>
  </section>
</template>
