<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import EncabezadoSeccion from '@/components/EncabezadoSeccion.vue'
import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

interface Miembro {
  id: string
  nombre: string
}
interface Actividad {
  id: string
  tipo: string
  detalle: string | null
  usuario: string | null
  creado_en: string | null
}
interface Prospecto {
  id: string
  nombre: string
  email: string | null
  telefono: string | null
  origen: string
  etapa: string
  interes: string | null
  motivo: string | null
  proximo_seguimiento: string | null
  convertido_en: string | null
  responsable: string | null
  responsable_id: string | null
  miembro: Miembro | null
  creado_en: string | null
  actividades?: Actividad[]
}
type Pipeline = Record<string, number>

const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)
const puedeGestionar = computed(() => sesion.puede('crm.gestionar'))

const ETAPAS = ['nuevo', 'contactado', 'interesado', 'prueba', 'ganado', 'perdido'] as const
const ORIGENES = ['web', 'referido', 'marketplace', 'presencial', 'redes', 'otro'] as const
const TIPOS_ACTIVIDAD = ['nota', 'llamada', 'correo', 'whatsapp', 'cita'] as const

const prospectos = ref<Prospecto[]>([])
const pipeline = ref<Pipeline>({})
const cargando = ref(true)
const error = ref<string | null>(null)

// Filtros
const filtroOrigen = ref('')
const buscar = ref('')

// Alta
const mostrarNuevo = ref(false)
const guardando = ref(false)
const nuevo = ref({ nombre: '', email: '', telefono: '', origen: 'web', interes: '', proximo_seguimiento: '', codigo_referido: '' })

// Drawer de detalle
const detalle = ref<Prospecto | null>(null)
const accionando = ref(false)
const etapaModel = ref({ etapa: '', motivo: '' })
const actividadModel = ref({ tipo: 'nota', detalle: '' })

const prospectosFiltrados = computed(() => {
  const q = buscar.value.trim().toLowerCase()
  return prospectos.value.filter((p) => {
    if (filtroOrigen.value !== '' && p.origen !== filtroOrigen.value) {
      return false
    }
    if (q !== '') {
      const texto = `${p.nombre} ${p.email ?? ''} ${p.telefono ?? ''}`.toLowerCase()
      if (!texto.includes(q)) {
        return false
      }
    }
    return true
  })
})

function porEtapa(etapa: string): Prospecto[] {
  return prospectosFiltrados.value.filter((p) => p.etapa === etapa)
}

function fecha(iso: string | null): string {
  if (iso === null) {
    return '—'
  }
  return new Date(iso).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: Prospecto[]; pipeline: Pipeline }>(`${base.value}/crm/prospectos`)
    prospectos.value = data.data
    pipeline.value = data.pipeline
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

async function crear(): Promise<void> {
  if (nuevo.value.nombre.trim() === '') {
    return
  }
  guardando.value = true
  error.value = null
  try {
    await api.post(`${base.value}/crm/prospectos`, {
      nombre: nuevo.value.nombre,
      email: nuevo.value.email !== '' ? nuevo.value.email : null,
      telefono: nuevo.value.telefono !== '' ? nuevo.value.telefono : null,
      origen: nuevo.value.origen,
      interes: nuevo.value.interes !== '' ? nuevo.value.interes : null,
      proximo_seguimiento: nuevo.value.proximo_seguimiento !== '' ? nuevo.value.proximo_seguimiento : null,
      codigo_referido: nuevo.value.codigo_referido !== '' ? nuevo.value.codigo_referido : undefined,
    })
    nuevo.value = { nombre: '', email: '', telefono: '', origen: 'web', interes: '', proximo_seguimiento: '', codigo_referido: '' }
    mostrarNuevo.value = false
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    guardando.value = false
  }
}

async function abrirDetalle(p: Prospecto): Promise<void> {
  detalle.value = p
  etapaModel.value = { etapa: p.etapa, motivo: '' }
  actividadModel.value = { tipo: 'nota', detalle: '' }
  try {
    const { data } = await api.get<{ data: Prospecto }>(`${base.value}/crm/prospectos/${p.id}`)
    detalle.value = data.data
    etapaModel.value.etapa = data.data.etapa
  } catch (e) {
    error.value = mensajeDeError(e)
  }
}

