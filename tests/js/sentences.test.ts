import { beforeEach, describe, expect, it } from 'vitest'
import { collectSentences, splitSentences } from '../../frontend/player/sentences'

describe('splitSentences', () => {
  it('splits on full stops, question and exclamation marks', () => {
    expect(splitSentences('One. Two? Three! Four')).toEqual([
      { start: 0, end: 4 },
      { start: 5, end: 9 },
      { start: 10, end: 16 },
      { start: 17, end: 21 },
    ])
  })

  it('keeps closing quotes and brackets with the sentence', () => {
    const text = 'He said "go." Then (it ended.) Done.'
    const parts = splitSentences(text).map((r) => text.slice(r.start, r.end))
    expect(parts).toEqual(['He said "go."', 'Then (it ended.)', 'Done.'])
  })

  it('does not split decimals or numbers like $12.7bn', () => {
    const text = 'Revenue hit $12.7bn in 2025. Up 4.5 percent.'
    const parts = splitSentences(text).map((r) => text.slice(r.start, r.end))
    expect(parts).toEqual(['Revenue hit $12.7bn in 2025.', 'Up 4.5 percent.'])
  })

  it('does not split common abbreviations', () => {
    const text = 'Dr. Smith met Mr. Jones, e.g. at 5 p.m. today. Next.'
    const parts = splitSentences(text).map((r) => text.slice(r.start, r.end))
    expect(parts).toEqual(['Dr. Smith met Mr. Jones, e.g. at 5 p.m. today.', 'Next.'])
  })

  it('splits a very long sentence at a comma so no part runs past the limit', () => {
    const text = `${'word '.repeat(60).trim()}, ${'more '.repeat(60).trim()}.`
    const parts = splitSentences(text, 200).map((r) => text.slice(r.start, r.end))
    expect(parts.length).toBeGreaterThan(1)
    for (const p of parts) expect(p.length).toBeLessThanOrEqual(200)
  })

  it('ignores whitespace-only text', () => {
    expect(splitSentences('   \n ')).toEqual([])
  })
})

describe('collectSentences', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('maps each sentence to a DOM range across inline elements', () => {
    document.body.innerHTML = '<article><p>First <em>bit</em> here. Second one.</p><p>Third.</p></article>'
    const sentences = collectSentences(document.querySelector('article')!)
    expect(sentences.map((s) => s.text)).toEqual(['First bit here.', 'Second one.', 'Third.'])
    expect(sentences[0].range.toString()).toBe('First bit here.')
    expect(sentences[2].range.toString()).toBe('Third.')
  })

  it('treats each block as its own sentence even without a full stop', () => {
    document.body.innerHTML = '<article><h2>A heading</h2><p>Body text</p></article>'
    expect(collectSentences(document.querySelector('article')!).map((s) => s.text)).toEqual(['A heading', 'Body text'])
  })

  it('skips the player, scripts, styles, figures and navigation', () => {
    document.body.innerHTML = `<article>
      <div class="listenly-player">Listen to this article</div>
      <script>var x = 1;</script><style>p{}</style>
      <figure><figcaption>Caption.</figcaption></figure>
      <nav>Menu.</nav>
      <p>Only this.</p>
    </article>`
    expect(collectSentences(document.querySelector('article')!).map((s) => s.text)).toEqual(['Only this.'])
  })

  it('returns nothing for an empty article', () => {
    document.body.innerHTML = '<article></article>'
    expect(collectSentences(document.querySelector('article')!)).toEqual([])
  })
})
