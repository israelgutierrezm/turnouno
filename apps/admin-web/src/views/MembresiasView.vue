<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

interface Producto {
  id: string
  nombre: string
  tipo: string
  precio_minor: number
  moneda: string
  ilimitado: boolean
  creditos_incluidos: number | null
}

interface PersonaItem {
  id: string
  nombre: string
  apellidos: string | null
}

interface DerechoItem {
  id: string
  producto: string
  ilimitado: boolean
  saldo_unidades: number
  saldo_creditos: number
  disponible_unidades: number
  disponible_creditos: number
}

const { t } = useI18n()
const auth = useAuthStore()

const tiposDisponibles = ['membresia', 'paquete', 'pase_dia', 'sesion_individual', 'add_on', 'taller']

const productos = ref<Producto[]>([])
const personas = ref<PersonaItem[]>([])
const derechos = ref<DerechoItem[]>([])

const personaId = ref('')
const productoVenta = ref('')
const consumos = ref<Record<string, string>>({})

const nombreProd = ref('')
const tipoProd = ref('membresia')
const precioPesos = ref('')
const monedaProd = ref('MXN')
const ilimitadoProd = ref(false)
const creditosProd = ref('')
const error = ref<string | null>(null)

const puedeGestionarProductos = computed(() => auth.puede('productos.gestionar'))
const puedeVender = computed(() => auth.puede('membresias.gestionar'))
const hayProductos = computed(() => productos.value.length > 0)
const hayPersonas = computed(() => personas.value.length > 0)

function precioTexto(producto: Producto): string {
  return `${(producto.precio_minor / 100).toFixed(2)} ${producto.moneda}`
}

async function cargarProductos(): Promise<void> {
  const { data } = await api.get<{ data: Producto[] }>('/api/v1/productos')
  productos.value = data.data
  if (productoVenta.value === '' && productos.value.length > 0) {
    productoVenta.value = productos.value[0].id
  }
}

async function cargarPersonas(): Promise<void> {
  const { data } = await api.get<{ data: PersonaItem[] }>('/api/v1/personas')
  personas.value = data.data
  if (personaId.value === '' && personas.value.length > 0) {
    personaId.value = personas.value[0].id
  }
}

async function cargarDerechos(): Promise<void> {
  if (personaId.value === '') {
    derechos.value = []
    return
  }
  const { data } = await api.get<{ data: DerechoItem[] }>(
    `/api/v1/personas/${personaId.value}/derechos`,
  )
  derechos.value = data.data
}

async function crearProducto(): Promise<void> {
  error.value = null
  try {
    await api.post('/api/v1/productos', {
      nombre: nombreProd.value,
      tipo: tipoProd.value,
      precio_minor: Math.round((Number(precioPesos.value) || 0) * 100),
      moneda: monedaProd.value,
      ilimitado: ilimitadoProd.value,
      creditos_incluidos: ilimitadoProd.value ? null : Math.round((Number(creditosProd.value) || 0) * 1000),
    })
    nombreProd.value = ''
    precioPesos.value = ''
    creditosProd.value = ''
    ilimitadoProd.value = false
    await cargarProductos()
  } catch {
    error.value = t('membresias.errorGenerico')
  }
}

async function vender(): Promise<void> {
  error.value = null
  try {
    await api.post(`/api/v1/personas/${personaId.value}/acuerdos`, {
      producto_id: productoVenta.value,
    })
    await cargarDerechos()
  } catch {
    error.value = t('membresias.errorGenerico')
  }
}

async function consumir(derechoId: string): Promise<void> {
  error.value = null
  const creditos = Number(consumos.value[derechoId])
  if (!creditos || creditos <= 0) {
    return
  }
  try {
    await api.post(`/api/v1/derechos/${derechoId}/consumos`, {
      unidades: Math.round(creditos * 1000),
    })
    consumos.value[derechoId] = ''
    await cargarDerechos()
  } catch {
    error.value = t('membresias.errorGenerico')
  }
}

watch(personaId, () => {
  void cargarDerechos()
})

onMounted(async () => {
  await Promise.all([cargarProductos(), cargarPersonas()])
  await cargarDerechos()
})
</script>

