import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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

  it('reads the post title first, without highlighting it', () => {
    page({ title: 'How to grow tomatoes' })
    playButton().click()
    expect(spoken.map((u) => u.text)).toEqual(['How to grow tomatoes', 'Grow tomatoes', 'First they need sun.', 'Then water daily.'])
    spoken[0].onstart?.()
    expect(current()).toBeUndefined()
  })

  it('does not read the title twice when the page already starts with it', () => {
    page({ title: 'Grow tomatoes' })
    playButton().click()
    expect(spoken.map((u) => u.text)).toEqual(['Grow tomatoes', 'First they need sun.', 'Then water daily.'])
  })

  it('does not repeat the title when a speed change restarts mid-article', () => {
    page({ title: 'How to grow tomatoes' })
    playButton().click()
    spoken[2].onstart?.() // spoken: title, 'Grow tomatoes', 'First they need sun.', …
    speedButton().click()
    expect(spoken.map((u) => u.text)).toEqual(['First they need sun.', 'Then water daily.'])
  })

  it('reads the title of a post with no other text, then finishes', () => {
    document.body.innerHTML = `<article><div class="listenly-player" data-listenly='${JSON.stringify({ post: 1, clientSide: true, audioUrl: '/a/', speed: 1, highlight: 'sentence', position: 'above', lang: 'en-US', title: 'Just a title' })}'></div></article>`
    mount(document.querySelector<HTMLElement>('.listenly-player')!)
    playButton().click()
    expect(spoken.map((u) => u.text)).toEqual(['Just a title'])
    spoken[0].onend?.()
    expect(playButton()).toHaveAttribute('aria-label', 'Play article')
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

class FakeAudio extends EventTarget {
  src = ''
  currentTime = 0
  playbackRate = 1
  play = vi.fn(() => Promise.resolve())
  pause = vi.fn()
  load = vi.fn()
  removeAttribute(name: string) {
    if (name === 'src') this.src = ''
  }
}

describe('server voice (live Google audio)', () => {
  let audios: FakeAudio[]
  const audio = () => audios[0]
  const flush = () => new Promise((r) => setTimeout(r, 0))
  const label = () => document.querySelector('.lvp-label')!.textContent

  beforeEach(() => {
    audios = []
    vi.stubGlobal(
      'Audio',
      class extends FakeAudio {
        constructor() {
          super()
          audios.push(this)
        }
      },
    )
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ json: () => Promise.resolve({ parts: 2 }) })))
  })

  const start = async (data: Record<string, unknown> = {}) => {
    page({ clientSide: false, highlight: 'off', ...data })
    playButton().click()
    await flush()
  }

  it('plays the first part from the audio route', async () => {
    await start()
    expect(audio().src).toBe('/wp-json/listenly/v1/audio/1/0')
    expect(audio().play).toHaveBeenCalled()
    expect(spoken).toEqual([])
  })

  it('moves on to the next part when one ends', async () => {
    await start()
    audio().dispatchEvent(new Event('ended'))
    expect(audio().src).toBe('/wp-json/listenly/v1/audio/1/1')
  })

  it('falls back to the browser voice when the first part fails (busy or provider error)', async () => {
    await start()
    audio().dispatchEvent(new Event('error'))
    expect(spoken.map((u) => u.text)).toEqual(['Grow tomatoes', 'First they need sun.', 'Then water daily.'])
    expect(label()).toBe("Playing with your browser's voice.")
    expect(playButton()).toHaveAttribute('aria-label', 'Pause')
  })

  it('after falling back, pause and speed act on the browser voice', async () => {
    await start()
    audio().dispatchEvent(new Event('error'))
    playButton().click()
    expect(synth.pause).toHaveBeenCalled()
    expect(audio().play).toHaveBeenCalledTimes(1)
  })

  it('stops with a message when a later part fails, instead of switching voice mid-article', async () => {
    await start()
    audio().dispatchEvent(new Event('ended'))
    audio().dispatchEvent(new Event('error'))
    expect(spoken).toEqual([])
    expect(label()).toBe("Audio isn't available right now. Try again in a few minutes.")
    expect(playButton()).toHaveAttribute('aria-label', 'Play article')
  })

  it('shows the message when there is no browser voice to fall back to', async () => {
    vi.stubGlobal('speechSynthesis', undefined)
    await start()
    audio().dispatchEvent(new Event('error'))
    expect(label()).toBe("Audio isn't available right now. Try again in a few minutes.")
    expect(playButton()).toHaveAttribute('aria-label', 'Play article')
  })

  it('pressing play again retries the part that failed', async () => {
    await start()
    audio().dispatchEvent(new Event('ended'))
    audio().dispatchEvent(new Event('error'))
    playButton().click()
    await flush()
    expect(audio().src).toBe('/wp-json/listenly/v1/audio/1/1')
    expect(label()).toBe('Listen to this article')
  })

  it('ignores an error event while stopped', async () => {
    page({ clientSide: false, highlight: 'off' })
    audio().dispatchEvent(new Event('error'))
    expect(label()).toBe('Listen to this article')
    expect(spoken).toEqual([])
  })

  it('a failed play() call does not throw an unhandled rejection', async () => {
    page({ clientSide: false, highlight: 'off' })
    audio().play.mockImplementationOnce(() => Promise.reject(new Error('NotSupportedError')))
    playButton().click()
    await flush()
    await flush()
    expect(audio().play).toHaveBeenCalled()
  })
})

