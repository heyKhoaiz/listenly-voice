import { describe, expect, it } from 'vitest'
import cases from '../fixtures/spoken.json'
import { spokenApplies, spokenNumbers } from '../../frontend/player/spoken'

describe('spokenNumbers (same cases as SpokenTest.php)', () => {
  it.each(cases as [string, string][])('%s', (input, expected) => {
    expect(spokenNumbers(input)).toBe(expected)
  })
  it('is English only', () => {
    expect(spokenApplies('en-US')).toBe(true)
    expect(spokenApplies('fr-FR')).toBe(false)
    expect(spokenApplies(undefined)).toBe(false)
  })
})
