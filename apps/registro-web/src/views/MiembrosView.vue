<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import EncabezadoSeccion from '@/components/EncabezadoSeccion.vue'
import TablaDatos from '@/components/TablaDatos.vue'
import { api, mensajeDeError } from '@/lib/api'
import { useSesionTenantStore } from '@/stores/sesionTenant'

type Miembro = {
  id: string
  nombre: string
  segundo_nombre: string | null
  primer_apellido: string | null
  segundo_apellido: string | null
  nombre_completo: string
  email: string | null
  tipo: string
  activo: boolean
  primera_vez: boolean | null
}

const { t } = useI18n()
const sesion = useSesionTenantStore()
const base = computed(() => `/api/v1/app/${sesion.slug}`)
const puedeGestionar = computed(() => sesion.puede('miembros.gestionar'))

const tipo = ref<'miembro' | 'instructor'>('miembro')
const miembros = ref<Miembro[]>([])
const cargando = ref(true)
const error = ref<string | null>(null)
const mensaje = ref<string | null>(null)

const form = ref({
  nombre: '',
  segundo_nombre: '',
  primer_apellido: '',
  segundo_apellido: '',
  email: '',
  tipo: 'miembro',
})
const guardando = ref(false)

const puedeInvitar = computed(() => sesion.puede('usuarios.invitar'))
const invitandoId = ref<string | null>(null)
const invitados = ref<Set<string>>(new Set())

const columnas = computed(() => {
  const cols = [
    { clave: 'nombre', etiqueta: t('miembros.colNombre') },
    { clave: 'email', etiqueta: t('miembros.colCorreo') },
    { clave: 'activo', etiqueta: t('miembros.colEstado') },
  ]
  if (puedeInvitar.value && tipo.value === 'miembro') {
    cols.push({ clave: 'acciones', etiqueta: '' })
  }
  return cols
})

async function invitar(m: Miembro): Promise<void> {
  if (m.email === null || m.email === '') {
    return
  }
  invitandoId.value = m.id
  error.value = null
  try {
    await api.post(`${base.value}/usuarios/invitar`, {
      nombre: m.nombre_completo,
      email: m.email,
      rol: 'miembro',
    })
    invitados.value = new Set(invitados.value).add(m.id)
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    invitandoId.value = null
  }
}

async function cargar(): Promise<void> {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: Miembro[] }>(`${base.value}/miembros`, {
      params: { tipo: tipo.value },
    })
    miembros.value = data.data
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    cargando.value = false
  }
}

async function crear(): Promise<void> {
  guardando.value = true
  error.value = null
  mensaje.value = null
  try {
    await api.post(`${base.value}/miembros`, {
      nombre: form.value.nombre,
      segundo_nombre: form.value.segundo_nombre || null,
      primer_apellido: form.value.primer_apellido || null,
      segundo_apellido: form.value.segundo_apellido || null,
      email: form.value.email || null,
      tipo: form.value.tipo,
    })
    mensaje.value = 'ok'
    form.value = {
      nombre: '',
      segundo_nombre: '',
      primer_apellido: '',
      segundo_apellido: '',
      email: '',
      tipo: tipo.value,
    }
    // Si el nuevo miembro es del tipo que se ve, recargar la lista.
    if (form.value.tipo === tipo.value) {
      await cargar()
    }
  } catch (e) {
    error.value = mensajeDeError(e)
  } finally {
    guardando.value = false
  }
}

function nombreCompleto(m: Miembro): string {
  return (
    m.nombre_completo ||
    [m.nombre, m.segundo_nombre, m.primer_apellido, m.segundo_apellido].filter(Boolean).join(' ').trim()
  )
}

watch(tipo, () => {
  form.value.tipo = tipo.value
  void cargar()
})

