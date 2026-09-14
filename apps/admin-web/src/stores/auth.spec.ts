import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { api, obtenerCsrf } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn() },
  obtenerCsrf: vi.fn(),
}))

const meDemo = {
  usuario: { id: 'u1', nombre: 'Demo', email: 'demo@turnouno.test' },
  tenant_actual: { id: 't1', nombre: 'Acme', slug: 'acme' },
  persona: null,
  pertenencias: [],
  roles: ['propietario'],
  permisos: ['sucursales.ver', 'sucursales.gestionar'],
}

describe('store de auth', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('inicia sesion y carga el usuario', async () => {
    vi.mocked(obtenerCsrf).mockResolvedValueOnce()
    vi.mocked(api.post).mockResolvedValueOnce({ data: {} })
    vi.mocked(api.get).mockResolvedValueOnce({ data: meDemo })

    const auth = useAuthStore()
    await auth.iniciarSesion('demo@turnouno.test', 'password')

    expect(auth.autenticado).toBe(true)
    expect(auth.puede('sucursales.gestionar')).toBe(true)
    expect(auth.puede('pagos.reembolsar')).toBe(false)
  })

  it('captura el mensaje de error al fallar el login', async () => {
    vi.mocked(obtenerCsrf).mockResolvedValueOnce()
    vi.mocked(api.post).mockRejectedValueOnce(new Error('credenciales'))

    const auth = useAuthStore()

    await expect(auth.iniciarSesion('x@y.test', 'mala')).rejects.toThrow()
    expect(auth.autenticado).toBe(false)
    expect(auth.error).not.toBeNull()
  })
})
