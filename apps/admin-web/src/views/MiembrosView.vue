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

interface PersonaDetalle extends Miembro {
  fecha_nacimiento: string | null
}

interface DerechoItem {
  id: string
  producto: string
  ilimitado: boolean
  saldo_creditos: number
  disponible_creditos: number
}

const { t } = useI18n()
const auth = useAuthStore()

const miembros = ref<Miembro[]>([])
const nombre = ref('')
const apellidos = ref('')
const email = ref('')
const cargando = ref(false)
const error = ref<string | null>(null)

const detalle = ref<PersonaDetalle | null>(null)
const derechos = ref<DerechoItem[]>([])
const cargandoDetalle = ref(false)

const puedeCrear = computed(() => auth.puede('miembros.crear'))
const puedeVerDerechos = computed(() => auth.puede('membresias.ver'))

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

async function verDetalle(id: string): Promise<void> {
  cargandoDetalle.value = true
  derechos.value = []
  try {
    const { data } = await api.get<{ data: PersonaDetalle }>(`/api/v1/personas/${id}`)
    detalle.value = data.data
    if (puedeVerDerechos.value) {
      const resp = await api.get<{ data: DerechoItem[] }>(`/api/v1/personas/${id}/derechos`)
      derechos.value = resp.data.data
    }
  } catch {
    error.value = t('miembros.errorDetalle')
  } finally {
    cargandoDetalle.value = false
  }
}

function cerrarDetalle(): void {
  detalle.value = null
  derechos.value = []
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

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Lista -->
      <div>
        <div v-if="cargando" class="text-sm text-slate-500">{{ t('comun.cargando') }}</div>
        <p v-else-if="miembros.length === 0" class="text-sm text-slate-500">{{ t('miembros.vacio') }}</p>
        <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
          <li v-for="miembro in miembros" :key="miembro.id">
            <button
              class="flex w-full items-center justify-between px-4 py-3 text-left text-sm hover:bg-slate-50"
              :class="detalle?.id === miembro.id ? 'bg-slate-50' : ''"
              @click="verDetalle(miembro.id)"
            >
              <span class="font-medium">{{ miembro.nombre }} {{ miembro.apellidos }}</span>
              <span class="text-slate-500">{{ miembro.email }}</span>
            </button>
          </li>
        </ul>
      </div>

      <!-- Detalle -->
      <div v-if="detalle" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="flex items-start justify-between">
          <h3 class="text-base font-semibold">{{ detalle.nombre }} {{ detalle.apellidos }}</h3>
          <button class="text-xs text-slate-500 hover:text-slate-800" @click="cerrarDetalle">
            {{ t('miembros.cerrar') }}
          </button>
        </div>
        <p v-if="cargandoDetalle" class="text-slate-500">{{ t('comun.cargando') }}</p>
        <template v-else>
          <dl class="space-y-1">
            <div class="flex justify-between gap-2">
              <dt class="text-slate-500">{{ t('miembros.email') }}</dt>
              <dd>{{ detalle.email ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt class="text-slate-500">{{ t('miembros.fechaNacimiento') }}</dt>
              <dd>{{ detalle.fecha_nacimiento ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt class="text-slate-500">{{ t('miembros.perfiles') }}</dt>
              <dd>{{ detalle.perfiles.join(', ') || '—' }}</dd>
            </div>
          </dl>

          <div v-if="puedeVerDerechos" class="border-t border-slate-100 pt-2">
            <p class="text-xs font-medium text-slate-500">{{ t('miembros.derechos') }}</p>
            <p v-if="derechos.length === 0" class="text-xs text-slate-400">{{ t('miembros.sinDerechos') }}</p>
            <ul v-else class="mt-1 space-y-1">
              <li v-for="derecho in derechos" :key="derecho.id" class="flex justify-between gap-2">
                <span>{{ derecho.producto }}</span>
                <span class="text-slate-500">
                  <template v-if="derecho.ilimitado">{{ t('miembros.ilimitado') }}</template>
                  <template v-else>{{ t('miembros.disponible') }}: {{ derecho.disponible_creditos }}</template>
                </span>
              </li>
            </ul>
          </div>
        </template>
      </div>
    </div>
  </section>
</template>
