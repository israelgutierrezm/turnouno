<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface Oferta {
  id: string
  nombre: string
  modalidad: string
  capacidad: number | null
}

interface Nivel {
  id: string
  nombre: string
  orden: number
}

interface Actividad {
  id: string
  nombre: string
  slug: string
  niveles: Nivel[]
  ofertas: Oferta[]
}

interface ProgramaDetalle {
  id: string
  nombre: string
  slug: string
  actividades: Actividad[]
}

interface ProgramaItem {
  id: string
  nombre: string
}

const { t } = useI18n()
const auth = useAuthStore()

const programas = ref<ProgramaItem[]>([])
const programaId = ref('')
const detalle = ref<ProgramaDetalle | null>(null)
const actividadId = ref('')

const nombrePrograma = ref('')
const nombreActividad = ref('')
const nombreNivel = ref('')
const ordenNivel = ref('0')
const nombreOferta = ref('')
const modalidadOferta = ref('grupal')
const capacidadOferta = ref('')
const error = ref<string | null>(null)

const puedeGestionar = computed(() => auth.puede('catalogo.gestionar'))
const hayProgramas = computed(() => programas.value.length > 0)
const actividades = computed<Actividad[]>(() => detalle.value?.actividades ?? [])

async function cargarProgramas(): Promise<void> {
  const { data } = await api.get<{ data: ProgramaItem[] }>('/api/v1/programas')
  programas.value = data.data
  if (programaId.value === '' && programas.value.length > 0) {
    programaId.value = programas.value[0].id
  }
}

async function cargarDetalle(): Promise<void> {
  if (programaId.value === '') {
    detalle.value = null
    return
  }
  const { data } = await api.get<{ data: ProgramaDetalle }>(`/api/v1/programas/${programaId.value}`)
  detalle.value = data.data
  if (actividades.value.length > 0 && !actividades.value.some((a) => a.id === actividadId.value)) {
    actividadId.value = actividades.value[0].id
  }
}

async function crearPrograma(): Promise<void> {
  error.value = null
  try {
    const { data } = await api.post<{ data: ProgramaItem }>('/api/v1/programas', {
      nombre: nombrePrograma.value,
    })
    nombrePrograma.value = ''
    await cargarProgramas()
    programaId.value = data.data.id
    await cargarDetalle()
  } catch {
    error.value = t('catalogo.errorGenerico')
  }
}

async function agregarActividad(): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/programas/${programaId.value}/actividades`, {
      nombre: nombreActividad.value,
    })
    nombreActividad.value = ''
    await cargarDetalle()
  } catch {
    error.value = t('catalogo.errorGenerico')
  }
}

async function agregarNivel(): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/actividades/${actividadId.value}/niveles`, {
      nombre: nombreNivel.value,
      orden: Number(ordenNivel.value) || 0,
    })
    nombreNivel.value = ''
    ordenNivel.value = '0'
    await cargarDetalle()
  } catch {
    error.value = t('catalogo.errorGenerico')
  }
}

