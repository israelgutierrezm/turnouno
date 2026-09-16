<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import IconoNav from '@/components/IconoNav.vue'
import { useSesionTenantStore } from '@/stores/sesionTenant'
import { ACENTOS, useTemaStore } from '@/stores/tema'

const { t } = useI18n()
const tema = useTemaStore()
const sesion = useSesionTenantStore()
const router = useRouter()
const route = useRoute()

tema.inicializar()

interface Enlace {
  nombre: string
  etiqueta: string
  permiso?: string
  soloMiembro?: boolean
}

const ENLACES: Enlace[] = [
  { nombre: 'mi-cuenta', etiqueta: 'nav.miCuenta', soloMiembro: true },
  { nombre: 'panel', etiqueta: 'nav.panel', permiso: 'facturacion.ver' },
  { nombre: 'miembros', etiqueta: 'nav.miembros', permiso: 'miembros.ver' },
  { nombre: 'agenda', etiqueta: 'nav.agenda', permiso: 'agenda.ver' },
  { nombre: 'ventas', etiqueta: 'nav.ventas', permiso: 'productos.ver' },
  { nombre: 'documentos', etiqueta: 'nav.documentos', permiso: 'documentos.subir' },
  { nombre: 'formularios', etiqueta: 'nav.formularios', permiso: 'formularios.responder' },
  { nombre: 'pasarelas', etiqueta: 'nav.pasarelas', permiso: 'pagos.configurar' },
  { nombre: 'integraciones', etiqueta: 'nav.integraciones', permiso: 'integraciones.configurar' },
  { nombre: 'configuracion', etiqueta: 'nav.configuracion', permiso: 'estudio.gestionar' },
]

const enlaces = computed(() =>
  ENLACES.filter((e) => {
    if (e.soloMiembro === true) {
      return sesion.usuario?.rol === 'miembro'
    }
    return e.permiso === undefined || sesion.puede(e.permiso)
  }),
)

const hogar = computed(() =>
  sesion.usuario?.rol === 'miembro' ? { name: 'mi-cuenta' } : { name: 'panel' },
)

const puedeConfigurar = computed(() => sesion.puede('estudio.gestionar'))

const enlaceActivo = computed(() => ENLACES.find((e) => e.nombre === route.name) ?? null)
const tituloSeccion = computed(() =>
  enlaceActivo.value ? t(enlaceActivo.value.etiqueta) : (sesion.estudio?.nombre ?? ''),
)

function siglas(nombre: string | undefined): string {
  return (
    (nombre ?? '')
      .split(' ')
      .slice(0, 2)
      .map((p) => p.charAt(0))
      .join('')
      .toUpperCase() || '·'
  )
}
const inicialesEstudio = computed(() => siglas(sesion.estudio?.nombre))
const inicialesUsuario = computed(() => siglas(sesion.usuario?.nombre))

// Estado de la interfaz.
const menuLateral = ref(false) // cajón en móvil
const compacto = ref(false) // barra contraída (solo iconos) en escritorio
const menuPerfil = ref(false)
const menuApariencia = ref(false)

// La contracción solo aplica en escritorio; con el cajón abierto se ve completo.
const compactoEfectivo = computed(() => compacto.value && !menuLateral.value)

function alternarCompacto(): void {
  compacto.value = !compacto.value
  try {
    localStorage.setItem('tu.barra.compacta', compacto.value ? '1' : '0')
  } catch {
    // Ignora si no hay localStorage.
  }
}

async function salir(): Promise<void> {
  menuPerfil.value = false
  await sesion.cerrarSesion()
  void router.push({ name: 'inicio' })
}

onMounted(() => {
  try {
    compacto.value = localStorage.getItem('tu.barra.compacta') === '1'
  } catch {
    // Ignora.
  }
})
</script>