describe('server voice with read-along highlighting', () => {
  let audios: FakeAudio[]
  let fetchMock: ReturnType<typeof vi.fn>
  let urls: string[]
  let revoked: string[]
  const audio = () => audios[0]
  const flush = async () => {
    for (let i = 0; i < 5; i++) await new Promise((r) => setTimeout(r, 0))
  }
  const label = () => document.querySelector('.lvp-label')!.textContent
  const json = (body: unknown, status = 200) => Promise.resolve({ ok: status < 400, status, json: () => Promise.resolve(body) })
  const part = (marks: { t: number; text: string }[], parts = 1) => ({ audio: btoa('MP3'), marks, parts })
  const at = (seconds: number) => {
    audio().currentTime = seconds
    audio().dispatchEvent(new Event('timeupdate'))
  }

  beforeEach(() => {
    audios = []
    urls = []
    revoked = []
    vi.stubGlobal(
      'Audio',
      class extends FakeAudio {
        constructor() {
          super()
          audios.push(this)
        }
      },
    )
    vi.stubGlobal('URL', Object.assign(URL, {
      createObjectURL: vi.fn(() => {
        const u = `blob:part-${urls.length}`
        urls.push(u)
        return u
      }),
      revokeObjectURL: vi.fn((u: string) => revoked.push(u)),
    }))
    fetchMock = vi.fn(() =>
      json(part([{ t: 0, text: 'A balcony guide.' }, { t: 0.8, text: 'First they need sun.' }, { t: 2.1, text: 'Then water daily.' }])),
    )
    vi.stubGlobal('fetch', fetchMock)
  })

  const start = async () => {
    page({ clientSide: false })
    playButton().click()
    await flush()
  }

  it('fetches the timed part and plays its audio, without a separate parts request', async () => {
    await start()
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.calls[0][0]).toBe('/wp-json/listenly/v1/audio/1/0/timed')
    expect(audio().src).toBe('blob:part-0')
    expect(audio().play).toHaveBeenCalled()
  })

  it('highlights the sentence being read as the audio plays', async () => {
    await start()
    at(1.0)
    expect(current()).toBe('First they need sun.')
    at(2.5)
    expect(current()).toBe('Then water daily.')
  })

  it('a sentence that is not on the page (the post title) clears the highlight', async () => {
    await start()
    at(1.0)
    at(0.1)
    expect(current()).toBeUndefined()
  })

  it('does not highlight when highlighting is turned off', async () => {
    page({ clientSide: false, highlight: 'off' })
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ json: () => Promise.resolve({ parts: 1 }) })))
    playButton().click()
    await flush()
    expect(audio().src).toBe('/wp-json/listenly/v1/audio/1/0')
  })

  it('moves to the next timed part and frees the previous audio', async () => {
    fetchMock.mockImplementationOnce(() => json(part([{ t: 0, text: 'First they need sun.' }], 2)))
    fetchMock.mockImplementationOnce(() => json(part([{ t: 0, text: 'Then water daily.' }], 2)))
    await start()
    audio().dispatchEvent(new Event('ended'))
    await flush()
    expect(fetchMock.mock.calls[1][0]).toBe('/wp-json/listenly/v1/audio/1/1/timed')
    expect(audio().src).toBe('blob:part-1')
    expect(revoked).toEqual(['blob:part-0'])
    at(0.2)
    expect(current()).toBe('Then water daily.')
  })

  it('clears the highlight at the end', async () => {
    await start()
    at(1.0)
    audio().dispatchEvent(new Event('ended'))
    expect(current()).toBeUndefined()
    expect(playButton()).toHaveAttribute('aria-label', 'Play article')
  })

  it('falls back to the browser voice when the first timed part is refused (429)', async () => {
    fetchMock.mockImplementationOnce(() => json({ code: 'listenly_busy' }, 429))
    await start()
    expect(label()).toBe("Playing with your browser's voice.")
    expect(spoken.map((u) => u.text)).toEqual(['Grow tomatoes', 'First they need sun.', 'Then water daily.'])
  })

  it('stops with a message when a later timed part fails', async () => {
    fetchMock.mockImplementationOnce(() => json(part([{ t: 0, text: 'First they need sun.' }], 2)))
    fetchMock.mockImplementationOnce(() => Promise.reject(new Error('offline')))
    await start()
    audio().dispatchEvent(new Event('ended'))
    await flush()
    expect(label()).toBe("Audio isn't available right now. Try again in a few minutes.")
    expect(playButton()).toHaveAttribute('aria-label', 'Play article')
  })

  it('works without the Highlight API (audio still plays)', async () => {
    vi.stubGlobal('Highlight', undefined)
    await start()
    at(1.0)
    expect(audio().play).toHaveBeenCalled()
    expect(highlights.size).toBe(0)
  })
})