async function agregarOferta(): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/actividades/${actividadId.value}/ofertas`, {
      nombre: nombreOferta.value,
      modalidad: modalidadOferta.value,
      capacidad: capacidadOferta.value === '' ? null : Number(capacidadOferta.value),
    })
    nombreOferta.value = ''
    capacidadOferta.value = ''
    await cargarDetalle()
  } catch {
    error.value = t('catalogo.errorGenerico')
  }
}

watch(programaId, () => {
  void cargarDetalle()
})

onMounted(async () => {
  await cargarProgramas()
  await cargarDetalle()
})
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('catalogo.titulo') }}</h2>

    <form
      v-if="puedeGestionar"
      class="flex items-end gap-3 rounded-lg border border-slate-200 bg-white p-4"
      @submit.prevent="crearPrograma"
    >
      <label class="flex-1 text-sm">
        <span class="text-slate-600">{{ t('catalogo.nuevoPrograma') }}</span>
        <input
          v-model="nombrePrograma"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
        />
      </label>
      <button
        type="submit"
        class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
      >
        {{ t('catalogo.crear') }}
      </button>
    </form>

    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
    <p v-if="!hayProgramas" class="text-sm text-slate-500">{{ t('catalogo.sinProgramas') }}</p>

    <template v-else>
      <label class="block max-w-xs text-sm">
        <span class="text-slate-600">{{ t('catalogo.programa') }}</span>
        <select v-model="programaId" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2">
          <option v-for="programa in programas" :key="programa.id" :value="programa.id">
            {{ programa.nombre }}
          </option>
        </select>
      </label>

      <div v-if="detalle" class="space-y-4">
        <ul class="divide-y rounded-lg border border-slate-200 bg-white">
          <li v-for="actividad in actividades" :key="actividad.id" class="px-4 py-3 text-sm">
            <p class="font-medium">{{ actividad.nombre }}</p>
            <div class="mt-1 grid gap-1 pl-4 text-slate-600 sm:grid-cols-2">
              <div>
                <span class="text-xs uppercase text-slate-400">{{ t('catalogo.niveles') }}</span>
                <span v-if="!actividad.niveles.length"> —</span>
                <span v-for="nivel in actividad.niveles" :key="nivel.id" class="ml-1">{{ nivel.nombre }}</span>
              </div>
              <div>
                <span class="text-xs uppercase text-slate-400">{{ t('catalogo.ofertas') }}</span>
                <span v-if="!actividad.ofertas.length"> —</span>
                <span v-for="oferta in actividad.ofertas" :key="oferta.id" class="ml-1">
                  {{ oferta.nombre }} ({{ oferta.modalidad }}<template v-if="oferta.capacidad">, {{ oferta.capacidad }}</template>)
                </span>
              </div>
            </div>
          </li>
          <li v-if="!actividades.length" class="px-4 py-3 text-sm text-slate-500">—</li>
        </ul>

        <div v-if="puedeGestionar" class="grid gap-4 md:grid-cols-3">
          <form
            class="space-y-2 rounded-lg border border-slate-200 bg-white p-4"
            @submit.prevent="agregarActividad"
          >
            <h3 class="text-sm font-medium">{{ t('catalogo.nuevaActividad') }}</h3>
            <input
              v-model="nombreActividad"
              :placeholder="t('catalogo.nombre')"
              required
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            <button
              type="submit"
              class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
            >
              {{ t('catalogo.agregar') }}
            </button>
          </form>

          <template v-if="actividades.length">
            <form
              class="space-y-2 rounded-lg border border-slate-200 bg-white p-4"
              @submit.prevent="agregarNivel"
            >
              <h3 class="text-sm font-medium">{{ t('catalogo.nuevoNivel') }}</h3>
              <select
                v-model="actividadId"
                class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
              >
                <option v-for="actividad in actividades" :key="actividad.id" :value="actividad.id">
                  {{ actividad.nombre }}
                </option>
              </select>
              <input
                v-model="nombreNivel"
                :placeholder="t('catalogo.nombre')"
                required
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
              />
              <input
                v-model="ordenNivel"
                type="number"
                :placeholder="t('catalogo.orden')"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
              />
              <button
                type="submit"
                class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
              >
                {{ t('catalogo.agregar') }}
              </button>
            </form>

            <form
              class="space-y-2 rounded-lg border border-slate-200 bg-white p-4"
              @submit.prevent="agregarOferta"
            >
              <h3 class="text-sm font-medium">{{ t('catalogo.nuevaOferta') }}</h3>
              <select
                v-model="actividadId"
                class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
              >
                <option v-for="actividad in actividades" :key="actividad.id" :value="actividad.id">
                  {{ actividad.nombre }}
                </option>
              </select>
              <input
                v-model="nombreOferta"
                :placeholder="t('catalogo.nombre')"
                required
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
              />
              <select
                v-model="modalidadOferta"
                class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
              >
                <option value="grupal">{{ t('catalogo.grupal') }}</option>
                <option value="privada">{{ t('catalogo.privada') }}</option>
              </select>
              <input
                v-model="capacidadOferta"
                type="number"
                :placeholder="t('catalogo.capacidad')"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
              />
              <button
                type="submit"
                class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
              >
                {{ t('catalogo.agregar') }}
              </button>
            </form>
          </template>
        </div>
      </div>
    </template>
  </section>
</template>