<template>
  <!-- ===================== APP AUTENTICADA (panel con barra lateral) ===================== -->
  <div v-if="sesion.autenticado" class="flex min-h-screen">
    <!-- Velo del cajón (móvil) -->
    <div
      v-if="menuLateral"
      class="fixed inset-0 z-40 bg-black/50 lg:hidden"
      @click="menuLateral = false"
    />

    <!-- Barra lateral (oscura) -->
    <aside
      class="fixed lg:sticky top-0 z-50 h-screen w-64 shrink-0 flex flex-col transition-all duration-200"
      :class="[
        compacto ? 'lg:w-[76px]' : 'lg:w-64',
        menuLateral ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
      ]"
      :style="{ background: 'var(--barra)', color: 'var(--barra-texto)' }"
    >
      <!-- Marca -->
      <RouterLink
        :to="hogar"
        class="flex items-center gap-3 h-16 px-4 shrink-0 border-b"
        :style="{ borderColor: 'var(--barra-borde)' }"
        @click="menuLateral = false"
      >
        <img
          v-if="sesion.estudio?.logo_url"
          :src="sesion.estudio.logo_url"
          :alt="sesion.estudio?.nombre"
          class="h-9 w-9 rounded-xl object-cover shrink-0"
        />
        <span
          v-else
          class="h-9 w-9 rounded-xl inline-flex items-center justify-center text-white text-sm font-bold shrink-0"
          :style="{ background: 'var(--barra-activo)' }"
          aria-hidden="true"
          >{{ inicialesEstudio }}</span
        >
        <span v-show="!compactoEfectivo" class="min-w-0">
          <span class="block text-sm font-semibold text-white truncate">{{
            sesion.estudio?.nombre ?? $t('marca')
          }}</span>
          <span class="block text-[11px] opacity-60 truncate">{{ $t('marca') }}</span>
        </span>
      </RouterLink>

      <!-- Navegación -->
      <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-1">
        <RouterLink
          v-for="e in enlaces"
          :key="e.nombre"
          class="tu-side-link"
          :class="{ 'lg:justify-center': compactoEfectivo }"
          :to="{ name: e.nombre }"
          :title="compactoEfectivo ? $t(e.etiqueta) : undefined"
          @click="menuLateral = false"
        >
          <IconoNav :nombre="e.nombre" :tam="20" class="shrink-0" />
          <span v-show="!compactoEfectivo" class="truncate">{{ $t(e.etiqueta) }}</span>
        </RouterLink>
      </nav>

      <!-- Contraer (solo escritorio) -->
      <div class="hidden lg:block p-3 border-t" :style="{ borderColor: 'var(--barra-borde)' }">
        <button
          type="button"
          class="tu-side-link w-full"
          :class="{ 'lg:justify-center': compactoEfectivo }"
          :title="$t('nav.contraer')"
          @click="alternarCompacto"
        >
          <span class="shrink-0" aria-hidden="true">{{ compacto ? '»' : '«' }}</span>
          <span v-show="!compactoEfectivo">{{ $t('nav.contraer') }}</span>
        </button>
      </div>
    </aside>

    <!-- Columna principal -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Encabezado (claro, translúcido) -->
      <header
        class="sticky top-0 z-30 h-16 flex items-center justify-between gap-3 px-4 sm:px-6 border-b backdrop-blur"
        :style="{
          background: 'color-mix(in srgb, var(--superficie) 85%, transparent)',
          borderColor: 'var(--borde)',
        }"
      >
        <div class="flex items-center gap-3 min-w-0">
          <button
            type="button"
            class="lg:hidden tu-icono-btn"
            :aria-label="$t('nav.menu')"
            @click="menuLateral = true"
          >
            <span aria-hidden="true">☰</span>
          </button>
          <span
            v-if="enlaceActivo"
            class="hidden sm:inline-flex h-9 w-9 rounded-xl items-center justify-center shrink-0"
            :style="{ background: 'var(--primario-suave)', color: 'var(--primario-fuerte)' }"
            aria-hidden="true"
          >
            <IconoNav :nombre="enlaceActivo.nombre" :tam="18" />
          </span>
          <h1 class="text-base font-semibold truncate">{{ tituloSeccion }}</h1>
        </div>

        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
          <!-- Apariencia -->
          <div class="relative">
            <button
              type="button"
              class="tu-icono-btn"
              :aria-label="$t('tema.apariencia')"
              :title="$t('tema.apariencia')"
              @click="menuApariencia = !menuApariencia; menuPerfil = false"
            >
              <IconoNav nombre="configuracion" :tam="18" />
            </button>
            <div
              v-if="menuApariencia"
              class="absolute right-0 top-full mt-2 w-64 tu-card p-4 z-50 space-y-4"
            >
              <div>
                <p class="tu-label">{{ $t('tema.modo') }}</p>
                <div class="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    class="tu-btn"
                    :class="!tema.esOscuro ? 'tu-btn-primario' : 'tu-btn-fantasma'"
                    @click="tema.esOscuro && tema.alternarModo()"
                  >
                    ☀ {{ $t('tema.claroCorto') }}
                  </button>
                  <button
                    type="button"
                    class="tu-btn"
                    :class="tema.esOscuro ? 'tu-btn-primario' : 'tu-btn-fantasma'"
                    @click="!tema.esOscuro && tema.alternarModo()"
                  >
                    ☾ {{ $t('tema.oscuroCorto') }}
                  </button>
                </div>
              </div>

              <div>
                <p class="tu-label">{{ $t('tema.densidad') }}</p>
                <div class="flex items-center gap-2">
                  <button type="button" class="tu-btn tu-btn-fantasma flex-1" @click="tema.ajustarDensidad(-1)">−</button>
                  <span class="text-sm" :style="{ color: 'var(--texto-suave)' }">{{ tema.densidad }}</span>
                  <button type="button" class="tu-btn tu-btn-fantasma flex-1" @click="tema.ajustarDensidad(1)">+</button>
                </div>
              </div>

              <div>
                <p class="tu-label">{{ $t('tema.acento') }}</p>
                <div class="flex items-center gap-2">
                  <button
                    v-for="a in ACENTOS"
                    :key="a.nombre"
                    type="button"
                    class="h-6 w-6 rounded-full border-2 transition-transform hover:scale-110"
                    :style="{
                      background: a.hex ?? '#4f46e5',
                      borderColor: tema.acento === a.hex ? 'var(--texto)' : 'transparent',
                    }"
                    :title="a.nombre"
                    :aria-label="a.nombre"
                    @click="tema.fijarAcento(a.hex)"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Perfil -->
          <div class="relative">
            <button
              type="button"
              class="flex items-center gap-2 rounded-xl p-1 pr-2 hover:bg-black/5"
              :aria-expanded="menuPerfil"
              @click="menuPerfil = !menuPerfil; menuApariencia = false"
            >
              <span
                class="h-8 w-8 rounded-lg inline-flex items-center justify-center text-white text-xs font-bold shrink-0"
                :style="{ background: 'var(--primario)' }"
                aria-hidden="true"
                >{{ inicialesUsuario }}</span
              >
              <span class="hidden sm:block text-left leading-tight">
                <span class="block text-[13px] font-semibold truncate max-w-[8rem]">{{ sesion.usuario?.nombre }}</span>
                <span class="block text-[11px] truncate" :style="{ color: 'var(--texto-suave)' }">{{ sesion.usuario?.rol }}</span>
              </span>
            </button>
            <div
              v-if="menuPerfil"
              class="absolute right-0 top-full mt-2 w-60 tu-card p-1.5 z-50"
            >
              <div class="px-2.5 py-2 border-b" :style="{ borderColor: 'var(--borde)' }">
                <p class="text-sm font-semibold truncate">{{ sesion.usuario?.nombre }}</p>
                <p class="text-xs truncate" :style="{ color: 'var(--texto-suave)' }">{{ sesion.usuario?.email }}</p>
              </div>
              <RouterLink
                v-if="puedeConfigurar"
                class="tu-menu-item mt-1"
                :to="{ name: 'configuracion' }"
                @click="menuPerfil = false"
              >
                <IconoNav nombre="configuracion" :tam="16" />
                {{ $t('nav.configuracion') }}
              </RouterLink>
              <button type="button" class="tu-menu-item" style="color: var(--error)" @click="salir">
                <span aria-hidden="true">⎋</span>
                {{ $t('panel.salir') }}
              </button>
            </div>
          </div>
        </div>
      </header>

      <main class="flex-1" :style="{ background: 'var(--fondo)' }">
        <div class="mx-auto max-w-7xl">
          <RouterView />
        </div>
      </main>
    </div>

    <!-- Cierra menús flotantes al hacer clic fuera -->
    <div
      v-if="menuPerfil || menuApariencia"
      class="fixed inset-0 z-20"
      @click="menuPerfil = false; menuApariencia = false"
    />
  </div>

  <!-- ===================== APP PÚBLICA ===================== -->
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
</template>

