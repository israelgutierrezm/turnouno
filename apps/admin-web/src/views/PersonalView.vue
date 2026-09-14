<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface Sucursal {
  id: string
  nombre: string
}

interface Usuario {
  id: string
  nombre: string
}

interface Asignacion {
  id: string
  usuario: { id: string; nombre: string }
  rol: string
}

const { t } = useI18n()
const auth = useAuthStore()

const rolesDisponibles = ['propietario', 'gerente-sucursal', 'recepcionista', 'miembro']

const sucursales = ref<Sucursal[]>([])
const usuarios = ref<Usuario[]>([])
const personal = ref<Asignacion[]>([])
const sucursalId = ref('')
const usuarioId = ref('')
const rol = ref('recepcionista')
const error = ref<string | null>(null)

const puedeGestionar = computed(() => auth.puede('personal.gestionar'))
const haySucursales = computed(() => sucursales.value.length > 0)

async function cargarBase(): Promise<void> {
  const [resSucursales, resUsuarios] = await Promise.all([
    api.get<{ data: Sucursal[] }>('/api/v1/sucursales'),
    api.get<{ data: Usuario[] }>('/api/v1/usuarios'),
  ])
  sucursales.value = resSucursales.data.data
  usuarios.value = resUsuarios.data.data

  if (sucursalId.value === '' && sucursales.value.length > 0) {
    sucursalId.value = sucursales.value[0].id
  }
  if (usuarioId.value === '' && usuarios.value.length > 0) {
    usuarioId.value = usuarios.value[0].id
  }
}

async function cargarPersonal(): Promise<void> {
  if (sucursalId.value === '') {
    personal.value = []
    return
  }
  const { data } = await api.get<{ data: Asignacion[] }>(
    `/api/v1/sucursales/${sucursalId.value}/personal`,
  )
  personal.value = data.data
}

async function asignar(): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/sucursales/${sucursalId.value}/personal`, {
      user_id: usuarioId.value,
      rol: rol.value,
    })
    await cargarPersonal()
  } catch {
    error.value = t('personal.errorAsignar')
  }
}

watch(sucursalId, () => {
  void cargarPersonal()
})

onMounted(async () => {
  if (!puedeGestionar.value) {
    return
  }
  await cargarBase()
  await cargarPersonal()
})
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('personal.titulo') }}</h2>

    <p v-if="!puedeGestionar" class="text-sm text-amber-700">{{ t('personal.sinPermiso') }}</p>
    <p v-else-if="!haySucursales" class="text-sm text-amber-700">
      {{ t('personal.sinSucursales') }}
    </p>

    <template v-else>
      <label class="block max-w-xs text-sm">
        <span class="text-slate-600">{{ t('personal.sucursal') }}</span>
        <select
          v-model="sucursalId"
          class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2"
        >
          <option v-for="sucursal in sucursales" :key="sucursal.id" :value="sucursal.id">
            {{ sucursal.nombre }}
          </option>
        </select>
      </label>

      <form
        class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4"
        @submit.prevent="asignar"
      >
        <label class="text-sm">
          <span class="text-slate-600">{{ t('personal.usuario') }}</span>
          <select
            v-model="usuarioId"
            class="mt-1 w-56 rounded-md border border-slate-300 px-2 py-2"
          >
            <option v-for="usuario in usuarios" :key="usuario.id" :value="usuario.id">
              {{ usuario.nombre }}
            </option>
          </select>
        </label>
        <label class="text-sm">
          <span class="text-slate-600">{{ t('personal.rol') }}</span>
          <select v-model="rol" class="mt-1 w-48 rounded-md border border-slate-300 px-2 py-2">
            <option v-for="nombreRol in rolesDisponibles" :key="nombreRol" :value="nombreRol">
              {{ nombreRol }}
            </option>
          </select>
        </label>
        <button
          type="submit"
          class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
        >
          {{ t('personal.asignar') }}
        </button>
      </form>

      <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

      <p v-if="personal.length === 0" class="text-sm text-slate-500">{{ t('personal.vacio') }}</p>
      <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
        <li
          v-for="asignacion in personal"
          :key="asignacion.id"
          class="flex items-center justify-between px-4 py-3 text-sm"
        >
          <span class="font-medium">{{ asignacion.usuario.nombre }}</span>
          <span class="text-slate-500">{{ asignacion.rol }}</span>
        </li>
      </ul>
    </template>
  </section>
</template>
