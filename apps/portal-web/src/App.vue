<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { api } from '@/lib/api'

interface HealthReport {
  status: string
  app: string
  environment: string
  version: string
}

const report = ref<HealthReport | null>(null)
const error = ref<string | null>(null)
const loading = ref(false)

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get<HealthReport>('/api/v1/health')
    report.value = data
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Request failed'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <main class="min-h-screen bg-white text-slate-900">
    <div class="mx-auto max-w-2xl space-y-4 px-4 py-10">
      <h1 class="text-2xl font-semibold">TurnoUno · Portal</h1>
      <p class="text-slate-600">Member &amp; guardian portal shell (Sprint 0).</p>

      <button
        class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
        :disabled="loading"
        @click="load()"
      >
        {{ loading ? 'Checking…' : 'Check API health' }}
      </button>

      <p v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <p v-else-if="report" class="text-sm">
        API: <span class="font-medium">{{ report.status }}</span> · {{ report.app }} ({{
          report.environment
        }}) v{{ report.version }}
      </p>
    </div>
  </main>
</template>
