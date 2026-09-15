<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface Miembro {
  id: string
  nombre: string
  apellidos: string | null
  email: string | null
  tipo: string
  activo: boolean
}

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)
const puedeGestionar = computed(() => sesion.puede('miembros.gestionar'))

const tipo = ref<'miembro' | 'instructor'>('miembro')
const miembros = ref<Miembro[]>([])
const cargando = ref(true)
const error = ref<string | null>(null)
const mensaje = ref<string | null>(null)

const form = ref({ nombre: '', apellidos: '', email: '', tipo: 'miembro' })
const guardando = ref(false)

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: Miembro[] }>(`${base.value}/miembros`, {
      params: { tipo: tipo.value },
    })
    miembros.value = data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

async function crear(): Promise<void> {
  guardando.value = true
  error.value = null
  mensaje.value = null
  try {
    await api.post(`${base.value}/miembros`, {
      nombre: form.value.nombre,
      apellidos: form.value.apellidos || null,
      email: form.value.email || null,
      tipo: form.value.tipo,
    })
    mensaje.value = 'ok'
    form.value = { nombre: '', apellidos: '', email: '', tipo: tipo.value }
    // Si el nuevo miembro es del tipo que se ve, recargar la lista.
    if (form.value.tipo === tipo.value) {
      await cargar()
    }
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    guardando.value = false
  }
}

watch(tipo, () => {
  form.value.tipo = tipo.value
  void cargar()
})

onMounted(() => {
  form.value.tipo = tipo.value
  void cargar()
})
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 py-10">
    <h1 class="text-3xl font-extrabold">{{ $t('miembros.titulo') }}</h1>
    <p class="mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('miembros.subtitulo') }}</p>

    <!-- Filtro alumnos / instructores -->
    <div class="mt-6 inline-flex rounded-lg border p-1" :style="{ borderColor: 'var(--borde)' }">
      <button
        type="button"
        class="px-3 py-1.5 rounded-md text-sm font-semibold"
        :style="{
          background: tipo === 'miembro' ? 'var(--primario)' : 'transparent',
          color: tipo === 'miembro' ? 'var(--primario-contraste)' : 'var(--texto)',
        }"
        @click="tipo = 'miembro'"
      >
        {{ $t('miembros.filtroMiembros') }}
      </button>
      <button
        type="button"
        class="px-3 py-1.5 rounded-md text-sm font-semibold"
        :style="{
          background: tipo === 'instructor' ? 'var(--primario)' : 'transparent',
          color: tipo === 'instructor' ? 'var(--primario-contraste)' : 'var(--texto)',
        }"
        @click="tipo = 'instructor'"
      >
        {{ $t('miembros.filtroInstructores') }}
      </button>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-[1fr_300px]">
      <!-- Lista -->
      <div class="tu-card p-2">
        <p v-if="cargando" class="p-4 text-sm" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('comun.cargando') }}
        </p>
        <p
          v-else-if="miembros.length === 0"
          class="p-6 text-center text-sm"
          :style="{ color: 'var(--texto-suave)' }"
        >
          {{ $t('miembros.vacio') }}
        </p>
        <ul v-else>
          <li
            v-for="(m, i) in miembros"
            :key="m.id"
            class="flex items-center gap-3 p-3"
            :style="{ borderTop: i > 0 ? '1px solid var(--borde)' : 'none' }"
          >
            <span
              class="h-9 w-9 rounded-full inline-flex items-center justify-center text-sm font-bold text-white shrink-0"
              :style="{ background: 'var(--primario)' }"
              aria-hidden="true"
              >{{ m.nombre.charAt(0).toUpperCase() }}</span
            >
            <div class="min-w-0">
              <p class="font-semibold truncate">
                {{ m.nombre }} {{ m.apellidos ?? '' }}
              </p>
              <p class="text-sm truncate" :style="{ color: 'var(--texto-suave)' }">
                {{ m.email ?? $t('miembros.sinApellidos') }}
              </p>
            </div>
          </li>
        </ul>
      </div>

      <!-- Alta -->
      <div v-if="puedeGestionar" class="tu-card p-5 h-max">
        <h2 class="font-bold">{{ $t('miembros.nuevo') }}</h2>
        <form class="mt-3 space-y-3" @submit.prevent="crear">
          <div>
            <label class="tu-label" for="mn">{{ $t('miembros.nombre') }}</label>
            <input id="mn" v-model="form.nombre" class="tu-input" required />
          </div>
          <div>
            <label class="tu-label" for="ma">{{ $t('miembros.apellidos') }}</label>
            <input id="ma" v-model="form.apellidos" class="tu-input" />
          </div>
          <div>
            <label class="tu-label" for="me">{{ $t('miembros.email') }}</label>
            <input id="me" v-model="form.email" class="tu-input" type="email" />
          </div>
          <div>
            <label class="tu-label" for="mt">{{ $t('miembros.tipo') }}</label>
            <select id="mt" v-model="form.tipo" class="tu-input">
              <option value="miembro">{{ $t('miembros.tipoMiembro') }}</option>
              <option value="instructor">{{ $t('miembros.tipoInstructor') }}</option>
            </select>
          </div>
          <p v-if="mensaje" class="text-sm" :style="{ color: 'var(--exito)' }">{{ $t('miembros.creado') }}</p>
          <p v-if="error" class="text-sm" style="color: var(--error)">{{ error }}</p>
          <button class="tu-btn tu-btn-primario w-full" type="submit" :disabled="guardando">
            {{ guardando ? $t('miembros.creando') : $t('miembros.crear') }}
          </button>
        </form>
      </div>
    </div>
  </section>
</template>
