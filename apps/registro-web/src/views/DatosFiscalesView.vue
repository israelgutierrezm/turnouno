<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import EncabezadoSeccion from '@/components/EncabezadoSeccion.vue'
import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface DatosFiscales {
  razon_social: string
  rfc: string
  regimen_fiscal: string
  codigo_postal: string
  facturapi_conectado: boolean
}

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)

const form = ref({ razon_social: '', rfc: '', regimen_fiscal: '', codigo_postal: '' })
const conectado = ref(false)
const cargado = ref(false)
const cargando = ref(true)
const guardando = ref(false)
const error = ref<string | null>(null)
const mensaje = ref<string | null>(null)

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: DatosFiscales | null }>(`${base.value}/datos-fiscales`)
    if (data.data !== null) {
      form.value = {
        razon_social: data.data.razon_social,
        rfc: data.data.rfc,
        regimen_fiscal: data.data.regimen_fiscal,
        codigo_postal: data.data.codigo_postal,
      }
      conectado.value = data.data.facturapi_conectado
      cargado.value = true
    }
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

async function guardar(): Promise<void> {
  guardando.value = true
  error.value = null
  mensaje.value = null
  try {
    const { data } = await api.put<{ data: DatosFiscales }>(`${base.value}/datos-fiscales`, form.value)
    conectado.value = data.data.facturapi_conectado
    cargado.value = true
    form.value.rfc = data.data.rfc // normalizado (mayúsculas)
    mensaje.value = 'ok'
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    guardando.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <section class="mx-auto max-w-2xl px-4 sm:px-6 py-8">
    <EncabezadoSeccion
      icono="datosFiscales"
      :titulo="$t('datosFiscales.titulo')"
      :subtitulo="$t('datosFiscales.subtitulo')"
    />

    <p v-if="cargando" class="mt-8" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>

    <div v-else class="mt-6 tu-card p-6">
      <div class="flex items-center gap-2 mb-4">
        <span class="tu-badge" :class="conectado ? 'tu-badge-exito' : ''">
          {{ conectado ? $t('datosFiscales.conectado') : $t('datosFiscales.noConectado') }}
        </span>
      </div>

      <form class="space-y-4" @submit.prevent="guardar">
        <div>
          <label class="tu-label" for="rs">{{ $t('datosFiscales.razonSocial') }}</label>
          <input id="rs" v-model="form.razon_social" class="tu-input" required />
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="tu-label" for="rfc">{{ $t('datosFiscales.rfc') }}</label>
            <input id="rfc" v-model="form.rfc" class="tu-input uppercase" required />
          </div>
          <div>
            <label class="tu-label" for="cp">{{ $t('datosFiscales.codigoPostal') }}</label>
            <input id="cp" v-model="form.codigo_postal" class="tu-input" inputmode="numeric" required />
          </div>
        </div>
        <div>
          <label class="tu-label" for="reg">{{ $t('datosFiscales.regimenFiscal') }}</label>
          <input id="reg" v-model="form.regimen_fiscal" class="tu-input" inputmode="numeric" required />
          <p class="mt-1 text-xs" :style="{ color: 'var(--texto-suave)' }">{{ $t('datosFiscales.ayudaRegimen') }}</p>
        </div>

        <p v-if="mensaje" class="text-sm" :style="{ color: 'var(--exito)' }">{{ $t('datosFiscales.guardado') }}</p>
        <p v-if="error" class="text-sm" style="color: var(--error)">{{ error }}</p>

        <button class="tu-btn tu-btn-primario" type="submit" :disabled="guardando">
          {{ guardando ? $t('datosFiscales.guardando') : $t('datosFiscales.guardar') }}
        </button>
      </form>
    </div>
  </section>
</template>
