import { describe, expect, it } from 'vitest'

import { getCorrelationId } from '@/lib/correlationId'

describe('getCorrelationId', () => {
  it('returns a non-empty string', () => {
    expect(getCorrelationId()).toBeTruthy()
  })

  it('returns unique values on subsequent calls', () => {
    expect(getCorrelationId()).not.toBe(getCorrelationId())
  })
})
