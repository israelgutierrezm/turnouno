<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { api } from '@/lib/api'
import { prepararTarjetaStripe } from '@/lib/checkout'

interface Producto {
  id: string
  nombre: string
  precio_minor: number
  moneda: string
  ilimitado: boolean
}

interface Pasarela {
  proveedor: string
  modo: string
  llaves: Record<string, string>
}

interface CheckoutInfo {
  tipo?: string
  client_secret?: string
  url?: string
  reference?: string
  barcode_url?: string
}

interface PagoResp {
  id: string
  estado: string
  proveedor: string
  orden_estado: string
  checkout: CheckoutInfo
}

const metodosPorProveedor: Record<string, string[]> = {
  manual: ['efectivo'],
  simulada: ['tarjeta'],
  stripe: ['tarjeta', 'oxxo'],
  openpay: ['tarjeta', 'oxxo', 'spei'],
  mercadopago: ['tarjeta', 'oxxo', 'spei'],
  ventanilla: ['ventanilla'],
}

const { t } = useI18n()

const productos = ref<Producto[]>([])
const pasarelas = ref<Pasarela[]>([])

const fase = ref<'productos' | 'pago' | 'voucher' | 'ventanilla' | 'exito' | 'pendiente'>('productos')
const ordenId = ref('')
const proveedor = ref('')
const metodo = ref('')
const voucher = ref<CheckoutInfo | null>(null)
const pagoId = ref('')
const mensaje = ref<string | null>(null)
const error = ref<string | null>(null)

const contenedorTarjeta = ref<HTMLElement | null>(null)
let confirmarTarjeta: ((clientSecret: string) => Promise<{ ok: boolean; error?: string }>) | null = null

function precio(producto: Producto): string {
  return `${(producto.precio_minor / 100).toFixed(2)} ${producto.moneda}`
}

function metodos(): string[] {
  return metodosPorProveedor[proveedor.value] ?? ['efectivo']
}

function llavePublica(nombre: string): string | undefined {
  return pasarelas.value.find((p) => p.proveedor === proveedor.value)?.llaves[nombre]
}

async function cargar(): Promise<void> {
  const [prod, pas] = await Promise.all([
    api.get<{ data: Producto[] }>('/api/v1/mi/productos'),
    api.get<{ data: Pasarela[] }>('/api/v1/mi/pasarelas'),
  ])
  productos.value = prod.data.data
  pasarelas.value = pas.data.data
  if (proveedor.value === '' && pasarelas.value.length > 0) {
    proveedor.value = pasarelas.value[0].proveedor
    metodo.value = metodos()[0]
  }
}

async function comprar(producto: Producto): Promise<void> {
  error.value = null
  const { data } = await api.post<{ data: { id: string } }>('/api/v1/mi/ordenes', { producto_id: producto.id })
  ordenId.value = data.data.id
  fase.value = 'pago'
  mensaje.value = t('comprar.creado')
}

// Monta el campo de tarjeta de Stripe cuando aplica.
watch([proveedor, metodo, fase], async () => {
  confirmarTarjeta = null
  if (fase.value !== 'pago' || proveedor.value !== 'stripe' || metodo.value !== 'tarjeta') {
    return
  }
  const llave = llavePublica('public_key')
  if (llave === undefined) {
    return
  }
  await nextTick()
  if (contenedorTarjeta.value !== null) {
    try {
      confirmarTarjeta = await prepararTarjetaStripe(llave, contenedorTarjeta.value)
    } catch {
      confirmarTarjeta = null
    }
  }
})

async function pagar(): Promise<void> {
  error.value = null
  try {
    const { data } = await api.post<{ data: PagoResp }>(`/api/v1/mi/ordenes/${ordenId.value}/pagos`, {
      proveedor: proveedor.value,
      metodo: metodo.value || null,
    })
    const pago = data.data
    pagoId.value = pago.id

    if (pago.estado === 'aprobado') {
      fase.value = 'exito'
      mensaje.value = t('comprar.pagado')
      return
    }

    // Pendiente: según el proveedor/checkout.
    if (pago.proveedor === 'ventanilla') {
      fase.value = 'ventanilla'
      return
    }
    if (pago.checkout.tipo === 'redirect' && pago.checkout.url !== undefined) {
      mensaje.value = t('comprar.redirigiendo')
      window.location.href = pago.checkout.url
      return
    }
    if (pago.checkout.tipo === 'voucher') {
      voucher.value = pago.checkout
      fase.value = 'voucher'
      return
    }
    if (pago.checkout.tipo === 'client_secret' && pago.checkout.client_secret !== undefined) {
      if (confirmarTarjeta === null) {
        error.value = t('comprar.error')
        return
      }
      const resultado = await confirmarTarjeta(pago.checkout.client_secret)
      if (!resultado.ok) {
        error.value = resultado.error ?? t('comprar.error')
        return
      }
    }

    fase.value = 'pendiente'
    mensaje.value = t('comprar.pendiente')
  } catch {
    error.value = t('comprar.error')
  }
}