function cerrarDetalle(): void {
  detalle.value = null
}

async function cambiarEtapa(): Promise<void> {
  if (detalle.value === null || etapaModel.value.etapa === '') {
    return
  }
  accionando.value = true
  error.value = null
  try {
    const { data } = await api.post<{ data: Prospecto }>(`${base.value}/crm/prospectos/${detalle.value.id}/etapa`, {
      etapa: etapaModel.value.etapa,
      motivo: etapaModel.value.motivo !== '' ? etapaModel.value.motivo : null,
    })
    detalle.value = data.data
    etapaModel.value.motivo = ''
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

async function agregarActividad(): Promise<void> {
  if (detalle.value === null || actividadModel.value.detalle.trim() === '') {
    return
  }
  accionando.value = true
  error.value = null
  try {
    const { data } = await api.post<{ data: Prospecto }>(`${base.value}/crm/prospectos/${detalle.value.id}/actividades`, {
      tipo: actividadModel.value.tipo,
      detalle: actividadModel.value.detalle,
    })
    detalle.value = data.data
    actividadModel.value = { tipo: 'nota', detalle: '' }
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

async function convertir(): Promise<void> {
  if (detalle.value === null) {
    return
  }
  accionando.value = true
  error.value = null
  try {
    const { data } = await api.post<{ data: Prospecto }>(`${base.value}/crm/prospectos/${detalle.value.id}/convertir`, {})
    detalle.value = data.data
    etapaModel.value.etapa = data.data.etapa
    await cargar()
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    accionando.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <section class="mx-auto max-w-6xl px-4 sm:px-6 py-8">
    <div class="flex items-start justify-between gap-3">
      <EncabezadoSeccion icono="crm" :titulo="$t('crm.titulo')" :subtitulo="$t('crm.subtitulo')" />
      <button v-if="puedeGestionar" class="tu-btn tu-btn-primario shrink-0" type="button" @click="mostrarNuevo = !mostrarNuevo">
        {{ $t('crm.nuevo') }}
      </button>
    </div>

    <!-- Alta de prospecto -->
    <form v-if="mostrarNuevo && puedeGestionar" class="mt-6 tu-card p-4 grid gap-3 sm:grid-cols-2" @submit.prevent="crear">
      <div>
        <label class="tu-label" for="np-nombre">{{ $t('crm.campos.nombre') }}</label>
        <input id="np-nombre" v-model="nuevo.nombre" class="tu-input" required />
      </div>
      <div>
        <label class="tu-label" for="np-origen">{{ $t('crm.campos.origen') }}</label>
        <select id="np-origen" v-model="nuevo.origen" class="tu-input">
          <option v-for="o in ORIGENES" :key="o" :value="o">{{ $t(`crm.origen.${o}`) }}</option>
        </select>
      </div>
      <div>
        <label class="tu-label" for="np-email">{{ $t('crm.campos.email') }}</label>
        <input id="np-email" v-model="nuevo.email" type="email" class="tu-input" />
      </div>
      <div>
        <label class="tu-label" for="np-tel">{{ $t('crm.campos.telefono') }}</label>
        <input id="np-tel" v-model="nuevo.telefono" class="tu-input" />
      </div>
      <div>
        <label class="tu-label" for="np-interes">{{ $t('crm.campos.interes') }}</label>
        <input id="np-interes" v-model="nuevo.interes" class="tu-input" />
      </div>
      <div>
        <label class="tu-label" for="np-seg">{{ $t('crm.campos.seguimiento') }}</label>
        <input id="np-seg" v-model="nuevo.proximo_seguimiento" type="date" class="tu-input" />
      </div>
      <div>
        <label class="tu-label" for="np-ref">{{ $t('crm.campos.codigoReferido') }}</label>
        <input id="np-ref" v-model="nuevo.codigo_referido" class="tu-input uppercase" :placeholder="$t('crm.campos.codigoReferidoPlaceholder')" />
      </div>
      <div class="sm:col-span-2 flex gap-2">
        <button class="tu-btn tu-btn-primario" type="submit" :disabled="guardando || nuevo.nombre.trim() === ''">
          {{ $t('crm.guardar') }}
        </button>
        <button class="tu-btn tu-btn-fantasma" type="button" @click="mostrarNuevo = false">{{ $t('crm.cancelar') }}</button>
      </div>
    </form>

    <!-- Filtros -->
    <div class="mt-6 flex flex-wrap items-end gap-3">
      <div class="grow min-w-[12rem]">
        <label class="tu-label" for="cf-buscar">{{ $t('crm.buscar') }}</label>
        <input id="cf-buscar" v-model="buscar" class="tu-input" :placeholder="$t('crm.buscar')" />
      </div>
      <div>
        <label class="tu-label" for="cf-origen">{{ $t('crm.campos.origen') }}</label>
        <select id="cf-origen" v-model="filtroOrigen" class="tu-input w-auto">
          <option value="">{{ $t('crm.filtroOrigen') }}</option>
          <option v-for="o in ORIGENES" :key="o" :value="o">{{ $t(`crm.origen.${o}`) }}</option>
        </select>
      </div>
    </div>

    <p v-if="error" class="mt-4 text-sm" style="color: var(--error)">{{ error }}</p>
    <p v-if="cargando" class="mt-6 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('comun.cargando') }}</p>

    <p v-else-if="prospectos.length === 0" class="mt-10 text-center text-sm" :style="{ color: 'var(--texto-suave)' }">
      {{ $t('crm.sinProspectos') }}
    </p>

    <!-- Tablero del embudo -->
    <div v-else class="mt-6 flex gap-4 overflow-x-auto pb-4">
      <div v-for="etapa in ETAPAS" :key="etapa" class="shrink-0 w-64">
        <div class="flex items-center justify-between mb-2">
          <h2 class="font-bold text-sm">{{ $t(`crm.etapas.${etapa}`) }}</h2>
          <span class="tu-badge">{{ pipeline[etapa] ?? 0 }}</span>
        </div>
        <div class="space-y-2">
          <button
            v-for="p in porEtapa(etapa)"
            :key="p.id"
            type="button"
            class="tu-card p-3 w-full text-left transition hover:shadow-md"
            @click="abrirDetalle(p)"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold truncate">{{ p.nombre }}</span>
              <span class="tu-badge shrink-0">{{ $t(`crm.origen.${p.origen}`) }}</span>
            </div>
            <p v-if="p.interes" class="text-xs mt-1 truncate" :style="{ color: 'var(--texto-suave)' }">{{ p.interes }}</p>
            <div class="flex items-center gap-2 mt-2 text-xs" :style="{ color: 'var(--texto-suave)' }">
              <span v-if="p.miembro" class="tu-badge tu-badge-exito">{{ $t('crm.yaMiembro') }}</span>
              <span v-if="p.proximo_seguimiento">📅 {{ fecha(p.proximo_seguimiento) }}</span>
              <span v-if="p.responsable" class="truncate">· {{ p.responsable }}</span>
            </div>
          </button>
          <p v-if="porEtapa(etapa).length === 0" class="text-xs px-1" :style="{ color: 'var(--texto-suave)' }">
            {{ $t('crm.sinResultados') }}
          </p>
        </div>
      </div>
    </div>

    <!-- ===== Drawer de detalle ===== -->
    <div v-if="detalle" class="fixed inset-0 z-50 flex justify-end">
      <div class="absolute inset-0 bg-black/50" @click="cerrarDetalle" />
      <aside class="relative w-full max-w-md h-full overflow-y-auto p-5 shadow-xl" :style="{ background: 'var(--superficie)' }">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="text-lg font-bold truncate">{{ detalle.nombre }}</h2>
            <p class="text-sm" :style="{ color: 'var(--texto-suave)' }">
              {{ $t(`crm.origen.${detalle.origen}`) }}
              <span v-if="detalle.email"> · {{ detalle.email }}</span>
              <span v-if="detalle.telefono"> · {{ detalle.telefono }}</span>
            </p>
            <span class="tu-badge mt-1 inline-block" :class="{ 'tu-badge-exito': detalle.etapa === 'ganado', 'tu-badge-aviso': detalle.etapa === 'perdido' }">
              {{ $t(`crm.etapas.${detalle.etapa}`) }}
            </span>
          </div>
          <button class="tu-icono-btn" :aria-label="$t('crm.cerrar')" @click="cerrarDetalle">✕</button>
        </div>

        <dl class="mt-4 text-sm space-y-1">
          <div v-if="detalle.interes" class="flex justify-between gap-2">
            <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('crm.interes') }}</dt>
            <dd class="text-right">{{ detalle.interes }}</dd>
          </div>
          <div v-if="detalle.proximo_seguimiento" class="flex justify-between gap-2">
            <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('crm.seguimiento') }}</dt>
            <dd class="text-right">{{ fecha(detalle.proximo_seguimiento) }}</dd>
          </div>
          <div v-if="detalle.responsable" class="flex justify-between gap-2">
            <dt :style="{ color: 'var(--texto-suave)' }">{{ $t('crm.responsable') }}</dt>
            <dd class="text-right">{{ detalle.responsable }}</dd>
          </div>
        </dl>

        <!-- Miembro enlazado / convertir -->
        <div class="mt-4 border-t pt-4" :style="{ borderColor: 'var(--borde)' }">
          <div v-if="detalle.miembro" class="flex items-center justify-between gap-2 text-sm">
            <span class="tu-badge tu-badge-exito">{{ $t('crm.convertido') }}</span>
            <RouterLink :to="{ name: 'miembros' }" class="tu-enlace">{{ $t('crm.verMiembro') }}</RouterLink>
          </div>
          <button
            v-else-if="puedeGestionar && detalle.etapa !== 'perdido'"
            class="tu-btn tu-btn-primario w-full"
            type="button"
            :disabled="accionando"
            @click="convertir"
          >
            {{ $t('crm.convertir') }}
          </button>
        </div>

        <!-- Cambio de etapa -->
        <div v-if="puedeGestionar" class="mt-4 border-t pt-4" :style="{ borderColor: 'var(--borde)' }">
          <h3 class="font-semibold text-sm">{{ $t('crm.moverA') }}</h3>
          <div class="mt-2 flex flex-wrap items-end gap-2">
            <select v-model="etapaModel.etapa" class="tu-input w-auto grow">
              <option v-for="e in ETAPAS" :key="e" :value="e">{{ $t(`crm.etapas.${e}`) }}</option>
            </select>
            <button class="tu-btn tu-btn-fantasma" type="button" :disabled="accionando || etapaModel.etapa === detalle.etapa" @click="cambiarEtapa">
              {{ $t('crm.guardar') }}
            </button>
          </div>
          <input
            v-if="etapaModel.etapa === 'perdido'"
            v-model="etapaModel.motivo"
            class="tu-input mt-2"
            :placeholder="$t('crm.motivoPerdido')"
          />
        </div>

        <!-- Bitácora -->
        <div class="mt-4 border-t pt-4" :style="{ borderColor: 'var(--borde)' }">
          <h3 class="font-semibold text-sm">{{ $t('crm.bitacora.titulo') }}</h3>
          <form v-if="puedeGestionar" class="mt-2 flex flex-wrap items-end gap-2" @submit.prevent="agregarActividad">
            <select v-model="actividadModel.tipo" class="tu-input w-auto">
              <option v-for="t in TIPOS_ACTIVIDAD" :key="t" :value="t">{{ $t(`crm.tipos.${t}`) }}</option>
            </select>
            <input v-model="actividadModel.detalle" class="tu-input grow min-w-[8rem]" :placeholder="$t('crm.bitacora.placeholder')" />
            <button class="tu-btn tu-btn-primario" type="submit" :disabled="accionando || actividadModel.detalle.trim() === ''">
              {{ $t('crm.bitacora.agregar') }}
            </button>
          </form>

          <ul v-if="detalle.actividades && detalle.actividades.length > 0" class="mt-3 space-y-2">
            <li v-for="a in detalle.actividades" :key="a.id" class="text-sm">
              <div class="flex items-center gap-2">
                <span class="tu-badge">{{ $t(`crm.tipos.${a.tipo}`) }}</span>
                <span class="text-xs" :style="{ color: 'var(--texto-suave)' }">{{ fecha(a.creado_en) }}<span v-if="a.usuario"> · {{ a.usuario }}</span></span>
              </div>
              <p v-if="a.detalle" class="mt-1">{{ a.detalle }}</p>
            </li>
          </ul>
          <p v-else class="mt-3 text-sm" :style="{ color: 'var(--texto-suave)' }">{{ $t('crm.bitacora.vacio') }}</p>
        </div>
      </aside>
    </div>
  </section>
</template>
