<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface PasarelaConfig {
  proveedor: string
  activa: boolean
  modo: string
  llaves_configuradas: string[]
}

// Llaves esperadas por proveedor (los valores nunca vuelven del backend).
const definicion: Record<string, string[]> = {
  stripe: ['public_key', 'secret_key', 'webhook_secret'],
  openpay: ['merchant_id', 'private_key', 'public_key'],
  mercadopago: ['public_key', 'access_token'],
  ventanilla: ['instrucciones'],
}

const { t } = useI18n()
const auth = useAuthStore()

const pasarelas = ref<PasarelaConfig[]>([])
const activa = reactive<Record<string, boolean>>({})
const modo = reactive<Record<string, string>>({})
const llaves = reactive<Record<string, Record<string, string>>>({})
const guardado = ref<string | null>(null)
const error = ref<string | null>(null)

const puedeConfigurar = computed(() => auth.puede('pagos.configurar'))

function llavesDe(proveedor: string): string[] {
  return definicion[proveedor] ?? []
}

async function cargar(): Promise<void> {
  const { data } = await api.get<{ data: PasarelaConfig[] }>('/api/v1/pasarelas')
  pasarelas.value = data.data
  for (const p of data.data) {
    activa[p.proveedor] = p.activa
    modo[p.proveedor] = p.modo
    llaves[p.proveedor] = {}
    for (const llave of llavesDe(p.proveedor)) {
      llaves[p.proveedor][llave] = ''
    }
  }
}

function estaConfigurada(proveedor: string, llave: string): boolean {
  return pasarelas.value.find((p) => p.proveedor === proveedor)?.llaves_configuradas.includes(llave) ?? false
}

async function guardar(proveedor: string): Promise<void> {
  error.value = null
  guardado.value = null
  // Solo se envían las llaves con valor nuevo (las vacías conservan lo guardado).
  const credenciales: Record<string, string> = {}
  for (const [llave, valor] of Object.entries(llaves[proveedor] ?? {})) {
    if (valor.trim() !== '') {
      credenciales[llave] = valor.trim()
    }
  }
  try {
    await api.put(`/api/v1/pasarelas/${proveedor}`, {
      activa: activa[proveedor] ?? false,
      modo: modo[proveedor] ?? 'test',
      credenciales,
    })
    for (const llave of Object.keys(llaves[proveedor] ?? {})) {
      llaves[proveedor][llave] = ''
    }
    await cargar()
    guardado.value = proveedor
  } catch {
    error.value = t('pasarelas.errorGuardar')
  }
}

onMounted(cargar)
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('pasarelas.titulo') }}</h2>

    <p v-if="!puedeConfigurar" class="text-sm text-slate-500">{{ t('pasarelas.sinPermiso') }}</p>

    <template v-else>
      <p class="text-sm text-slate-500">{{ t('pasarelas.ayuda') }}</p>
      <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

      <div class="space-y-4">
        <form
          v-for="pasarela in pasarelas"
          :key="pasarela.proveedor"
          class="space-y-3 rounded-lg border border-slate-200 bg-white p-4"
          @submit.prevent="guardar(pasarela.proveedor)"
        >
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold capitalize">{{ pasarela.proveedor }}</h3>
            <span
              v-if="guardado === pasarela.proveedor"
              class="text-xs font-medium text-emerald-600"
            >
              {{ t('pasarelas.guardado') }}
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-sm text-slate-700">
              <input v-model="activa[pasarela.proveedor]" type="checkbox" />
              {{ t('pasarelas.activa') }}
            </label>
            <label class="text-sm">
              <span class="text-slate-600">{{ t('pasarelas.modo') }}</span>
              <select
                v-model="modo[pasarela.proveedor]"
                class="ml-2 rounded-md border border-slate-300 px-2 py-1 text-sm"
              >
                <option value="test">{{ t('pasarelas.test') }}</option>
                <option value="live">{{ t('pasarelas.live') }}</option>
              </select>
            </label>
          </div>

          <div v-if="llavesDe(pasarela.proveedor).length" class="grid gap-2 sm:grid-cols-2">
            <label
              v-for="llave in llavesDe(pasarela.proveedor)"
              :key="llave"
              class="text-sm"
            >
              <span class="text-slate-600">
                {{ llave }}
                <span v-if="estaConfigurada(pasarela.proveedor, llave)" class="text-emerald-600">✓</span>
              </span>
              <input
                v-model="llaves[pasarela.proveedor][llave]"
                type="password"
                autocomplete="off"
                :placeholder="estaConfigurada(pasarela.proveedor, llave) ? t('pasarelas.configurada') : ''"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
              />
            </label>
          </div>

          <button
            type="submit"
            class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
          >
            {{ t('pasarelas.guardar') }}
          </button>
        </form>
      </div>
    </template>
  </section>
</template>
