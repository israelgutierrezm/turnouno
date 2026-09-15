<script setup lang="ts">
import { computed } from 'vue'
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
  permiso?: string
}

const ENLACES: Enlace[] = [
  { nombre: 'panel', etiqueta: 'nav.panel' },
  { nombre: 'miembros', etiqueta: 'nav.miembros', permiso: 'miembros.ver' },
]

const enlaces = computed(() =>
  ENLACES.filter((e) => e.permiso === undefined || sesion.puede(e.permiso)),
)

async function salir(): Promise<void> {
  await sesion.cerrarSesion()
  void router.push({ name: 'inicio' })
}
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <header class="border-b" :style="{ borderColor: 'var(--borde)', background: 'var(--superficie)' }">
      <div class="mx-auto max-w-6xl px-4 h-16 flex items-center justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
          <RouterLink
            :to="sesion.autenticado ? { name: 'panel' } : { name: 'inicio' }"
            class="flex items-center gap-2 font-bold text-lg shrink-0"
          >
            <span
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-white"
              :style="{ background: 'var(--primario)' }"
              aria-hidden="true"
              >T</span
            >
            <span class="hidden sm:inline">{{ $t('marca') }}</span>
          </RouterLink>

          <nav v-if="sesion.autenticado" class="flex items-center gap-1 overflow-x-auto">
            <RouterLink
              v-for="e in enlaces"
              :key="e.nombre"
              class="tu-nav-link"
              :to="{ name: e.nombre }"
            >
              {{ $t(e.etiqueta) }}
            </RouterLink>
          </nav>
        </div>

        <nav class="flex items-center gap-1 sm:gap-2 shrink-0">
          <RouterLink
            v-if="!sesion.autenticado"
            class="tu-btn tu-btn-fantasma hidden sm:inline-flex"
            :to="{ name: 'directorio' }"
          >
            {{ $t('nav.directorio') }}
          </RouterLink>

          <div
            class="flex items-center rounded-lg border"
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

          <button
            type="button"
            class="tu-btn tu-btn-fantasma"
            :title="tema.esOscuro ? $t('tema.claro') : $t('tema.oscuro')"
            :aria-label="tema.esOscuro ? $t('tema.claro') : $t('tema.oscuro')"
            @click="tema.alternarModo()"
          >
            <span aria-hidden="true">{{ tema.esOscuro ? '☀' : '☾' }}</span>
          </button>

          <button v-if="sesion.autenticado" type="button" class="tu-btn tu-btn-fantasma" @click="salir">
            {{ $t('panel.salir') }}
          </button>
          <RouterLink v-else class="tu-btn tu-btn-primario" :to="{ name: 'registro' }">
            {{ $t('nav.registrar') }}
          </RouterLink>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <RouterView />
    </main>

    <footer
      class="border-t text-sm"
      :style="{ borderColor: 'var(--borde)', color: 'var(--texto-suave)' }"
    >
      <div class="mx-auto max-w-6xl px-4 py-6 flex items-center justify-between">
        <span>© {{ new Date().getFullYear() }} {{ $t('marca') }}</span>
        <RouterLink class="tu-enlace" :to="{ name: 'directorio' }">{{ $t('nav.directorio') }}</RouterLink>
      </div>
    </footer>
  </div>
</template>
