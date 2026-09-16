<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'

import { useSesionTenantStore } from '@/stores/sesionTenant'
import { useTemaStore } from '@/stores/tema'

const tema = useTemaStore()
const sesion = useSesionTenantStore()
const router = useRouter()

// Aplica el tema y la densidad guardados (contexto con Pinia ya activo).
tema.inicializar()

interface Enlace {
  nombre: string
  etiqueta: string
  icono: string
  permiso?: string
  soloMiembro?: boolean
}

const ENLACES: Enlace[] = [
  { nombre: 'mi-cuenta', etiqueta: 'nav.miCuenta', icono: '🏠', soloMiembro: true },
  { nombre: 'panel', etiqueta: 'nav.panel', icono: '📊', permiso: 'facturacion.ver' },
  { nombre: 'miembros', etiqueta: 'nav.miembros', icono: '👥', permiso: 'miembros.ver' },
  { nombre: 'agenda', etiqueta: 'nav.agenda', icono: '📅', permiso: 'agenda.ver' },
  { nombre: 'ventas', etiqueta: 'nav.ventas', icono: '🛒', permiso: 'productos.ver' },
  { nombre: 'documentos', etiqueta: 'nav.documentos', icono: '📄', permiso: 'documentos.subir' },
  { nombre: 'formularios', etiqueta: 'nav.formularios', icono: '📝', permiso: 'formularios.responder' },
  { nombre: 'pasarelas', etiqueta: 'nav.pasarelas', icono: '💳', permiso: 'pagos.configurar' },
  { nombre: 'integraciones', etiqueta: 'nav.integraciones', icono: '🔌', permiso: 'integraciones.configurar' },
  { nombre: 'configuracion', etiqueta: 'nav.configuracion', icono: '⚙️', permiso: 'estudio.gestionar' },
]

const enlaces = computed(() =>
  ENLACES.filter((e) => {
    if (e.soloMiembro === true) {
      return sesion.usuario?.rol === 'miembro'
    }
    return e.permiso === undefined || sesion.puede(e.permiso)
  }),
)

// Inicio segun rol: el miembro va a su cuenta; el staff al panel.
const hogar = computed(() =>
  sesion.usuario?.rol === 'miembro' ? { name: 'mi-cuenta' } : { name: 'panel' },
)

const puedeConfigurar = computed(() => sesion.puede('estudio.gestionar'))

const iniciales = computed(() => {
  const nombre = sesion.usuario?.nombre ?? ''
  return (
    nombre
      .split(' ')
      .slice(0, 2)
      .map((p) => p.charAt(0))
      .join('')
      .toUpperCase() || '·'
  )
})

const menuLateral = ref(false) // drawer en movil
const menuPerfil = ref(false) // dropdown de perfil

async function salir(): Promise<void> {
  menuPerfil.value = false
  await sesion.cerrarSesion()
  void router.push({ name: 'inicio' })
}
</script>

