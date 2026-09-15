import axios from 'axios'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { api, obtenerCsrf } from '@/lib/api'

export interface Me {
  usuario: { id: string; nombre: string; email: string }
  tenant_actual: { id: string; nombre: string; slug: string } | null
  persona: { id: string; nombre: string; apellidos: string | null } | null
  roles: string[]
  permisos: string[]
}

export const useAuthStore = defineStore('auth', () => {
  const me = ref<Me | null>(null)
  const cargando = ref(false)
  const error = ref<string | null>(null)
  const verificado = ref(false)

  const autenticado = computed(() => me.value !== null)
  const nombre = computed(() => me.value?.persona?.nombre ?? me.value?.usuario.nombre ?? '')

  async function cargarMe(): Promise<void> {
    const { data } = await api.get<Me>('/api/v1/me')
    me.value = data
  }

  async function verificarSesion(): Promise<void> {
    if (verificado.value) {
      return
    }
    try {
      await cargarMe()
    } catch {
      me.value = null
    } finally {
      verificado.value = true
    }
  }

  async function iniciarSesion(email: string, password: string): Promise<void> {
    cargando.value = true
    error.value = null
    try {
      await obtenerCsrf()
      await api.post('/api/v1/auth/login', { email, password })
      await cargarMe()
      verificado.value = true
    } catch (e) {
      error.value = mensajeDeError(e)
      throw e
    } finally {
      cargando.value = false
    }
  }

  async function cerrarSesion(): Promise<void> {
    try {
      await api.post('/api/v1/auth/logout')
    } finally {
      me.value = null
    }
  }

  return { me, cargando, error, autenticado, nombre, cargarMe, verificarSesion, iniciarSesion, cerrarSesion }
})

function mensajeDeError(e: unknown): string {
  if (axios.isAxiosError(e)) {
    const data = e.response?.data as { message?: string } | undefined

    return data?.message ?? 'No se pudo iniciar sesión.'
  }

  return 'Ocurrió un error inesperado.'
}
