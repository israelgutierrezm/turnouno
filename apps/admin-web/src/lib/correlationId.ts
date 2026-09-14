/**
 * Generates a client-side correlation id so a browser action can be traced
 * across the API request/response and the server logs (see docs/ARCHITECTURE.md).
 */
export function getCorrelationId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `cid-${Date.now()}-${Math.random().toString(16).slice(2)}`
}
