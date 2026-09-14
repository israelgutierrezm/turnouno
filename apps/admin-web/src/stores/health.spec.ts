import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { api } from '@/lib/api'
import { useHealthStore } from '@/stores/health'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
}))

describe('health store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('stores the report on success', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      data: {
        status: 'ok',
        app: 'TurnoUno',
        environment: 'testing',
        version: '0.1.0',
        time: '2026-01-01T00:00:00+00:00',
        checks: { database: { status: 'ok' } },
      },
    })

    const store = useHealthStore()
    await store.fetchHealth()

    expect(store.report?.status).toBe('ok')
    expect(store.error).toBeNull()
    expect(store.loading).toBe(false)
  })

  it('captures the message on failure', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('network down'))

    const store = useHealthStore()
    await store.fetchHealth()

    expect(store.report).toBeNull()
    expect(store.error).toBe('network down')
  })
})