<template>
  <section class="space-y-6">
    <h2 class="text-xl font-semibold">{{ t('membresias.titulo') }}</h2>

    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Productos -->
      <div class="space-y-3">
        <h3 class="text-sm font-medium text-slate-700">{{ t('membresias.productos') }}</h3>

        <form
          v-if="puedeGestionarProductos"
          class="space-y-2 rounded-lg border border-slate-200 bg-white p-4"
          @submit.prevent="crearProducto"
        >
          <h4 class="text-sm font-medium">{{ t('membresias.nuevoProducto') }}</h4>
          <input
            v-model="nombreProd"
            :placeholder="t('membresias.nombre')"
            required
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
          />
          <div class="grid grid-cols-2 gap-2">
            <select v-model="tipoProd" class="rounded-md border border-slate-300 px-2 py-2 text-sm">
              <option v-for="tipo in tiposDisponibles" :key="tipo" :value="tipo">
                {{ t('membresias.tipos.' + tipo) }}
              </option>
            </select>
            <input
              v-model="precioPesos"
              type="number"
              step="0.01"
              :placeholder="t('membresias.precio')"
              class="rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
          </div>
          <div class="grid grid-cols-2 items-center gap-2">
            <label class="flex items-center gap-2 text-sm text-slate-600">
              <input v-model="ilimitadoProd" type="checkbox" />
              {{ t('membresias.ilimitado') }}
            </label>
            <input
              v-model="creditosProd"
              type="number"
              :disabled="ilimitadoProd"
              :placeholder="t('membresias.creditos')"
              class="rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
            />
          </div>
          <button
            type="submit"
            class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
          >
            {{ t('membresias.crear') }}
          </button>
        </form>

        <p v-if="!hayProductos" class="text-sm text-slate-500">{{ t('membresias.sinProductos') }}</p>
        <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
          <li
            v-for="producto in productos"
            :key="producto.id"
            class="flex items-center justify-between px-4 py-3 text-sm"
          >
            <span>
              <span class="font-medium">{{ producto.nombre }}</span>
              <span class="text-slate-400"> · {{ t('membresias.tipos.' + producto.tipo) }}</span>
            </span>
            <span class="text-slate-500">
              {{ precioTexto(producto) }} ·
              {{ producto.ilimitado ? t('membresias.ilimitado') : (producto.creditos_incluidos ?? 0) / 1000 }}
            </span>
          </li>
        </ul>
      </div>

      <!-- Venta y derechos -->
      <div class="space-y-3">
        <h3 class="text-sm font-medium text-slate-700">{{ t('membresias.vender') }}</h3>
        <p v-if="!hayPersonas" class="text-sm text-slate-500">{{ t('membresias.sinPersonas') }}</p>

        <template v-else>
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="grid gap-2 sm:grid-cols-2">
              <label class="text-sm">
                <span class="text-slate-600">{{ t('membresias.persona') }}</span>
                <select v-model="personaId" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                  <option v-for="persona in personas" :key="persona.id" :value="persona.id">
                    {{ persona.nombre }} {{ persona.apellidos }}
                  </option>
                </select>
              </label>
              <label class="text-sm">
                <span class="text-slate-600">{{ t('membresias.producto') }}</span>
                <select v-model="productoVenta" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                  <option v-for="producto in productos" :key="producto.id" :value="producto.id">
                    {{ producto.nombre }}
                  </option>
                </select>
              </label>
            </div>
            <button
              v-if="puedeVender && hayProductos"
              class="mt-3 rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
              @click="vender"
            >
              {{ t('membresias.venderBtn') }}
            </button>
          </div>

          <h3 class="pt-2 text-sm font-medium text-slate-700">{{ t('membresias.derechos') }}</h3>
          <p v-if="derechos.length === 0" class="text-sm text-slate-500">
            {{ t('membresias.sinDerechos') }}
          </p>
          <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
            <li v-for="derecho in derechos" :key="derecho.id" class="space-y-2 px-4 py-3 text-sm">
              <div class="flex items-center justify-between">
                <span class="font-medium">{{ derecho.producto }}</span>
                <span class="text-slate-500">
                  <template v-if="derecho.ilimitado">{{ t('membresias.ilimitado') }}</template>
                  <template v-else>
                    {{ t('membresias.saldo') }}: {{ derecho.saldo_creditos }} ·
                    {{ t('membresias.disponible') }}: {{ derecho.disponible_creditos }}
                  </template>
                </span>
              </div>
              <form
                v-if="puedeVender && !derecho.ilimitado"
                class="flex items-center gap-2"
                @submit.prevent="consumir(derecho.id)"
              >
                <input
                  v-model="consumos[derecho.id]"
                  type="number"
                  step="0.001"
                  :placeholder="t('membresias.creditos')"
                  class="w-24 rounded-md border border-slate-300 px-2 py-1 text-xs"
                />
                <button
                  type="submit"
                  class="rounded-md bg-slate-700 px-2 py-1 text-xs font-medium text-white hover:bg-slate-600"
                >
                  {{ t('membresias.consumir') }}
                </button>
              </form>
            </li>
          </ul>
        </template>
      </div>
    </div>
  </section>
</template>
