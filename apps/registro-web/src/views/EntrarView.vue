<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import CampoContrasena from '@/components/CampoContrasena.vue'
import { clientIdGoogle, renderizarBotonGoogle } from '@/lib/google'
import { useSesionTenantStore } from '@/stores/sesionTenant'

const route = useRoute()
const router = useRouter()
const sesion = useSesionTenantStore()

const slug = ref(String(route.query.estudio ?? ''))
const email = ref('')
const password = ref('')
const avisoGoogle = ref(false)

const hayGoogle = clientIdGoogle() !== undefined
const contenedorGoogle = ref<HTMLElement | null>(null)

// El miembro entra a su cuenta; el staff al panel.
function destino(): { name: string } {
  return sesion.usuario?.rol === 'miembro' ? { name: 'mi-cuenta' } : { name: 'panel' }
}

async function enviar(): Promise<void> {
  try {
    await sesion.iniciarSesion(slug.value.trim(), email.value, password.value)
    void router.push(destino())
  } catch {
    // El error queda en sesion.error.
  }
}

async function entrarConGoogle(credential: string): Promise<void> {
  if (slug.value.trim() === '') {
    sesion.error = 'Escribe primero la direccion de tu estudio.'
    return
  }
  try {
    await sesion.iniciarSesionConGoogle(slug.value.trim(), credential)
    void router.push(destino())
  } catch {
    // El error queda en sesion.error.
  }
}

onMounted(() => {
  if (hayGoogle && contenedorGoogle.value !== null) {
    void renderizarBotonGoogle(contenedorGoogle.value, (c) => void entrarConGoogle(c))
  }
})
</script>

<template>
  <section class="mx-auto max-w-md px-4 py-10">
    <h1 class="text-3xl font-extrabold">{{ $t('entrar.titulo') }}</h1>
    <p class="mt-1" :style="{ color: 'var(--texto-suave)' }">{{ $t('entrar.subtitulo') }}</p>

    <form class="mt-6 tu-card p-6 space-y-4" @submit.prevent="enviar">
      <div>
        <label class="tu-label" for="slug">{{ $t('entrar.slug') }}</label>
        <input
          id="slug"
          v-model="slug"
          class="tu-input"
          :placeholder="$t('entrar.slugPh')"
          required
        />
      </div>
      <div>
        <label class="tu-label" for="email">{{ $t('entrar.email') }}</label>
        <input id="email" v-model="email" class="tu-input" type="email" required />
      </div>
      <div>
        <label class="tu-label" for="password">{{ $t('entrar.password') }}</label>
        <CampoContrasena id="password" v-model="password" autocomplete="current-password" :required="true" />
      </div>

      <p v-if="sesion.error" class="text-sm" style="color: var(--error)">{{ sesion.error }}</p>

      <button class="tu-btn tu-btn-primario w-full" type="submit" :disabled="sesion.cargando">
        {{ sesion.cargando ? $t('entrar.entrando') : $t('entrar.entrar') }}
      </button>

      <div class="flex items-center gap-3 text-xs" :style="{ color: 'var(--texto-suave)' }">
        <span class="flex-1 border-t" :style="{ borderColor: 'var(--borde)' }" />
        <span>o</span>
        <span class="flex-1 border-t" :style="{ borderColor: 'var(--borde)' }" />
      </div>

      <div v-if="hayGoogle" ref="contenedorGoogle" class="flex justify-center"></div>
      <template v-else>
        <button class="tu-btn tu-btn-fantasma w-full" type="button" @click="avisoGoogle = true">
          <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
            <path
              fill="#EA4335"
              d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"
            />
            <path
              fill="#4285F4"
              d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"
            />
            <path
              fill="#FBBC05"
              d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"
            />
            <path
              fill="#34A853"
              d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
            />
          </svg>
          {{ $t('entrar.google') }}
        </button>
        <p v-if="avisoGoogle" class="text-xs text-center" :style="{ color: 'var(--texto-suave)' }">
          {{ $t('entrar.googlePronto') }}
        </p>
      </template>
    </form>

    <p class="mt-4 text-sm text-center" :style="{ color: 'var(--texto-suave)' }">
      {{ $t('entrar.sinCuenta') }}
      <RouterLink class="tu-enlace" :to="{ name: 'registro' }">{{ $t('entrar.registrar') }}</RouterLink>
    </p>
  </section>
</template>