onMounted(() => {
  form.value.tipo = tipo.value
  void cargar()
})
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 py-8">
    <EncabezadoSeccion
      icono="miembros"
      :titulo="$t('miembros.titulo')"
      :subtitulo="$t('miembros.subtitulo')"
      :total="miembros.length"
    />

    <!-- Filtro alumnos / instructores -->
    <div class="mt-6 inline-flex rounded-lg border p-1" :style="{ borderColor: 'var(--borde)' }">
      <button
        type="button"
        class="px-3 py-1.5 rounded-md text-sm font-semibold"
        :style="{
          background: tipo === 'miembro' ? 'var(--primario)' : 'transparent',
          color: tipo === 'miembro' ? 'var(--primario-contraste)' : 'var(--texto)',
        }"
        @click="tipo = 'miembro'"
      >
        {{ $t('miembros.filtroMiembros') }}
      </button>
      <button
        type="button"
        class="px-3 py-1.5 rounded-md text-sm font-semibold"
        :style="{
          background: tipo === 'instructor' ? 'var(--primario)' : 'transparent',
          color: tipo === 'instructor' ? 'var(--primario-contraste)' : 'var(--texto)',
        }"
        @click="tipo = 'instructor'"
      >
        {{ $t('miembros.filtroInstructores') }}
      </button>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_300px]">
      <!-- Tabla -->
      <div class="min-w-0">
        <p v-if="cargando" class="tu-card p-6 text-sm" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('comun.cargando') }}
        </p>
        <TablaDatos
          v-else
          :columnas="columnas"
          :filas="miembros"
          :buscar-en="['nombre', 'primer_apellido', 'segundo_apellido', 'nombre_completo', 'email']"
          :vacio="$t('miembros.vacio')"
        >
          <template #col-nombre="{ fila }">
            <div class="flex items-center gap-3">
              <span
                class="h-8 w-8 rounded-full inline-flex items-center justify-center text-xs font-bold text-white shrink-0"
                :style="{ background: 'var(--primario)' }"
                aria-hidden="true"
                >{{ (fila as Miembro).nombre.charAt(0).toUpperCase() }}</span
              >
              <span class="font-semibold">{{ nombreCompleto(fila as Miembro) }}</span>
              <span
                v-if="tipo === 'miembro' && (fila as Miembro).primera_vez"
                class="tu-badge tu-badge-aviso"
                :title="$t('miembros.nuevoAyuda')"
              >{{ $t('miembros.nuevo') }}</span>
            </div>
          </template>
          <template #col-email="{ valor }">
            <span :style="{ color: 'var(--texto-suave)' }">{{ valor ?? '—' }}</span>
          </template>
          <template #col-activo="{ valor }">
            <span class="tu-badge" :class="valor ? 'tu-badge-exito' : ''">
              {{ valor ? $t('miembros.activo') : $t('miembros.inactivo') }}
            </span>
          </template>
          <template #col-acciones="{ fila }">
            <span v-if="invitados.has((fila as Miembro).id)" class="tu-badge tu-badge-exito">
              {{ $t('miembros.invitado') }}
            </span>
            <button
              v-else-if="(fila as Miembro).email"
              class="tu-btn tu-btn-fantasma text-sm"
              type="button"
              :disabled="invitandoId === (fila as Miembro).id"
              @click="invitar(fila as Miembro)"
            >
              {{ invitandoId === (fila as Miembro).id ? $t('miembros.invitando') : $t('miembros.invitar') }}
            </button>
            <span v-else class="text-xs" :style="{ color: 'var(--texto-suave)' }">{{ $t('miembros.sinCorreoInvitar') }}</span>
          </template>
        </TablaDatos>
      </div>

      <!-- Alta -->
      <div v-if="puedeGestionar" class="tu-card p-5 h-max">
        <h2 class="font-bold">{{ $t('miembros.nuevoTitulo') }}</h2>
        <form class="mt-3 space-y-3" @submit.prevent="crear">
          <div>
            <label class="tu-label" for="mn">{{ $t('miembros.nombre') }}</label>
            <input id="mn" v-model="form.nombre" class="tu-input" required />
          </div>
          <div>
            <label class="tu-label" for="msn"
              >{{ $t('miembros.segundoNombre') }}
              <span :style="{ color: 'var(--texto-suave)' }">({{ $t('miembros.opcional') }})</span></label
            >
            <input id="msn" v-model="form.segundo_nombre" class="tu-input" />
          </div>
          <div>
            <label class="tu-label" for="mpa">{{ $t('miembros.primerApellido') }}</label>
            <input id="mpa" v-model="form.primer_apellido" class="tu-input" />
          </div>
          <div>
            <label class="tu-label" for="msa"
              >{{ $t('miembros.segundoApellido') }}
              <span :style="{ color: 'var(--texto-suave)' }">({{ $t('miembros.opcional') }})</span></label
            >
            <input id="msa" v-model="form.segundo_apellido" class="tu-input" />
          </div>
          <div>
            <label class="tu-label" for="me">{{ $t('miembros.email') }}</label>
            <input id="me" v-model="form.email" class="tu-input" type="email" />
          </div>
          <div>
            <label class="tu-label" for="mt">{{ $t('miembros.tipo') }}</label>
            <select id="mt" v-model="form.tipo" class="tu-input">
              <option value="miembro">{{ $t('miembros.tipoMiembro') }}</option>
              <option value="instructor">{{ $t('miembros.tipoInstructor') }}</option>
            </select>
          </div>
          <p v-if="mensaje" class="text-sm" :style="{ color: 'var(--exito)' }">{{ $t('miembros.creado') }}</p>
          <p v-if="error" class="text-sm" style="color: var(--error)">{{ error }}</p>
          <button class="tu-btn tu-btn-primario w-full" type="submit" :disabled="guardando">
            {{ guardando ? $t('miembros.creando') : $t('miembros.crear') }}
          </button>
        </form>
      </div>
    </div>
  </section>
</template>
