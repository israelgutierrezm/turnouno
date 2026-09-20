<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const mostrarNav = computed(() => auth.autenticado)

interface Enlace {
  to: string
  clave: string
  permiso: string | null
}

// Enlaces del nav; `permiso: null` = siempre visible. Se filtran por permiso del usuario.
const enlaces: Enlace[] = [
  { to: '/', clave: 'inicio', permiso: null },
  { to: '/miembros', clave: 'miembros', permiso: 'miembros.ver' },
  { to: '/organizaciones', clave: 'organizaciones', permiso: 'organizaciones.ver' },
  { to: '/sucursales', clave: 'sucursales', permiso: 'sucursales.ver' },
  { to: '/personal', clave: 'personal', permiso: 'personal.gestionar' },
  { to: '/catalogo', clave: 'catalogo', permiso: 'catalogo.ver' },
  { to: '/recursos', clave: 'recursos', permiso: 'recursos.ver' },
  { to: '/membresias', clave: 'membresias', permiso: 'membresias.ver' },
  { to: '/ordenes', clave: 'ordenes', permiso: 'membresias.ver' },
  { to: '/agenda', clave: 'agenda', permiso: 'agenda.ver' },
  { to: '/mi-agenda', clave: 'miAgenda', permiso: 'agenda.ver' },
  { to: '/pasarelas', clave: 'pasarelas', permiso: 'pagos.configurar' },
  { to: '/comprobantes', clave: 'comprobantes', permiso: 'pagos.crear' },
]

const enlacesVisibles = computed(() =>
  enlaces.filter((enlace) => enlace.permiso === null || auth.puede(enlace.permiso)),
)

async function salir(): Promise<void> {
  await auth.cerrarSesion()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header v-if="mostrarNav" class="border-b border-slate-200 bg-white">
      <nav class="mx-auto flex max-w-4xl flex-wrap items-center gap-4 px-4 py-3">
        <span class="font-semibold">TurnoUno</span>
        <RouterLink
          v-for="enlace in enlacesVisibles"
          :key="enlace.to"
          :to="enlace.to"
          class="text-sm text-slate-600 hover:text-slate-900"
        >
          {{ t('nav.' + enlace.clave) }}
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
