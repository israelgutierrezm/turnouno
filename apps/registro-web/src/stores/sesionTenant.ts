import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { api, fijarBearer, mensajeDeError } from '@/lib/api'

export interface UsuarioTenant {
  ulid: string
  nombre: string
  email: string
  rol: string
}

export interface EstudioSesion {
  slug: string
  nombre: string
  estado: string
  estado_facturacion?: string
  trial_termina_en?: string | null
}

interface RespuestaAuth {
  token: string
  usuario: UsuarioTenant
  estudio: EstudioSesion
}

const CLAVE_BEARER = 'tu.tenant.bearer'
const CLAVE_SLUG = 'tu.tenant.slug'

/**
 * Sesion TENANT-LOCAL: no hay login global. Se resuelve el estudio por slug y se
 * guarda un bearer token que se envia en cada peticion a `/app/{slug}/...`. El
 * bearer y el slug se persisten para reanudar la sesion al recargar.
 */
export const useSesionTenantStore = defineStore('sesionTenant', () => {
  const slug = ref<string | null>(leer(CLAVE_SLUG))
  const bearer = ref<string | null>(leer(CLAVE_BEARER))
  const usuario = ref<UsuarioTenant | null>(null)
  const estudio = ref<EstudioSesion | null>(null)
  const cargando = ref(false)
  const error = ref<string | null>(null)
  const verificado = ref(false)

  const autenticado = computed(() => usuario.value !== null && bearer.value !== null)

  fijarBearer(bearer.value)

  function establecer(datos: RespuestaAuth): void {
    bearer.value = datos.token
    slug.value = datos.estudio.slug
    usuario.value = datos.usuario
    estudio.value = datos.estudio
    verificado.value = true
    fijarBearer(datos.token)
    guardar(CLAVE_BEARER, datos.token)
    guardar(CLAVE_SLUG, datos.estudio.slug)
  }

  async function iniciarSesion(slugEstudio: string, email: string, password: string): Promise<void> {
    cargando.value = true
    error.value = null
    try {
      const { data } = await api.post<{ data: RespuestaAuth }>(
        `/api/v1/app/${slugEstudio}/login`,
        { email, password },
      )
      establecer(data.data)
    } catch (e) {
      error.value = mensajeDeError(e, 'No se pudo iniciar sesion.')
      throw e
    } finally {
      cargando.value = false
    }
  }

  async function activar(
    slugEstudio: string,
    email: string,
    token: string,
    password: string,
    passwordConfirmation: string,
  ): Promise<void> {
    cargando.value = true
    error.value = null
    try {
      const { data } = await api.post<{ data: RespuestaAuth }>(
        `/api/v1/app/${slugEstudio}/activar`,
        { email, token, password, password_confirmation: passwordConfirmation },
      )
      establecer(data.data)
    } catch (e) {
      error.value = mensajeDeError(e, 'No se pudo activar la cuenta.')
      throw e
    } finally {
      cargando.value = false
    }
  }

  async function cargarYo(): Promise<void> {
    if (slug.value === null || bearer.value === null) {
      return
    }
    const { data } = await api.get<{ data: { usuario: UsuarioTenant; estudio: EstudioSesion } }>(
      `/api/v1/app/${slug.value}/yo`,
    )
    usuario.value = data.data.usuario
    estudio.value = data.data.estudio
  }

  async function verificarSesion(): Promise<void> {
    if (verificado.value) {
      return
    }
    try {
      await cargarYo()
    } catch {
      limpiar()
    } finally {
      verificado.value = true
    }
  }

  async function cerrarSesion(): Promise<void> {
    try {
      if (slug.value !== null && bearer.value !== null) {
        await api.post(`/api/v1/app/${slug.value}/logout`)
      }
    } catch {
      // Aunque falle en el servidor, limpiamos localmente.
    } finally {
      limpiar()
    }
  }

  function limpiar(): void {
    bearer.value = null
    usuario.value = null
    estudio.value = null
    fijarBearer(null)
    borrar(CLAVE_BEARER)
  }

  return {
    slug,
    bearer,
    usuario,
    estudio,
    cargando,
    error,
    autenticado,
    iniciarSesion,
    activar,
    cargarYo,
    verificarSesion,
    cerrarSesion,
  }
})

function leer(clave: string): string | null {
  try {
    return localStorage.getItem(clave)
  } catch {
    return null
  }
}
function guardar(clave: string, valor: string): void {
  try {
    localStorage.setItem(clave, valor)
  } catch {
    // Ignora si no hay localStorage.
  }
}
function borrar(clave: string): void {
  try {
    localStorage.removeItem(clave)
  } catch {
    // Ignora.
  }
}