<template>
  <!-- ======================= APP AUTENTICADA: layout con barra lateral ======================= -->
  <div v-if="sesion.autenticado" class="flex min-h-screen">
    <!-- Fondo oscuro del drawer en movil -->
    <div
      v-if="menuLateral"
      class="fixed inset-0 z-30 bg-black/40 lg:hidden"
      @click="menuLateral = false"
    />

    <!-- Barra lateral -->
    <aside
      class="fixed lg:sticky top-0 z-40 h-screen w-64 shrink-0 flex flex-col border-r transition-transform lg:translate-x-0"
      :class="menuLateral ? 'translate-x-0' : '-translate-x-full'"
      :style="{ background: 'var(--superficie)', borderColor: 'var(--borde)' }"
    >
      <!-- Marca -->
      <RouterLink
        :to="hogar"
        class="flex items-center gap-2 px-4 h-16 font-bold text-lg shrink-0 border-b"
        :style="{ borderColor: 'var(--borde)' }"
        @click="menuLateral = false"
      >
        <span
          class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-white shrink-0"
          :style="{ background: 'var(--primario)' }"
          aria-hidden="true"
          >T</span
        >
        <span class="truncate">{{ sesion.estudio?.nombre ?? $t('marca') }}</span>
      </RouterLink>

      <!-- Navegacion -->
      <nav class="flex-1 overflow-y-auto p-3 space-y-1">
        <RouterLink
          v-for="e in enlaces"
          :key="e.nombre"
          class="tu-side-link"
          :to="{ name: e.nombre }"
          @click="menuLateral = false"
        >
          <span class="text-lg leading-none w-6 text-center" aria-hidden="true">{{ e.icono }}</span>
          <span class="truncate">{{ $t(e.etiqueta) }}</span>
        </RouterLink>
      </nav>

      <!-- Pie: tema + perfil -->
      <div class="border-t p-3 space-y-3" :style="{ borderColor: 'var(--borde)' }">
        <!-- Controles de tema -->
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="tu-btn tu-btn-fantasma flex-1"
            :title="tema.esOscuro ? $t('tema.claro') : $t('tema.oscuro')"
            @click="tema.alternarModo()"
          >
            <span aria-hidden="true">{{ tema.esOscuro ? '☀' : '☾' }}</span>
            <span class="text-sm">{{ tema.esOscuro ? $t('tema.claro') : $t('tema.oscuro') }}</span>
          </button>
          <div
            class="flex items-center rounded-lg border shrink-0"
            :style="{ borderColor: 'var(--borde)' }"
            role="group"
            :aria-label="$t('tema.masDensidad')"
          >
            <button
              type="button"
              class="px-2.5 py-1.5 text-lg leading-none"
              :title="$t('tema.menosDensidad')"
              :aria-label="$t('tema.menosDensidad')"
              @click="tema.ajustarDensidad(-1)"
            >
              −
            </button>
            <button
              type="button"
              class="px-2.5 py-1.5 text-lg leading-none border-l"
              :style="{ borderColor: 'var(--borde)' }"
              :title="$t('tema.masDensidad')"
              :aria-label="$t('tema.masDensidad')"
              @click="tema.ajustarDensidad(1)"
            >
              +
            </button>
          </div>
        </div>

        <!-- Boton de perfil -->
        <div class="relative">
          <button
            type="button"
            class="w-full flex items-center gap-3 rounded-lg p-2 text-left transition-colors"
            :style="{ background: menuPerfil ? 'var(--superficie-2)' : 'transparent' }"
            :aria-expanded="menuPerfil"
            @click="menuPerfil = !menuPerfil"
          >
            <span
              class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white font-bold text-sm shrink-0"
              :style="{ background: 'var(--primario)' }"
              aria-hidden="true"
              >{{ iniciales }}</span
            >
            <span class="min-w-0 flex-1">
              <span class="block font-semibold text-sm truncate">{{ sesion.usuario?.nombre }}</span>
              <span class="block text-xs truncate" :style="{ color: 'var(--texto-suave)' }">{{
                sesion.usuario?.rol
              }}</span>
            </span>
            <span class="text-xs shrink-0" :style="{ color: 'var(--texto-suave)' }" aria-hidden="true">▾</span>
          </button>

          <!-- Menu del perfil -->
          <div
            v-if="menuPerfil"
            class="absolute bottom-full left-0 right-0 mb-2 tu-card p-1.5 z-10"
          >
            <p class="px-2.5 py-1.5 text-xs truncate" :style="{ color: 'var(--texto-suave)' }">
              {{ sesion.usuario?.email }}
            </p>
            <RouterLink
              v-if="puedeConfigurar"
              class="tu-side-link"
              :to="{ name: 'configuracion' }"
              @click="menuPerfil = false"
            >
              <span class="text-lg leading-none w-6 text-center" aria-hidden="true">⚙️</span>
              <span>{{ $t('nav.configuracion') }}</span>
            </RouterLink>
            <button
              type="button"
              class="tu-side-link w-full"
              style="color: var(--error)"
              @click="salir"
            >
              <span class="text-lg leading-none w-6 text-center" aria-hidden="true">⎋</span>
              <span>{{ $t('panel.salir') }}</span>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Columna principal -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Barra superior (movil): hamburguesa + marca -->
      <header
        class="lg:hidden sticky top-0 z-20 flex items-center gap-3 h-14 px-4 border-b"
        :style="{ background: 'var(--superficie)', borderColor: 'var(--borde)' }"
      >
        <button
          type="button"
          class="tu-btn tu-btn-fantasma px-2.5"
          :aria-label="$t('nav.menu')"
          @click="menuLateral = true"
        >
          <span aria-hidden="true">☰</span>
        </button>
        <span class="font-bold truncate">{{ sesion.estudio?.nombre ?? $t('marca') }}</span>
      </header>

      <main class="flex-1">
        <RouterView />
      </main>
    </div>
  </div>

  <!-- ======================= APP PUBLICA: encabezado simple ======================= -->
  <div v-else class="min-h-screen flex flex-col">
    <header class="border-b" :style="{ borderColor: 'var(--borde)', background: 'var(--superficie)' }">
      <div class="mx-auto max-w-6xl px-4 h-16 flex items-center justify-between gap-4">
        <RouterLink :to="{ name: 'inicio' }" class="flex items-center gap-2 font-bold text-lg shrink-0">
          <span
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-white"
            :style="{ background: 'var(--primario)' }"
            aria-hidden="true"
            >T</span
          >
          <span class="hidden sm:inline">{{ $t('marca') }}</span>
        </RouterLink>

        <nav class="flex items-center gap-1 sm:gap-2 shrink-0">
          <RouterLink class="tu-btn tu-btn-fantasma hidden sm:inline-flex" :to="{ name: 'directorio' }">
            {{ $t('nav.directorio') }}
          </RouterLink>
          <RouterLink class="tu-btn tu-btn-fantasma" :to="{ name: 'entrar' }">
            {{ $t('nav.entrar') }}
          </RouterLink>

          <button
            type="button"
            class="tu-btn tu-btn-fantasma px-2.5"
            :title="tema.esOscuro ? $t('tema.claro') : $t('tema.oscuro')"
            :aria-label="tema.esOscuro ? $t('tema.claro') : $t('tema.oscuro')"
            @click="tema.alternarModo()"
          >
            <span aria-hidden="true">{{ tema.esOscuro ? '☀' : '☾' }}</span>
          </button>

          <RouterLink class="tu-btn tu-btn-primario" :to="{ name: 'registro' }">
            {{ $t('nav.registrar') }}
          </RouterLink>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <RouterView />
    </main>

    <footer class="border-t text-sm" :style="{ borderColor: 'var(--borde)', color: 'var(--texto-suave)' }">
      <div class="mx-auto max-w-6xl px-4 py-6 flex items-center justify-between">
        <span>© {{ new Date().getFullYear() }} {{ $t('marca') }}</span>
        <RouterLink class="tu-enlace" :to="{ name: 'directorio' }">{{ $t('nav.directorio') }}</RouterLink>
      </div>
    </footer>
  </div>

  <!-- Cierra el menu de perfil al hacer clic fuera -->
  <div v-if="menuPerfil" class="fixed inset-0 z-[5]" @click="menuPerfil = false" />
</template>

<style>
.tu-side-link {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.55rem 0.7rem;
  border-radius: 0.6rem;
  font-weight: 600;
  font-size: 0.92rem;
  color: var(--texto-suave);
  text-decoration: none;
  cursor: pointer;
}
.tu-side-link:hover {
  background: var(--superficie-2);
  color: var(--texto);
}
.tu-side-link.router-link-active {
  background: var(--primario-suave);
  color: var(--primario-fuerte);
}
</style>
