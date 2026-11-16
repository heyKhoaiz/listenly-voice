import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { applySay, mount } from '../../frontend/player/mount'

type U = { text: string; onstart: (() => void) | null; onend: (() => void) | null }
let spoken: U[]
let events: [string, Record<string, unknown>][]

class FakeUtterance {
  text: string
  rate = 1
  lang = ''
  onstart: (() => void) | null = null
  onend: (() => void) | null = null
  constructor(text: string) {
    this.text = text
  }
}

function page(data: Record<string, unknown> = {}, body = '<h2>Grow tomatoes</h2><p>First they need sun. <span class="ad">Buy seeds now.</span> Then water daily.</p>') {
  const json = JSON.stringify({ post: 1, clientSide: true, audioUrl: '/a/1/', playUrl: '/play/1', speed: 1, highlight: 'off', position: 'above', lang: 'en-US', ...data })
  document.body.innerHTML = `<article><div class="listenly-player" data-listenly='${json}'></div>${body}</article>`
  const root = document.querySelector<HTMLElement>('.listenly-player')!
  mount(root)
  return root
}
const playButton = () => document.querySelector<HTMLButtonElement>('.lvp-play')!

beforeEach(() => {
  spoken = []
  events = []
  vi.stubGlobal('speechSynthesis', { speak: vi.fn((u: U) => spoken.push(u)), cancel: vi.fn(() => (spoken = [])), pause: vi.fn(), resume: vi.fn(), paused: false })
  vi.stubGlobal('SpeechSynthesisUtterance', FakeUtterance)
  vi.stubGlobal('wp', { hooks: { applyFilters: (_n: string, v: unknown) => v, doAction: (_n: string, name: string, detail: Record<string, unknown>) => events.push([name, detail]) } })
  vi.stubGlobal('navigator', { sendBeacon: vi.fn(() => true) })
})
afterEach(() => vi.unstubAllGlobals())

describe('player extras', () => {
  it('adds allowed classes and --lvp-* variables only', () => {
    const root = page({ classes: ['lvp-style-pill', 'bad class'], vars: { '--lvp-btn-bg': '#E8590C', color: 'red' } })
    expect(root.classList.contains('lvp-style-pill')).toBe(true)
    expect(root.className).not.toContain('bad')
    const bar = root.querySelector<HTMLElement>('.lvp')!
    expect(bar.style.getPropertyValue('--lvp-btn-bg')).toBe('#E8590C')
    expect(bar.style.getPropertyValue('color')).toBe('')
  })

  it('shows a download link when given one', () => {
    page({ download: '/audio.mp3' })
    const a = document.querySelector<HTMLAnchorElement>('.lvp-download')!
    expect(a.getAttribute('href')).toBe('/audio.mp3')
    expect(a.getAttribute('aria-label')).toBe('Download the audio')
  })

  it('skips the extra selector and ignores an invalid one', () => {
    page({ skip: '.ad' })
    playButton().click()
    expect(spoken.map((u) => u.text).join(' ')).not.toContain('Buy seeds')
    page({ skip: '[[[' })
    playButton().click()
    expect(spoken.map((u) => u.text).join(' ')).toContain('Buy seeds')
  })

  it('applies spoken replacements to whole words', () => {
    expect(applySay('WP is great, WPML too', [['WP', 'WordPress']])).toBe('WordPress is great, WPML too')
    expect(applySay('Hi', undefined)).toBe('Hi')
    page({ say: [['sun', 'sunshine']] })
    playButton().click()
    expect(spoken.map((u) => u.text)).toContain('First they need sunshine.')
  })

  it('counts the first play once and emits events', () => {
    page()
    playButton().click()
    playButton().click()
    playButton().click()
    expect((navigator.sendBeacon as ReturnType<typeof vi.fn>).mock.calls).toEqual([['/play/1']])
    expect(events.map((e) => e[0])).toEqual(expect.arrayContaining(['play', 'pause']))
    spoken.forEach((u) => u.onstart?.())
    expect(events.some(([n, d]) => n === 'progress' && typeof d.fraction === 'number')).toBe(true)
  })

  it('offers other tracks and plays the chosen file', () => {
    const playSpy = vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue()
    const pauseSpy = vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {})
    page({ tracks: [{ label: '1-min summary', file: '/summary.mp3' }] })
    const buttons = [...document.querySelectorAll<HTMLButtonElement>('.lvp-track')]
    expect(buttons.map((b) => b.textContent)).toEqual(['Full article', '1-min summary'])
    buttons[1].click()
    expect(buttons[1].getAttribute('aria-pressed')).toBe('true')
    playButton().click()
    expect(playSpy).toHaveBeenCalled()
    expect(spoken).toHaveLength(0)
    expect(events.some(([n, d]) => n === 'track' && d.index === 0)).toBe(true)
    playSpy.mockRestore()
    pauseSpy.mockRestore()
  })
})
