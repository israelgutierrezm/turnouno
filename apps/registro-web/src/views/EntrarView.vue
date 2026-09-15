<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { useSesionTenantStore } from '@/stores/sesionTenant'

const route = useRoute()
const router = useRouter()
const sesion = useSesionTenantStore()

const slug = ref(String(route.query.estudio ?? ''))
const email = ref('')
const password = ref('')
const avisoGoogle = ref(false)

async function enviar(): Promise<void> {
  try {
    await sesion.iniciarSesion(slug.value.trim(), email.value, password.value)
    void router.push({ name: 'panel' })
  } catch {
    // El error queda en sesion.error.
  }
}
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
        <input id="password" v-model="password" class="tu-input" type="password" required />
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

      <button class="tu-btn tu-btn-fantasma w-full" type="button" @click="avisoGoogle = true">
        <span aria-hidden="true">G</span>
        {{ $t('entrar.google') }}
      </button>
      <p v-if="avisoGoogle" class="text-xs text-center" :style="{ color: 'var(--texto-suave)' }">
        {{ $t('entrar.googlePronto') }}
      </p>
    </form>

    <p class="mt-4 text-sm text-center" :style="{ color: 'var(--texto-suave)' }">
      {{ $t('entrar.sinCuenta') }}
      <RouterLink class="tu-enlace" :to="{ name: 'registro' }">{{ $t('entrar.registrar') }}</RouterLink>
    </p>
  </section>
</template>
