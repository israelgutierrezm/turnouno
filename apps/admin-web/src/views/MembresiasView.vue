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
  politica_reset: string
  unidades_por_ciclo: number | null
  politica_rollover: string
  rollover_max: number | null
  actividad: string | null
  sucursal: string | null
}

interface Actividad {
  id: string
  nombre: string
}

interface Sucursal {
  id: string
  nombre: string
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
const proveedorPago = ref('manual')
const proveedores = ref<string[]>(['manual'])
const metodoPago = ref('')
const consumos = ref<Record<string, string>>({})

const metodosDisponibles = ['tarjeta', 'oxxo', 'spei', 'efectivo', 'ventanilla']

const nombreProd = ref('')
const tipoProd = ref('membresia')
const precioPesos = ref('')
const monedaProd = ref('MXN')
const ilimitadoProd = ref(false)
const creditosProd = ref('')
// Plantilla de ciclo/rollover/restricciones (opcional).
const resetProd = ref('ninguno')
const unidadesCicloProd = ref('')
const rolloverProd = ref('ninguno')
const rolloverMaxProd = ref('')
const actividadProd = ref('')
const sucursalProd = ref('')
const actividades = ref<Actividad[]>([])
const sucursales = ref<Sucursal[]>([])
const topUps = ref<Record<string, string>>({})
const error = ref<string | null>(null)

const resetsDisponibles = ['ninguno', 'calendario', 'aniversario']
const rolloversDisponibles = ['ninguno', 'completo', 'limitado']

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

async function cargarPasarelas(): Promise<void> {
  try {
    const { data } = await api.get<{ data: string[] }>('/api/v1/pasarelas/activas')
    proveedores.value = data.data
    if (!proveedores.value.includes(proveedorPago.value) && proveedores.value.length > 0) {
      proveedorPago.value = proveedores.value[0]
    }
  } catch {
    proveedores.value = ['manual']
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

async function cargarActividades(): Promise<void> {
  try {
    const { data } = await api.get<{ data: Actividad[] }>('/api/v1/actividades')
    actividades.value = data.data
  } catch {
    actividades.value = []
  }
}

async function cargarSucursales(): Promise<void> {
  try {
    const { data } = await api.get<{ data: Sucursal[] }>('/api/v1/sucursales')
    sucursales.value = data.data
  } catch {
    sucursales.value = []
  }
}

async function crearProducto(): Promise<void> {
  error.value = null
  const recurrente = resetProd.value !== 'ninguno'
  try {
    await api.post('/api/v1/productos', {
      nombre: nombreProd.value,
      tipo: tipoProd.value,
      precio_minor: Math.round((Number(precioPesos.value) || 0) * 100),
      moneda: monedaProd.value,
      ilimitado: ilimitadoProd.value,
      creditos_incluidos: ilimitadoProd.value ? null : Math.round((Number(creditosProd.value) || 0) * 1000),
      politica_reset: resetProd.value,
      unidades_por_ciclo:
        recurrente && !ilimitadoProd.value ? Math.round((Number(unidadesCicloProd.value) || 0) * 1000) : null,
      politica_rollover: rolloverProd.value,
      rollover_max:
        rolloverProd.value === 'limitado' ? Math.round((Number(rolloverMaxProd.value) || 0) * 1000) : null,
      actividad_id: actividadProd.value || null,
      sucursal_id: sucursalProd.value || null,
    })
    nombreProd.value = ''
    precioPesos.value = ''
    creditosProd.value = ''
    ilimitadoProd.value = false
    resetProd.value = 'ninguno'
    unidadesCicloProd.value = ''
    rolloverProd.value = 'ninguno'
    rolloverMaxProd.value = ''
    actividadProd.value = ''
    sucursalProd.value = ''
    await cargarProductos()
  } catch {
    error.value = t('membresias.errorGenerico')
  }
}

async function recargar(derechoId: string): Promise<void> {
  error.value = null
  const creditos = Number(topUps.value[derechoId])
  if (!creditos || creditos <= 0) {
    return
  }
  try {
    await api.post(`/api/v1/derechos/${derechoId}/topups`, {
      unidades: Math.round(creditos * 1000),
    })
    topUps.value[derechoId] = ''
    await cargarDerechos()
  } catch {
    error.value = t('membresias.errorGenerico')
  }
}

async function vender(): Promise<void> {
  error.value = null
  try {
    // Flujo comercial: crear la orden y cobrarla; el pago aprobado concede el derecho.
    const { data } = await api.post<{ data: { id: string } }>('/api/v1/ordenes', {
      persona_id: personaId.value,
      items: [{ producto_id: productoVenta.value }],
    })
    await api.post(`/api/v1/ordenes/${data.data.id}/pagos`, {
      proveedor: proveedorPago.value,
      metodo: metodoPago.value || null,
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
  const tareas = [cargarProductos(), cargarPersonas(), cargarPasarelas()]
  if (puedeGestionarProductos.value) {
    tareas.push(cargarActividades(), cargarSucursales())
  }
  await Promise.all(tareas)
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

          <!-- Ciclo / rollover / restricciones (opcional) -->
          <details class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
            <summary class="cursor-pointer text-xs font-medium text-slate-600">
              {{ t('membresias.config') }}
            </summary>
            <div class="mt-2 space-y-2">
              <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-slate-600">
                  {{ t('membresias.reinicio') }}
                  <select v-model="resetProd" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                    <option v-for="r in resetsDisponibles" :key="r" :value="r">
                      {{ t('membresias.reinicios.' + r) }}
                    </option>
                  </select>
                </label>
                <label v-if="resetProd !== 'ninguno' && !ilimitadoProd" class="text-xs text-slate-600">
                  {{ t('membresias.unidadesPorCiclo') }}
                  <input
                    v-model="unidadesCicloProd"
                    type="number"
                    step="0.001"
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                  />
                </label>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-slate-600">
                  {{ t('membresias.rollover') }}
                  <select v-model="rolloverProd" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                    <option v-for="r in rolloversDisponibles" :key="r" :value="r">
                      {{ t('membresias.rollovers.' + r) }}
                    </option>
                  </select>
                </label>
                <label v-if="rolloverProd === 'limitado'" class="text-xs text-slate-600">
                  {{ t('membresias.rolloverMax') }}
                  <input
                    v-model="rolloverMaxProd"
                    type="number"
                    step="0.001"
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                  />
                </label>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-slate-600">
                  {{ t('membresias.restriccionActividad') }}
                  <select v-model="actividadProd" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="">{{ t('membresias.cualquiera') }}</option>
                    <option v-for="a in actividades" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                  </select>
                </label>
                <label class="text-xs text-slate-600">
                  {{ t('membresias.restriccionSucursal') }}
                  <select v-model="sucursalProd" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="">{{ t('membresias.cualquiera') }}</option>
                    <option v-for="s in sucursales" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                  </select>
                </label>
              </div>
            </div>
          </details>

          <button
            type="submit"
            class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700"
          >
            {{ t('membresias.crear') }}
          </button>
        </form>

        <p v-if="!hayProductos" class="text-sm text-slate-500">{{ t('membresias.sinProductos') }}</p>
        <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
          <li v-for="producto in productos" :key="producto.id" class="space-y-1 px-4 py-3 text-sm">
            <div class="flex items-center justify-between">
              <span>
                <span class="font-medium">{{ producto.nombre }}</span>
                <span class="text-slate-400"> · {{ t('membresias.tipos.' + producto.tipo) }}</span>
              </span>
              <span class="text-slate-500">
                {{ precioTexto(producto) }} ·
                {{ producto.ilimitado ? t('membresias.ilimitado') : (producto.creditos_incluidos ?? 0) / 1000 }}
              </span>
            </div>
            <div class="flex flex-wrap gap-1">
              <span
                v-if="producto.politica_reset !== 'ninguno'"
                class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600"
              >
                {{ t('membresias.reinicio') }}: {{ t('membresias.reinicios.' + producto.politica_reset) }}
                <template v-if="producto.unidades_por_ciclo">
                  · {{ producto.unidades_por_ciclo / 1000 }}
                </template>
              </span>
              <span
                v-if="producto.politica_rollover !== 'ninguno'"
                class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600"
              >
                {{ t('membresias.rollover') }}: {{ t('membresias.rollovers.' + producto.politica_rollover) }}
                <template v-if="producto.rollover_max"> ({{ producto.rollover_max / 1000 }})</template>
              </span>
              <span
                v-if="producto.actividad"
                class="rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-700"
              >
                {{ producto.actividad }}
              </span>
              <span
                v-if="producto.sucursal"
                class="rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-700"
              >
                {{ producto.sucursal }}
              </span>
            </div>
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
            <div v-if="puedeVender && hayProductos" class="mt-3 flex flex-wrap items-end gap-2">
              <label class="text-sm">
                <span class="text-slate-600">{{ t('membresias.proveedor') }}</span>
                <select
                  v-model="proveedorPago"
                  class="mt-1 block rounded-md border border-slate-300 px-2 py-2 text-sm capitalize"
                >
                  <option v-for="proveedor in proveedores" :key="proveedor" :value="proveedor">
                    {{ proveedor }}
                  </option>
                </select>
              </label>
              <label class="text-sm">
                <span class="text-slate-600">{{ t('membresias.metodo') }}</span>
                <select
                  v-model="metodoPago"
                  class="mt-1 block rounded-md border border-slate-300 px-2 py-2 text-sm"
                >
                  <option value="">{{ t('membresias.metodoAuto') }}</option>
                  <option v-for="metodo in metodosDisponibles" :key="metodo" :value="metodo">
                    {{ t('membresias.metodos.' + metodo) }}
                  </option>
                </select>
              </label>
              <button
                class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
                @click="vender"
              >
                {{ t('membresias.venderBtn') }}
              </button>
            </div>
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
              <div v-if="puedeVender && !derecho.ilimitado" class="flex flex-wrap items-center gap-2">
                <form class="flex items-center gap-2" @submit.prevent="consumir(derecho.id)">
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
                <form class="flex items-center gap-2" @submit.prevent="recargar(derecho.id)">
                  <input
                    v-model="topUps[derecho.id]"
                    type="number"
                    step="0.001"
                    :placeholder="t('membresias.topUp')"
                    class="w-24 rounded-md border border-emerald-300 px-2 py-1 text-xs"
                  />
                  <button
                    type="submit"
                    class="rounded-md bg-emerald-700 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-600"
                  >
                    {{ t('membresias.topUpBtn') }}
                  </button>
                </form>
              </div>
            </li>
          </ul>
        </template>
      </div>
    </div>
  </section>
</template>
