<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const mostrarNav = computed(() => auth.autenticado)

async function salir(): Promise<void> {
  await auth.cerrarSesion()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header v-if="mostrarNav" class="border-b border-slate-200 bg-white">
      <nav class="mx-auto flex max-w-4xl items-center gap-4 px-4 py-3">
        <span class="font-semibold">TurnoUno</span>
        <RouterLink to="/" class="text-sm text-slate-600 hover:text-slate-900">
          {{ t('nav.inicio') }}
        </RouterLink>
        <RouterLink to="/miembros" class="text-sm text-slate-600 hover:text-slate-900">
          {{ t('nav.miembros') }}
        </RouterLink>
        <RouterLink to="/familias" class="text-sm text-slate-600 hover:text-slate-900">
          {{ t('nav.familias') }}
        </RouterLink>
        <RouterLink to="/organizaciones" class="text-sm text-slate-600 hover:text-slate-900">
          {{ t('nav.organizaciones') }}
        </RouterLink>
        <RouterLink to="/sucursales" class="text-sm text-slate-600 hover:text-slate-900">
          {{ t('nav.sucursales') }}
        </RouterLink>
        <RouterLink to="/personal" class="text-sm text-slate-600 hover:text-slate-900">
          {{ t('nav.personal') }}
        </RouterLink>
        <span class="ml-auto text-sm text-slate-500">{{ auth.me?.tenant_actual?.nombre }}</span>
        <button class="text-sm text-slate-600 hover:text-slate-900" @click="salir">
          {{ t('nav.salir') }}
        </button>
      </nav>
    </header>

    <main :class="mostrarNav ? 'mx-auto max-w-4xl px-4 py-6' : ''">
      <RouterView />
    </main>
  </div>
</template>
