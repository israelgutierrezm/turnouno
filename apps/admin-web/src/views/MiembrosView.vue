<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface Miembro {
  id: string
  nombre: string
  apellidos: string | null
  email: string | null
  perfiles: string[]
}

const { t } = useI18n()
const auth = useAuthStore()

const miembros = ref<Miembro[]>([])
const nombre = ref('')
const apellidos = ref('')
const email = ref('')
const cargando = ref(false)
const error = ref<string | null>(null)

const puedeCrear = computed(() => auth.puede('miembros.crear'))

async function cargar(): Promise<void> {
  cargando.value = true
  try {
    const { data } = await api.get<{ data: Miembro[] }>('/api/v1/personas?perfil=miembro')
    miembros.value = data.data
  } finally {
    cargando.value = false
  }
}

async function crear(): Promise<void> {
  error.value = null
  try {
    await api.post('/api/v1/personas', {
      nombre: nombre.value,
      apellidos: apellidos.value || null,
      email: email.value || null,
      perfiles: ['miembro'],
    })
    nombre.value = ''
    apellidos.value = ''
    email.value = ''
    await cargar()
  } catch {
    error.value = t('miembros.errorCrear')
  }
}

onMounted(() => void cargar())
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('miembros.titulo') }}</h2>

    <form
      v-if="puedeCrear"
      class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-4"
      @submit.prevent="crear"
    >
      <label class="text-sm">
        <span class="text-slate-600">{{ t('miembros.nombre') }}</span>
        <input v-model="nombre" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
      </label>
      <label class="text-sm">
        <span class="text-slate-600">{{ t('miembros.apellidos') }}</span>
        <input v-model="apellidos" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
      </label>
      <label class="text-sm">
        <span class="text-slate-600">{{ t('miembros.email') }}</span>
        <input v-model="email" type="email" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
      </label>
      <button
        type="submit"
        class="self-end rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
      >
        {{ t('miembros.crear') }}
      </button>
    </form>

    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

    <div v-if="cargando" class="text-sm text-slate-500">{{ t('comun.cargando') }}</div>
    <p v-else-if="miembros.length === 0" class="text-sm text-slate-500">{{ t('miembros.vacio') }}</p>
    <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
      <li
        v-for="miembro in miembros"
        :key="miembro.id"
        class="flex items-center justify-between px-4 py-3 text-sm"
      >
        <span class="font-medium">{{ miembro.nombre }} {{ miembro.apellidos }}</span>
        <span class="text-slate-500">{{ miembro.email }}</span>
      </li>
    </ul>
  </section>
</template>
