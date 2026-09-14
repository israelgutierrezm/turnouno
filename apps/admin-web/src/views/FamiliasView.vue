<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface HogarItem {
  id: string
  nombre: string
}

interface PersonaFamilia {
  id: string
  nombre: string
  apellidos: string | null
  perfiles: string[]
  dependientes: Array<{ id: string; nombre: string }>
}

interface Familia {
  id: string
  nombre: string
  personas: PersonaFamilia[]
}

const { t } = useI18n()
const auth = useAuthStore()

const hogares = ref<HogarItem[]>([])
const hogarId = ref('')
const familia = ref<Familia | null>(null)

const nombreHogar = ref('')
const nombrePersona = ref('')
const apellidosPersona = ref('')
const tutorId = ref('')
const nombreDependiente = ref('')
const parentesco = ref('')
const error = ref<string | null>(null)

const puedeGestionar = computed(() => auth.puede('miembros.crear'))
const hayHogares = computed(() => hogares.value.length > 0)

async function cargarHogares(): Promise<void> {
  const { data } = await api.get<{ data: HogarItem[] }>('/api/v1/hogares')
  hogares.value = data.data
  if (hogarId.value === '' && hogares.value.length > 0) {
    hogarId.value = hogares.value[0].id
  }
}

async function cargarFamilia(): Promise<void> {
  if (hogarId.value === '') {
    familia.value = null
    return
  }
  const { data } = await api.get<{ data: Familia }>(`/api/v1/hogares/${hogarId.value}`)
  familia.value = data.data
  if (tutorId.value === '' && familia.value.personas.length > 0) {
    tutorId.value = familia.value.personas[0].id
  }
}

async function crearHogar(): Promise<void> {
  error.value = null
  try {
    const { data } = await api.post<{ data: HogarItem }>('/api/v1/hogares', {
      nombre: nombreHogar.value,
    })
    nombreHogar.value = ''
    await cargarHogares()
    hogarId.value = data.data.id
    await cargarFamilia()
  } catch {
    error.value = t('familias.errorGenerico')
  }
}

async function agregarPersona(): Promise<void> {
  error.value = null
  try {
    await api.post('/api/v1/personas', {
      nombre: nombrePersona.value,
      apellidos: apellidosPersona.value || null,
      hogar_id: hogarId.value,
      perfiles: ['tutor'],
    })
    nombrePersona.value = ''
    apellidosPersona.value = ''
    await cargarFamilia()
  } catch {
    error.value = t('familias.errorGenerico')
  }
}

async function agregarDependiente(): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/personas/${tutorId.value}/dependientes`, {
      nombre: nombreDependiente.value,
      parentesco: parentesco.value || null,
    })
    nombreDependiente.value = ''
    parentesco.value = ''
    await cargarFamilia()
  } catch {
    error.value = t('familias.errorGenerico')
  }
}

watch(hogarId, () => {
  void cargarFamilia()
})

onMounted(async () => {
  await cargarHogares()
  await cargarFamilia()
})
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('familias.titulo') }}</h2>

    <form
      v-if="puedeGestionar"
      class="flex items-end gap-3 rounded-lg border border-slate-200 bg-white p-4"
      @submit.prevent="crearHogar"
    >
      <label class="flex-1 text-sm">
        <span class="text-slate-600">{{ t('familias.nombreHogar') }}</span>
        <input
          v-model="nombreHogar"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
        />
      </label>
      <button
        type="submit"
        class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
      >
        {{ t('familias.crearHogar') }}
      </button>
    </form>

    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
    <p v-if="!hayHogares" class="text-sm text-slate-500">{{ t('familias.sinHogares') }}</p>

    <template v-else>
      <label class="block max-w-xs text-sm">
        <span class="text-slate-600">{{ t('familias.hogar') }}</span>
        <select v-model="hogarId" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2">
          <option v-for="hogar in hogares" :key="hogar.id" :value="hogar.id">{{ hogar.nombre }}</option>
        </select>
      </label>

      <div v-if="familia" class="space-y-4">
        <p v-if="familia.personas.length === 0" class="text-sm text-slate-500">
          {{ t('familias.vacio') }}
        </p>
        <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
          <li v-for="persona in familia.personas" :key="persona.id" class="px-4 py-3 text-sm">
            <div class="flex items-center justify-between">
              <span class="font-medium">{{ persona.nombre }} {{ persona.apellidos }}</span>
              <span class="text-xs text-slate-500">{{ persona.perfiles.join(', ') }}</span>
            </div>
            <ul v-if="persona.dependientes.length" class="mt-2 space-y-1 pl-4">
              <li
                v-for="dependiente in persona.dependientes"
                :key="dependiente.id"
                class="text-slate-600"
              >
                ↳ {{ dependiente.nombre }}
              </li>
            </ul>
          </li>
        </ul>

        <div v-if="puedeGestionar" class="grid gap-4 sm:grid-cols-2">
          <form
            class="space-y-2 rounded-lg border border-slate-200 bg-white p-4"
            @submit.prevent="agregarPersona"
          >
            <h3 class="text-sm font-medium">{{ t('familias.agregarPersona') }}</h3>
            <input
              v-model="nombrePersona"
              :placeholder="t('familias.nombre')"
              required
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            <input
              v-model="apellidosPersona"
              :placeholder="t('familias.apellidos')"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            <button
              type="submit"
              class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
            >
              {{ t('familias.agregar') }}
            </button>
          </form>

          <form
            v-if="familia.personas.length"
            class="space-y-2 rounded-lg border border-slate-200 bg-white p-4"
            @submit.prevent="agregarDependiente"
          >
            <h3 class="text-sm font-medium">{{ t('familias.agregarDependiente') }}</h3>
            <label class="block text-xs text-slate-600">
              {{ t('familias.tutor') }}
              <select
                v-model="tutorId"
                class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
              >
                <option v-for="persona in familia.personas" :key="persona.id" :value="persona.id">
                  {{ persona.nombre }} {{ persona.apellidos }}
                </option>
              </select>
            </label>
            <input
              v-model="nombreDependiente"
              :placeholder="t('familias.nombreDependiente')"
              required
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            <input
              v-model="parentesco"
              :placeholder="t('familias.parentesco')"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            <button
              type="submit"
              class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
            >
              {{ t('familias.registrar') }}
            </button>
          </form>
        </div>
      </div>
    </template>
  </section>
</template>
