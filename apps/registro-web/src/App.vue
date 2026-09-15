<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'

import { useTemaStore } from '@/stores/tema'

const tema = useTemaStore()

// Aplica el tema y la densidad guardados (contexto con Pinia ya activo).
tema.inicializar()
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <header class="border-b" :style="{ borderColor: 'var(--borde)', background: 'var(--superficie)' }">
      <div class="mx-auto max-w-6xl px-4 h-16 flex items-center justify-between gap-4">
        <RouterLink :to="{ name: 'inicio' }" class="flex items-center gap-2 font-bold text-lg">
          <span
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-white"
            :style="{ background: 'var(--primario)' }"
            aria-hidden="true"
            >T</span
          >
          <span>{{ $t('marca') }}</span>
        </RouterLink>

        <nav class="flex items-center gap-1 sm:gap-2">
          <RouterLink class="tu-btn tu-btn-fantasma hidden sm:inline-flex" :to="{ name: 'directorio' }">
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

          <RouterLink class="tu-btn tu-btn-primario" :to="{ name: 'registro' }">
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
