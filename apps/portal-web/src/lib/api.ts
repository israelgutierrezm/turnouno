import axios from 'axios'

import { getCorrelationId } from '@/lib/correlationId'

/**
 * Shared HTTP client for the member/guardian portal.
 *
 * - `withCredentials` enables Sanctum cookie authentication (same-site).
 * - Every request carries an `X-Correlation-ID` for end-to-end tracing.
 */
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  config.headers.set('X-Correlation-ID', getCorrelationId())

  return config
})
