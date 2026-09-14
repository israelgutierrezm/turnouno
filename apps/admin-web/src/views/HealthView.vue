<script setup lang="ts">
import { onMounted } from 'vue'

import { useHealthStore } from '@/stores/health'

const health = useHealthStore()

onMounted(() => {
  void health.fetchHealth()
})
</script>

<template>
  <section class="space-y-4">
    <div class="flex items-center gap-3">
      <h2 class="text-xl font-semibold">API Health</h2>
      <button
        class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
        :disabled="health.loading"
        @click="health.fetchHealth()"
      >
        {{ health.loading ? 'Checking…' : 'Refresh' }}
      </button>
    </div>

    <p v-if="health.error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
      {{ health.error }}
    </p>

    <div v-if="health.report" class="space-y-3">
      <p class="text-sm">
        Overall:
        <span :class="health.report.status === 'ok' ? 'text-green-600' : 'text-amber-600'">
          {{ health.report.status }}
        </span>
        · {{ health.report.app }} ({{ health.report.environment }}) v{{ health.report.version }}
      </p>

      <ul class="divide-y rounded-md border border-slate-200 bg-white">
        <li
          v-for="(check, name) in health.report.checks"
          :key="name"
          class="flex items-center justify-between px-3 py-2 text-sm"
        >
          <span class="font-medium">{{ name }}</span>
          <span :class="check.status === 'ok' ? 'text-green-600' : 'text-red-600'">
            {{ check.status }}
          </span>
        </li>
      </ul>
    </div>
  </section>
</template>