async function subirComprobante(evento: Event): Promise<void> {
  error.value = null
  const input = evento.target as HTMLInputElement
  const archivo = input.files?.[0]
  if (archivo === undefined) {
    return
  }
  const forma = new FormData()
  forma.append('comprobante', archivo)
  try {
    await api.post(`/api/v1/pagos/${pagoId.value}/comprobante`, forma)
    fase.value = 'exito'
    mensaje.value = t('comprar.comprobanteSubido')
  } catch {
    error.value = t('comprar.error')
  }
}

onMounted(cargar)
</script>

<template>
  <section class="space-y-4">
    <h1 class="text-xl font-semibold">{{ t('comprar.titulo') }}</h1>
    <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
    <p v-if="mensaje" class="text-sm text-emerald-700">{{ mensaje }}</p>

    <!-- Catálogo -->
    <div v-if="fase === 'productos'" class="space-y-2">
      <h2 class="text-sm font-medium text-slate-700">{{ t('comprar.productos') }}</h2>
      <p v-if="productos.length === 0" class="text-sm text-slate-500">{{ t('comprar.sinProductos') }}</p>
      <ul v-else class="divide-y rounded-lg border border-slate-200 bg-white">
        <li
          v-for="producto in productos"
          :key="producto.id"
          class="flex items-center justify-between px-4 py-3 text-sm"
        >
          <span>
            <span class="font-medium">{{ producto.nombre }}</span>
            <span class="text-slate-400"> · {{ precio(producto) }}</span>
          </span>
          <button
            class="rounded-md bg-slate-800 px-3 py-1 text-xs font-medium text-white hover:bg-slate-700"
            @click="comprar(producto)"
          >
            {{ t('comprar.comprar') }}
          </button>
        </li>
      </ul>
    </div>

    <!-- Pago -->
    <div v-else-if="fase === 'pago'" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
      <label class="block text-sm">
        <span class="text-slate-600">{{ t('comprar.metodo') }}</span>
        <select v-model="proveedor" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm capitalize">
          <option v-for="pasarela in pasarelas" :key="pasarela.proveedor" :value="pasarela.proveedor">
            {{ pasarela.proveedor }}
          </option>
        </select>
      </label>
      <label class="block text-sm">
        <select v-model="metodo" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
          <option v-for="m in metodos()" :key="m" :value="m">{{ m }}</option>
        </select>
      </label>

      <div v-if="proveedor === 'stripe' && metodo === 'tarjeta'">
        <div ref="contenedorTarjeta" class="rounded-md border border-slate-300 px-3 py-3 text-sm"></div>
      </div>

      <button
        class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
        @click="pagar"
      >
        {{ t('comprar.pagar') }}
      </button>
    </div>

    <!-- Voucher (OXXO) -->
    <div v-else-if="fase === 'voucher'" class="space-y-2 rounded-lg border border-slate-200 bg-white p-4 text-sm">
      <h2 class="font-medium">{{ t('comprar.voucherTitulo') }}</h2>
      <p>{{ t('comprar.voucherRef') }}: <span class="font-mono">{{ voucher?.reference }}</span></p>
      <p class="text-slate-500">{{ t('comprar.voucherInstruccion') }}</p>
    </div>

    <!-- Ventanilla -->
    <div v-else-if="fase === 'ventanilla'" class="space-y-2 rounded-lg border border-slate-200 bg-white p-4 text-sm">
      <h2 class="font-medium">{{ t('comprar.ventanillaTitulo') }}</h2>
      <p class="text-slate-500">{{ t('comprar.ventanillaInstruccion') }}</p>
      <label class="block">
        <span class="text-slate-600">{{ t('comprar.subirComprobante') }}</span>
        <input type="file" accept="image/*,application/pdf" class="mt-1 block text-sm" @change="subirComprobante" />
      </label>
    </div>

    <!-- Éxito / pendiente -->
    <div v-else class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
      <p>{{ mensaje }}</p>
    </div>
  </section>
</template>
