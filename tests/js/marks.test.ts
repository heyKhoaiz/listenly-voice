// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { alignMarks, markAt, normalize } from '../../frontend/player/marks'
import { collectSentences } from '../../frontend/player/sentences'

function article(html: string) {
  document.body.innerHTML = `<article>${html}</article>`
  return collectSentences(document.querySelector('article')!)
}

describe('normalize', () => {
  it('keeps only lower-case letters and digits, in any script', () => {
    expect(normalize('It’s 60 cm — "Café"!')).toBe('its60cmcafé')
  })
})

describe('alignMarks', () => {
  it('maps each timed sentence to the matching sentence on the page', () => {
    const page = article('<p>First they need sun. Then water daily.</p><p>Pick when red.</p>')
    const { targets } = alignMarks(
      [
        { t: 0, text: 'First they need sun.' },
        { t: 1.2, text: 'Then water daily.' },
        { t: 2.5, text: 'Pick when red.' },
      ],
      page,
      0,
    )
    expect(targets.map((s) => s?.text)).toEqual(['First they need sun.', 'Then water daily.', 'Pick when red.'])
  })

  it('leaves the post title (read by the server but not on the page) unmatched', () => {
    const page = article('<p>First they need sun.</p>')
    const { targets } = alignMarks([{ t: 0, text: 'Grow tomatoes.' }, { t: 1, text: 'First they need sun.' }], page, 0)
    expect(targets[0]).toBeNull()
    expect(targets[1]?.text).toBe('First they need sun.')
  })

  it('matches despite different punctuation, quotes and spacing', () => {
    const page = article('<p>“Water deeply,” she said — every   morning.</p>')
    const { targets } = alignMarks([{ t: 0, text: '"Water deeply," she said - every morning.' }], page, 0)
    expect(targets[0]?.text).toContain('Water deeply')
  })

  it('a server sentence that spans two page sentences highlights the first', () => {
    const page = article('<p>Dr. Lee grows them. They thrive.</p>')
    const { targets } = alignMarks([{ t: 0, text: 'Dr.' }, { t: 0.4, text: 'Lee grows them.' }], page, 0)
    expect(targets[0]?.text).toBe('Dr. Lee grows them.')
    expect(targets[1]?.text).toBe('Dr. Lee grows them.')
  })

  it('carries on from the cursor, so the next part keeps reading forward', () => {
    const page = article('<p>Water daily. Feed weekly. Water daily.</p>')
    const first = alignMarks([{ t: 0, text: 'Water daily.' }, { t: 1, text: 'Feed weekly.' }], page, 0)
    const second = alignMarks([{ t: 0, text: 'Water daily.' }], page, first.cursor)
    expect(page.indexOf(second.targets[0]!)).toBe(2)
  })

  it('handles no marks and no page sentences', () => {
    expect(alignMarks([], article('<p>Hi.</p>'), 0).targets).toEqual([])
    expect(alignMarks([{ t: 0, text: 'Hi.' }], [], 0).targets).toEqual([null])
  })
})

describe('markAt', () => {
  const marks = [{ t: 0.2, text: 'a' }, { t: 1.5, text: 'b' }, { t: 3, text: 'c' }]
  it('is the last mark that has started', () => {
    expect(markAt(marks, 0.1)).toBe(-1)
    expect(markAt(marks, 0.2)).toBe(0)
    expect(markAt(marks, 2.9)).toBe(1)
    expect(markAt(marks, 99)).toBe(2)
  })
  it('is -1 without marks', () => {
    expect(markAt([], 5)).toBe(-1)
  })
})
