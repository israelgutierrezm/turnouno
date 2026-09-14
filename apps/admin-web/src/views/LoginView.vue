<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const email = ref('owner@turnouno.test')
const password = ref('')

async function enviar(): Promise<void> {
  try {
    await auth.iniciarSesion(email.value, password.value)
    await router.push({ name: 'inicio' })
  } catch {
    // El mensaje de error se muestra desde el store.
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4">
    <form
      class="w-full max-w-sm space-y-4 rounded-lg border border-slate-200 bg-white p-6"
      @submit.prevent="enviar"
    >
      <h1 class="text-xl font-semibold">{{ t('auth.login.titulo') }}</h1>

      <label class="block text-sm">
        <span class="text-slate-600">{{ t('auth.login.email') }}</span>
        <input
          v-model="email"
          type="email"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
        />
      </label>

      <label class="block text-sm">
        <span class="text-slate-600">{{ t('auth.login.password') }}</span>
        <input
          v-model="password"
          type="password"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
        />
      </label>

      <p v-if="auth.error" class="rounded-md bg-red-50 p-2 text-sm text-red-700">{{ auth.error }}</p>

      <button
        type="submit"
        :disabled="auth.cargando"
        class="w-full rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
      >
        {{ auth.cargando ? t('comun.cargando') : t('auth.login.entrar') }}
      </button>
    </form>
  </div>
</template>
