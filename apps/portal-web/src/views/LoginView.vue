<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')

async function entrar(): Promise<void> {
  try {
    await auth.iniciarSesion(email.value, password.value)
    await router.push({ name: 'perfil' })
  } catch {
    // El mensaje de error vive en el store.
  }
}
</script>

<template>
  <div class="mx-auto mt-16 max-w-sm rounded-lg border border-slate-200 bg-white p-6">
    <h1 class="text-lg font-semibold">{{ t('login.titulo') }}</h1>
    <form class="mt-4 space-y-3" @submit.prevent="entrar">
      <label class="block text-sm">
        <span class="text-slate-600">{{ t('login.email') }}</span>
        <input
          v-model="email"
          type="email"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
        />
      </label>
      <label class="block text-sm">
        <span class="text-slate-600">{{ t('login.password') }}</span>
        <input
          v-model="password"
          type="password"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
        />
      </label>
      <p v-if="auth.error" class="text-sm text-red-700">{{ auth.error }}</p>
      <button
        type="submit"
        :disabled="auth.cargando"
        class="w-full rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
      >
        {{ t('login.entrar') }}
      </button>
    </form>
  </div>
</template>
