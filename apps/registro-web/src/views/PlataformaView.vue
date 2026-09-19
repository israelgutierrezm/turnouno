<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import TablaDatos from '@/components/TablaDatos.vue'

interface Estudio {
  slug: string
  nombre: string
  estado: string
  estado_facturacion: string
  publicado: boolean
  pais: string | null
  ciudad: string | null
  creado_en: string | null
}

const { t } = useI18n()
const CLAVE_TOKEN = 'tu.plataforma.token'
const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'
const cliente = axios.create({ baseURL: apiUrl, headers: { Accept: 'application/json' } })

const token = ref<string>(leer())
const tokenInput = ref('')
const autenticado = ref(false)
const cargando = ref(false)
const error = ref<string | null>(null)

const estudios = ref<Estudio[]>([])
const facturapiConfigurada = ref(false)
const llaveInput = ref('')
const guardando = ref(false)
const mensaje = ref<string | null>(null)

const columnas = computed(() => [
  { clave: 'slug', etiqueta: t('plataforma.estudios.colSlug') },
  { clave: 'nombre', etiqueta: t('plataforma.estudios.colNombre') },
  { clave: 'estado', etiqueta: t('plataforma.estudios.colEstado') },
  { clave: 'estado_facturacion', etiqueta: t('plataforma.estudios.colFacturacion') },
  { clave: 'ciudad', etiqueta: t('plataforma.estudios.colCiudad') },
])

function encabezados(): { headers: Record<string, string> } {
  return { headers: { Authorization: `Bearer ${token.value}` } }
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const [est, cfg] = await Promise.all([
      cliente.get<{ data: Estudio[] }>('/api/v1/plataforma/estudios', encabezados()),
      cliente.get<{ data: { facturapi_configurada: boolean } }>('/api/v1/plataforma/configuracion', encabezados()),
    ])
    estudios.value = est.data.data
    facturapiConfigurada.value = cfg.data.data.facturapi_configurada
    autenticado.value = true
  } catch {
    autenticado.value = false
    error.value = t('plataforma.tokenInvalido')
    token.value = ''
    borrar()
  } finally {
    cargando.value = false
  }
}

function entrar(): void {
  token.value = tokenInput.value.trim()
  guardar(token.value)
  void cargar()
}

function salir(): void {
  token.value = ''
  autenticado.value = false
  estudios.value = []
  borrar()
}

async function guardarLlave(): Promise<void> {
  guardando.value = true
  mensaje.value = null
  error.value = null
  try {
    const { data } = await cliente.put<{ data: { facturapi_configurada: boolean } }>(
      '/api/v1/plataforma/configuracion',
      { facturapi_llave: llaveInput.value || null },
      encabezados(),
    )
    facturapiConfigurada.value = data.data.facturapi_configurada
    llaveInput.value = ''
    mensaje.value = 'ok'
  } catch {
    error.value = t('plataforma.tokenInvalido')
  } finally {
    guardando.value = false
  }
}

onMounted(() => {
  if (token.value !== '') {
    void cargar()
  }
})

function leer(): string {
  try {
    return localStorage.getItem(CLAVE_TOKEN) ?? ''
  } catch {
    return ''
  }
}
function guardar(v: string): void {
  try {
    localStorage.setItem(CLAVE_TOKEN, v)
  } catch {
    // sin localStorage: se mantiene solo en memoria
  }
}
function borrar(): void {
  try {
    localStorage.removeItem(CLAVE_TOKEN)
  } catch {
    // ignora
  }
}
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 sm:px-6 py-10">
    <!-- Puerta por token -->
    <div v-if="!autenticado" class="mx-auto max-w-sm tu-card p-6">
      <h1 class="font-bold text-xl">{{ $t('plataforma.titulo') }}</h1>
      <p class="mt-1 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('plataforma.tokenAyuda') }}</p>
      <form class="mt-4 space-y-3" @submit.prevent="entrar">
        <div>
          <label class="tu-label" for="tk">{{ $t('plataforma.token') }}</label>
          <input id="tk" v-model="tokenInput" class="tu-input" type="password" required />
        </div>
        <p v-if="error" class="text-sm" style="color: var(--error)">{{ error }}</p>
        <button class="tu-btn tu-btn-primario w-full" type="submit" :disabled="cargando">
          {{ $t('plataforma.entrar') }}
        </button>
      </form>
    </div>

    <template v-else>
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="font-bold text-2xl">{{ $t('plataforma.titulo') }}</h1>
          <p class="text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('plataforma.subtitulo') }}</p>
        </div>
        <button class="tu-btn tu-btn-fantasma" type="button" @click="salir">{{ $t('plataforma.salir') }}</button>
      </div>

      <!-- Cuenta FacturAPI -->
      <div class="mt-6 tu-card p-6">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="font-bold">{{ $t('plataforma.facturapi.titulo') }}</h2>
            <p class="text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('plataforma.facturapi.subtitulo') }}</p>
          </div>
          <span class="tu-badge" :class="facturapiConfigurada ? 'tu-badge-exito' : ''">
            {{ facturapiConfigurada ? $t('plataforma.facturapi.configurada') : $t('plataforma.facturapi.noConfigurada') }}
          </span>
        </div>
        <form class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-end" @submit.prevent="guardarLlave">
          <div class="flex-1">
            <label class="tu-label" for="lk">{{ $t('plataforma.facturapi.llave') }}</label>
            <input id="lk" v-model="llaveInput" class="tu-input" type="password" placeholder="sk_live_…" />
          </div>
          <button class="tu-btn tu-btn-primario" type="submit" :disabled="guardando">
            {{ guardando ? $t('plataforma.facturapi.guardando') : $t('plataforma.facturapi.guardar') }}
          </button>
        </form>
        <p class="mt-2 text-xs" :style="{ color: 'var(--texto-suave)' }">{{ $t('plataforma.facturapi.ayuda') }}</p>
        <p v-if="mensaje" class="mt-2 text-sm" :style="{ color: 'var(--exito)' }">
          {{ $t('plataforma.facturapi.guardado') }}
        </p>
      </div>

      <!-- Estudios -->
      <h2 class="mt-8 font-bold text-lg">{{ $t('plataforma.estudios.titulo') }} · {{ estudios.length }}</h2>
      <p v-if="cargando" class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>
      <TablaDatos
        v-else
        class="mt-3"
        :columnas="columnas"
        :filas="estudios"
        :buscar-en="['slug', 'nombre', 'ciudad']"
        :vacio="$t('plataforma.estudios.vacio')"
      >
        <template #col-slug="{ valor }">
          <span class="font-mono text-sm">{{ valor }}</span>
        </template>
        <template #col-ciudad="{ fila }">
          <span :style="{ color: 'var(--texto-suave)' }">{{ (fila as Estudio).ciudad ?? '—' }}</span>
        </template>
      </TablaDatos>
    </template>
  </section>
</template>
