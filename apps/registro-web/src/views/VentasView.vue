<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface Miembro {
  id: string
  nombre: string
  apellidos: string | null
}
interface Producto {
  id: string
  nombre: string
  tipo: string
  precio_minor: number
  moneda: string
  ilimitado: boolean
  creditos_incluidos: number | null
}
interface Orden {
  id: string
  comprador: string | null
  estado: string
  total_minor: number
  moneda: string
}

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)
const puedeVender = computed(() => sesion.puede('ordenes.gestionar'))
const puedeCrearProducto = computed(() => sesion.puede('productos.gestionar'))

const miembros = ref<Miembro[]>([])
const productos = ref<Producto[]>([])
const ordenes = ref<Orden[]>([])
const cargando = ref(true)
const error = ref<string | null>(null)

const venta = ref({ compradorId: '', productoId: '', metodo: 'efectivo' })
const vendiendo = ref(false)
const exito = ref<string | null>(null)

const prod = ref({ nombre: '', tipo: 'paquete', precio: '899', creditos: '8' })
const creando = ref(false)

function dinero(minor: number, moneda: string): string {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: moneda }).format(minor / 100)
}
function nombreMiembro(m: Miembro): string {
  return `${m.nombre} ${m.apellidos ?? ''}`.trim()
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const [m, p, o] = await Promise.all([
      api.get<{ data: Miembro[] }>(`${base.value}/miembros`, { params: { tipo: 'miembro' } }),
      api.get<{ data: Producto[] }>(`${base.value}/productos`),
      api.get<{ data: Orden[] }>(`${base.value}/ordenes`),
    ])
    miembros.value = m.data.data
    productos.value = p.data.data
    ordenes.value = o.data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

async function vender(): Promise<void> {
  vendiendo.value = true
  error.value = null
  exito.value = null
  try {
    const orden = await api.post<{ data: { id: string } }>(`${base.value}/ordenes`, {
      comprador_id: venta.value.compradorId,
      items: [{ producto_id: venta.value.productoId, cantidad: 1 }],
    })
    await api.post(`${base.value}/ordenes/${orden.data.data.id}/liquidar`, {
      metodo: venta.value.metodo,
    })

    // Muestra el derecho recien concedido al comprador.
    const der = await api.get<{ data: Array<{ ilimitado: boolean; saldo: number | null }> }>(
      `${base.value}/miembros/${venta.value.compradorId}/derechos`,
    )
    const persona = miembros.value.find((x) => x.id === venta.value.compradorId)
    const nombre = persona ? nombreMiembro(persona) : ''
    const ultimo = der.data.data[0]
    exito.value =
      ultimo && ultimo.ilimitado
        ? 'membresia:' + nombre
        : 'pack:' + nombre + '|' + String(ultimo?.saldo ?? 0)

    venta.value.productoId = ''
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    vendiendo.value = false
  }
}

async function crearProducto(): Promise<void> {
  creando.value = true
  error.value = null
  try {
    const esPaquete = prod.value.tipo === 'paquete'
    await api.post(`${base.value}/productos`, {
      nombre: prod.value.nombre,
      tipo: esPaquete ? 'paquete' : 'membresia',
      precio_minor: Math.round(Number(prod.value.precio) * 100),
      moneda: 'MXN',
      ilimitado: !esPaquete,
      creditos_incluidos: esPaquete ? Math.round(Number(prod.value.creditos) * 1000) : null,
    })
    prod.value.nombre = ''
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    creando.value = false
  }
}

const exitoTexto = computed(() => {
  if (exito.value === null) {
    return null
  }
  if (exito.value.startsWith('membresia:')) {
    return { clave: 'ventas.vender.exitoMembresia', args: { persona: exito.value.slice('membresia:'.length) } }
  }
  const resto = exito.value.slice('pack:'.length)
  const [persona, saldo] = resto.split('|')
  return { clave: 'ventas.vender.exitoPack', args: { persona, saldo: Math.round(Number(saldo) / 1000) } }
})

onMounted(cargar)
</script>

<template>
  <section class="mx-auto max-w-5xl px-4 py-10">
    <h1 class="text-3xl font-extrabold">{{ $t('ventas.titulo') }}</h1>
    <p class="mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('ventas.subtitulo') }}</p>

    <p v-if="cargando" class="mt-8" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>
    <p v-if="error" class="mt-4 text-sm" style="color: var(--error)">{{ error }}</p>

    <div v-if="!cargando" class="mt-6 grid gap-6 md:grid-cols-2">
      <!-- Vender -->
      <div v-if="puedeVender" class="tu-card p-6">
        <h2 class="font-bold text-lg">{{ $t('ventas.vender.titulo') }}</h2>

        <p v-if="miembros.length === 0" class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('ventas.vender.sinMiembros') }}
        </p>
        <p v-else-if="productos.length === 0" class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('ventas.vender.sinProductos') }}
        </p>

        <form v-else class="mt-4 space-y-3" @submit.prevent="vender">
          <div>
            <label class="tu-label" for="vm">{{ $t('ventas.vender.miembro') }}</label>
            <select id="vm" v-model="venta.compradorId" class="tu-input" required>
              <option value="" disabled>{{ $t('ventas.vender.elegir') }}</option>
              <option v-for="m in miembros" :key="m.id" :value="m.id">{{ nombreMiembro(m) }}</option>
            </select>
          </div>
          <div>
            <label class="tu-label" for="vp">{{ $t('ventas.vender.producto') }}</label>
            <select id="vp" v-model="venta.productoId" class="tu-input" required>
              <option value="" disabled>{{ $t('ventas.vender.elegir') }}</option>
              <option v-for="p in productos" :key="p.id" :value="p.id">
                {{ p.nombre }} · {{ dinero(p.precio_minor, p.moneda) }}
              </option>
            </select>
          </div>
          <div>
            <label class="tu-label" for="vmet">{{ $t('ventas.vender.metodo') }}</label>
            <select id="vmet" v-model="venta.metodo" class="tu-input">
              <option value="efectivo">{{ $t('ventas.metodos.efectivo') }}</option>
              <option value="transferencia">{{ $t('ventas.metodos.transferencia') }}</option>
              <option value="ventanilla">{{ $t('ventas.metodos.ventanilla') }}</option>
            </select>
          </div>
          <p
            v-if="exitoTexto"
            class="rounded-lg p-2.5 text-sm"
            :style="{ background: 'var(--exito-suave)', color: 'var(--exito)' }"
          >
            {{ $t(exitoTexto.clave, exitoTexto.args) }}
          </p>
          <button
            class="tu-btn tu-btn-primario w-full"
            type="submit"
            :disabled="vendiendo || venta.compradorId === '' || venta.productoId === ''"
          >
            {{ vendiendo ? $t('ventas.vender.cobrando') : $t('ventas.vender.cobrar') }}
          </button>
        </form>
      </div>

      <!-- Productos -->
      <div class="tu-card p-6">
        <h2 class="font-bold text-lg">{{ $t('ventas.productos.titulo') }}</h2>
        <ul v-if="productos.length > 0" class="mt-3 space-y-2">
          <li
            v-for="p in productos"
            :key="p.id"
            class="flex items-center justify-between gap-2 text-sm"
          >
            <span class="font-medium truncate">{{ p.nombre }}</span>
            <span class="shrink-0" :style="{ color: 'var(--texto-suave)' }">
              {{ dinero(p.precio_minor, p.moneda) }} ·
              {{
                p.ilimitado
                  ? $t('ventas.productos.ilimitado')
                  : $t('ventas.productos.creditosSufijo', { n: Math.round((p.creditos_incluidos ?? 0) / 1000) })
              }}
            </span>
          </li>
        </ul>
        <p v-else class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('ventas.productos.vacio') }}
        </p>

        <form v-if="puedeCrearProducto" class="mt-5 border-t pt-4 space-y-3" :style="{ borderColor: 'var(--borde)' }" @submit.prevent="crearProducto">
          <p class="font-semibold text-sm">{{ $t('ventas.productos.nuevo') }}</p>
          <input v-model="prod.nombre" class="tu-input" :placeholder="$t('ventas.productos.nombrePh')" required />
          <div class="grid grid-cols-2 gap-2">
            <select v-model="prod.tipo" class="tu-input">
              <option value="paquete">{{ $t('ventas.tipos.paquete') }}</option>
              <option value="membresia">{{ $t('ventas.tipos.membresia') }}</option>
            </select>
            <input v-model="prod.precio" class="tu-input" type="number" min="0" step="0.01" :aria-label="$t('ventas.productos.precio')" />
          </div>
          <input
            v-if="prod.tipo === 'paquete'"
            v-model="prod.creditos"
            class="tu-input"
            type="number"
            min="1"
            :aria-label="$t('ventas.productos.creditos')"
          />
          <button class="tu-btn tu-btn-fantasma w-full" type="submit" :disabled="creando || prod.nombre.trim() === ''">
            {{ creando ? $t('ventas.productos.creando') : $t('ventas.productos.crear') }}
          </button>
        </form>
      </div>
    </div>

    <!-- Ventas recientes -->
    <div v-if="!cargando" class="mt-6 tu-card p-6">
      <h2 class="font-bold text-lg">{{ $t('ventas.ordenes.titulo') }}</h2>
      <ul v-if="ordenes.length > 0" class="mt-3 space-y-2">
        <li v-for="o in ordenes" :key="o.id" class="flex items-center justify-between gap-2 text-sm">
          <span class="truncate">{{ o.comprador ?? '—' }}</span>
          <span class="flex items-center gap-2 shrink-0">
            <span>{{ dinero(o.total_minor, o.moneda) }}</span>
            <span class="tu-badge" :class="{ 'tu-badge-exito': o.estado === 'pagada' }">
              {{ o.estado === 'pagada' ? $t('ventas.ordenes.pagada') : $t('ventas.ordenes.pendiente') }}
            </span>
          </span>
        </li>
      </ul>
      <p v-else class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('ventas.ordenes.vacio') }}
      </p>
    </div>
  </section>
</template>