describe('ready audio file with timings (from an extension)', () => {
  let audios: FakeAudio[]
  let fetchMock: ReturnType<typeof vi.fn>
  const audio = () => audios[0]
  const flush = async () => {
    for (let i = 0; i < 5; i++) await new Promise((r) => setTimeout(r, 0))
  }
  const at = (seconds: number) => {
    audio().currentTime = seconds
    audio().dispatchEvent(new Event('timeupdate'))
  }
  const timings = { marks: [{ t: 0, text: 'Grow tomatoes.' }, { t: 1, text: 'First they need sun.' }, { t: 2, text: 'Then water daily.' }] }

  beforeEach(() => {
    audios = []
    vi.stubGlobal(
      'Audio',
      class extends FakeAudio {
        constructor() {
          super()
          audios.push(this)
        }
      },
    )
    fetchMock = vi.fn(() => Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(timings) }))
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    delete (window as unknown as { wp?: unknown }).wp
  })

  const start = async (data: Record<string, unknown> = {}) => {
    page({ clientSide: false, file: '/uploads/listenly/1-abc.mp3', timingsUrl: '/uploads/listenly/1-abc.json', ...data })
    playButton().click()
    await flush()
  }

  it('plays the file and fetches its timings once', async () => {
    await start()
    expect(audio().src).toBe('/uploads/listenly/1-abc.mp3')
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.calls[0][0]).toBe('/uploads/listenly/1-abc.json')
  })

  it('highlights the sentence being read in the file', async () => {
    await start()
    at(1.2)
    expect(current()).toBe('First they need sun.')
    at(2.4)
    expect(current()).toBe('Then water daily.')
  })

  it('still plays without highlighting when the timings fail to load', async () => {
    fetchMock.mockImplementationOnce(() => Promise.resolve({ ok: false, status: 404, json: () => Promise.resolve({}) }))
    await start()
    at(1.2)
    expect(audio().play).toHaveBeenCalled()
    expect(current()).toBeUndefined()
  })

  it('does not fetch timings when highlighting is off', async () => {
    await start({ highlight: 'off' })
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('plays a file without timings as before', async () => {
    await start({ timingsUrl: undefined })
    expect(fetchMock).not.toHaveBeenCalled()
    expect(audio().play).toHaveBeenCalled()
  })

  it('lets an extension narrow the highlight through listenly.player.highlight', async () => {
    const seen: unknown[] = []
    ;(window as unknown as { wp: unknown }).wp = {
      hooks: {
        applyFilters: (name: string, range: Range | null, ctx: { time: number; index: number; sentence: { range: Range } | null; timings: unknown }) => {
          if (name !== 'listenly.player.highlight') return range
          seen.push({ time: ctx.time, index: ctx.index, hasTimings: !!ctx.timings })
          if (!ctx.sentence) return range
          const word = document.createRange()
          const node = ctx.sentence.range.startContainer
          word.setStart(node, ctx.sentence.range.startOffset)
          word.setEnd(node, ctx.sentence.range.startOffset + 5)
          return word
        },
      },
    }
    await start()
    at(1.2)
    expect(current()).toBe('First')
    expect(seen[seen.length - 1]).toEqual({ time: 1.2, index: 1, hasTimings: true })
  })

  it('the filter also applies to live timed parts', async () => {
    ;(window as unknown as { wp: unknown }).wp = { hooks: { applyFilters: () => null } }
    fetchMock.mockImplementation(() => Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({ audio: btoa('MP3'), marks: timings.marks, parts: 1 }) }))
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: vi.fn(() => 'blob:x'), revokeObjectURL: vi.fn() }))
    page({ clientSide: false })
    playButton().click()
    await flush()
    at(1.2)
    expect(current()).toBeUndefined()
  })
})