<style>
/* Enlaces del sidebar OSCURO (estilo panel). */
.tu-side-link {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.6rem 0.7rem;
  border-radius: 0.75rem;
  font-weight: 500;
  font-size: 0.9rem;
  color: var(--barra-texto);
  text-decoration: none;
  cursor: pointer;
  white-space: nowrap;
  transition:
    background-color 0.15s ease,
    color 0.15s ease;
}
.tu-side-link:hover {
  background: var(--barra-suave);
  color: #ffffff;
}
.tu-side-link.router-link-active {
  background: var(--barra-activo);
  color: #ffffff;
  font-weight: 600;
}

/* Botón de icono del encabezado. */
.tu-icono-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 2.25rem;
  width: 2.25rem;
  border-radius: 0.7rem;
  color: var(--texto-suave);
  cursor: pointer;
}
.tu-icono-btn:hover {
  background: var(--superficie-2);
  color: var(--texto);
}

/* Elementos de menús flotantes (perfil, apariencia) sobre fondo claro. */
.tu-menu-item {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  padding: 0.5rem 0.6rem;
  border-radius: 0.6rem;
  font-weight: 600;
  font-size: 0.88rem;
  color: var(--texto);
  text-decoration: none;
  cursor: pointer;
  text-align: left;
}
.tu-menu-item:hover {
  background: var(--superficie-2);
}
</style>
