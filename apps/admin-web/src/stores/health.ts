import { defineStore } from 'pinia'
import { ref } from 'vue'

import { api } from '@/lib/api'

export interface HealthCheck {
  status: string
  error?: string
}

export interface HealthReport {
  status: string
  app: string
  environment: string
  version: string
  time: string
  checks: Record<string, HealthCheck>
}

export const useHealthStore = defineStore('health', () => {
  const report = ref<HealthReport | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchHealth(): Promise<void> {
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

  return { report, loading, error, fetchHealth }
})
