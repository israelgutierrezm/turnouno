import axios from 'axios'

import { getCorrelationId } from '@/lib/correlationId'

/**
 * Cliente HTTP del portal del miembro/tutor.
 *
 * - `withCredentials` + `withXSRFToken` habilitan la autenticación por cookie de
 *   Sanctum (mismo sitio), enviando el token XSRF también entre puertos.
 * - Cada petición lleva un `X-Correlation-ID` para trazabilidad de punta a punta.
 */
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  config.headers.set('X-Correlation-ID', getCorrelationId())

  return config
})

/**
 * Obtiene la cookie CSRF de Sanctum antes de una petición con estado (login).
 */
export async function obtenerCsrf(): Promise<void> {
  await api.get('/sanctum/csrf-cookie')
}
