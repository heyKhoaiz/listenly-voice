import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '../../frontend/player/mount'

type U = { text: string; rate: number; lang: string; onstart: (() => void) | null; onend: (() => void) | null }
let spoken: U[]
let synth: { speak: ReturnType<typeof vi.fn>; cancel: ReturnType<typeof vi.fn>; pause: ReturnType<typeof vi.fn>; resume: ReturnType<typeof vi.fn>; paused: boolean }
let highlights: Map<string, { ranges: Range[] }>

class FakeUtterance {
  text: string
  rate = 1
  lang = ''
  onstart: (() => void) | null = null
  onend: (() => void) | null = null
  onerror: (() => void) | null = null
  constructor(text: string) {
    this.text = text
  }
}

class FakeHighlight {
  ranges: Range[]
  constructor(...ranges: Range[]) {
    this.ranges = ranges
  }
}

function page(data: Record<string, unknown> = {}) {
  const json = JSON.stringify({ post: 1, clientSide: true, audioUrl: '/wp-json/listenly/v1/audio/1/', speed: 1, highlight: 'sentence', position: 'above', lang: 'en-US', ...data })
  document.body.innerHTML = `<article><div class="listenly-player" data-listenly='${json}'></div><h2>Grow tomatoes</h2><p>First they need sun. Then water daily.</p></article>`
  const root = document.querySelector<HTMLElement>('.listenly-player')!
  mount(root)
  return root
}

const playButton = () => document.querySelector<HTMLButtonElement>('.lvp-play')!
const speedButton = () => document.querySelector<HTMLButtonElement>('.lvp-speed')!
const current = () => highlights.get('listenly-sentence')?.ranges[0]?.toString()

beforeEach(() => {
  spoken = []
  highlights = new Map()
  synth = {
    speak: vi.fn((u: U) => spoken.push(u)),
    cancel: vi.fn(() => {
      spoken = []
    }),
    pause: vi.fn(),
    resume: vi.fn(),
    paused: false,
  }
  vi.stubGlobal('speechSynthesis', synth)
  vi.stubGlobal('SpeechSynthesisUtterance', FakeUtterance)
  vi.stubGlobal('Highlight', FakeHighlight)
  vi.stubGlobal('CSS', { highlights })
})

describe('browser voice', () => {
  it('shows an accessible play button and speed control', () => {
    page()
    expect(playButton()).toHaveAttribute('aria-label', 'Play article')
    expect(speedButton()).toHaveAttribute('aria-label', 'Playback speed 1×')
    expect(document.querySelector('.lvp')).toHaveAttribute('aria-label', 'Audio player')
  })

  it('reads the article one sentence at a time, without the player text', () => {
    page()
    playButton().click()
    expect(spoken.map((u) => u.text)).toEqual(['Grow tomatoes', 'First they need sun.', 'Then water daily.'])
    expect(spoken.every((u) => u.lang === 'en-US' && u.rate === 1)).toBe(true)
    expect(playButton()).toHaveAttribute('aria-label', 'Pause')
  })

  it('highlights the sentence being read', () => {
    page()
    playButton().click()
    spoken[1].onstart?.()
    expect(current()).toBe('First they need sun.')
    spoken[2].onstart?.()
    expect(current()).toBe('Then water daily.')
  })

  it('does not highlight when highlighting is off', () => {
    page({ highlight: 'off' })
    playButton().click()
    spoken[0].onstart?.()
    expect(highlights.size).toBe(0)
  })

  it('works without the Highlight API', () => {
    vi.stubGlobal('CSS', {})
    page()
    playButton().click()
    expect(() => spoken[0].onstart?.()).not.toThrow()
  })

  it('clears the highlight and resets the button at the end', () => {
    page()
    playButton().click()
    const last = spoken[spoken.length - 1]
    last.onstart?.()
    last.onend?.()
    expect(highlights.has('listenly-sentence')).toBe(false)
    expect(playButton()).toHaveAttribute('aria-label', 'Play article')
  })

  it('pauses, then resumes where it stopped', () => {
    page()
    playButton().click()
    playButton().click()
    expect(synth.pause).toHaveBeenCalled()
    expect(playButton()).toHaveAttribute('aria-label', 'Play article')
    synth.paused = true
    playButton().click()
    expect(synth.resume).toHaveBeenCalled()
    expect(synth.speak).toHaveBeenCalledTimes(3) // nothing queued again
  })

  it('changing speed while playing restarts from the current sentence at the new rate', () => {
    page()
    playButton().click()
    spoken[1].onstart?.()
    speedButton().click()
    expect(synth.cancel).toHaveBeenCalled()
    expect(spoken.map((u) => u.text)).toEqual(['First they need sun.', 'Then water daily.'])
    expect(spoken.every((u) => u.rate === 1.25)).toBe(true)
    expect(speedButton()).toHaveAttribute('aria-label', 'Playback speed 1.25×')
  })

  it('a cancelled sentence does not end the session', () => {
    page()
    playButton().click()
    const first = spoken[0]
    speedButton().click() // cancel → re-queue
    first.onend?.()
    expect(playButton()).toHaveAttribute('aria-label', 'Pause')
  })

  it('says so instead of showing a dead button when the browser has no voice', () => {
    vi.stubGlobal('speechSynthesis', undefined)
    page()
    expect(playButton()).toBeDisabled()
    expect(document.querySelector('.lvp-label')).toHaveTextContent("This browser can't read articles aloud.")
  })

  it('cycles the speed through 0.75 to 2', () => {
    page()
    const seen = Array.from({ length: 5 }, () => {
      speedButton().click()
      return speedButton().textContent
    })
    expect(seen).toEqual(['1.25×', '1.5×', '2×', '0.75×', '1×'])
  })
})
