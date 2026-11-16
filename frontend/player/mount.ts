// One player. Browser voice → speechSynthesis, one sentence at a time with read-along highlighting;
// a ready audio file (data.file, filled by extensions) → plays it, with read-along when the
// extension also gives data.timingsUrl ({ marks: [{ t, text }] });
// server voices → MP3 parts from /wp-json/listenly/v1/audio/{post}/{part}, or with read-along on,
// /{part}/timed: the same audio as JSON plus when each sentence starts.
import { alignMarks, markAt, normalize, type Mark } from './marks'
import { collectSentences, type Sentence } from './sentences'

type Data = { post: number; title?: string; clientSide: boolean; file?: string; timingsUrl?: string; audioUrl: string; speed: number; highlight: string; position: string; lang: string }

const t = (text: string) => (window as unknown as { wp?: { i18n?: { __: (s: string, d: string) => string } } }).wp?.i18n?.__(text, 'listenly-voice') ?? text
const SPEEDS = [0.75, 1, 1.25, 1.5, 2]
const HIGHLIGHT = 'listenly-sentence'

// Extensions can narrow or replace the highlighted range (for example to one word):
//   wp.hooks.addFilter('listenly.player.highlight', ns, (range, { time, index, sentence, marks, timings }) => range)
type HighlightContext = { time: number; index: number; sentence: Sentence | null; marks: Mark[]; timings: unknown }
const filterRange = (range: Range | null, ctx: HighlightContext): Range | null => {
  const hooks = (window as unknown as { wp?: { hooks?: { applyFilters: (n: string, v: unknown, c: unknown) => unknown } } }).wp?.hooks
  if (!hooks) return range
  const out = hooks.applyFilters('listenly.player.highlight', range, ctx)
  return out && typeof out === 'object' && 'startContainer' in out ? (out as Range) : null
}

type Highlights = { set(name: string, h: unknown): void; delete(name: string): void }
const highlights = (): Highlights | null => {
  const reg = (globalThis as { CSS?: { highlights?: Highlights } }).CSS?.highlights
  return reg && typeof (globalThis as { Highlight?: unknown }).Highlight === 'function' ? reg : null
}

function articleOf(root: HTMLElement): HTMLElement {
  return (root.closest('article, .entry-content, main') as HTMLElement | null) ?? document.body
}

export function mount(root: HTMLElement): void {
  const data = JSON.parse(root.dataset.listenly ?? '{}') as Data
  const synth = (globalThis as { speechSynthesis?: SpeechSynthesis }).speechSynthesis
  // True for the browser voice, or after live audio failed and we switched to it.
  let local = data.clientSide
  let speed = data.speed || 1
  let playing = false
  let part = 0
  let parts = 1
  const audio = new Audio()

  // Browser voice state: the sentence list, where we are, and a generation counter so
  // callbacks from a cancelled queue are ignored.
  let sentences: Sentence[] | null = null
  let index = 0
  let generation = 0

  root.innerHTML = ''
  const bar = document.createElement('div')
  bar.className = 'lvp'
  bar.setAttribute('role', 'region')
  bar.setAttribute('aria-label', t('Audio player'))
  const play = document.createElement('button')
  play.type = 'button'
  play.className = 'lvp-play'
  const label = document.createElement('span')
  label.className = 'lvp-label'
  label.textContent = t('Listen to this article')
  const rate = document.createElement('button')
  rate.type = 'button'
  rate.className = 'lvp-speed'
  bar.append(play, label, rate)
  root.append(bar)

  const unsupported = local && !synth

  const render = () => {
    play.setAttribute('aria-label', playing ? t('Pause') : t('Play article'))
    play.innerHTML = playing
      ? '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>'
      : '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>'
    rate.textContent = `${speed}×`
    rate.setAttribute('aria-label', `${t('Playback speed')} ${speed}×`)
  }

  const paint = (range: Range | null) => {
    const reg = highlights()
    if (!reg || data.highlight === 'off') return
    if (!range) return reg.delete(HIGHLIGHT)
    const HighlightCtor = (globalThis as unknown as { Highlight: new (...r: Range[]) => unknown }).Highlight
    reg.set(HIGHLIGHT, new HighlightCtor(range))
  }
  const mark = (sentence: Sentence | null) => paint(sentence?.range ?? null)

  const finish = () => {
    playing = false
    index = 0
    mark(null)
    render()
  }

  // Queue every sentence from `from` onwards. Chrome stops a single long utterance after
  // about 15 seconds, so short utterances also make long articles play to the end.
  const speakFrom = (from: number) => {
    if (!synth) return
    sentences ??= collectSentences(articleOf(root))
    const gen = ++generation
    synth.cancel()
    const lang = data.lang || document.documentElement.lang || 'en-US'
    // The title sits outside the post content in most themes, so read it first, unless the
    // content already starts with it. Google voices read it too (it's in the server's text).
    const title = (data.title ?? '').trim()
    const withTitle = from === 0 && title !== '' && !normalize(sentences[0]?.text ?? '').startsWith(normalize(title))
    if (sentences.length === 0 && !withTitle) return finish()
    if (withTitle) {
      const u = new SpeechSynthesisUtterance(title)
      u.lang = lang
      u.rate = speed
      u.onstart = () => {
        if (gen === generation) mark(null)
      }
      u.onend = () => {
        if (gen === generation && sentences!.length === 0) finish()
      }
      synth.speak(u)
    }
    sentences.slice(from).forEach((sentence, i) => {
      const u = new SpeechSynthesisUtterance(sentence.text)
      u.lang = lang
      u.rate = speed
      u.onstart = () => {
        if (gen !== generation) return
        index = from + i
        mark(sentence)
      }
      u.onend = () => {
        if (gen === generation && from + i === sentences!.length - 1) finish()
      }
      synth.speak(u)
    })
  }

  // play() rejects when the source fails; the 'error' event below handles that.
  const start = () => void audio.play().catch(() => {})

  // Read-along for server voices: each timed part says when its sentences start, matched to
  // the page's sentences by text. `cursor` keeps the matching moving forward across parts.
  const timed = data.highlight !== 'off' && highlights() !== null
  let marks: Mark[] = []
  let targets: (Sentence | null)[] = []
  let cursor = 0
  let blobUrl = ''
  let timings: unknown = null
  // Timings for a ready file cover the whole post; they're kept for replays.
  let fileTimings: { marks: Mark[]; targets: (Sentence | null)[] } | null = null

  const loadFileTimings = () => {
    if (!data.timingsUrl || data.highlight === 'off' || highlights() === null || fileTimings) return
    fileTimings = { marks: [], targets: [] }
    void fetch(data.timingsUrl)
      .then((r) => {
        if (!r.ok) throw new Error(String(r.status))
        return r.json() as Promise<{ marks?: Mark[] }>
      })
      .then((j) => {
        const list = Array.isArray(j.marks) ? j.marks : []
        sentences ??= collectSentences(articleOf(root))
        fileTimings = { marks: list, targets: alignMarks(list, sentences, 0).targets }
        timings = j
        marks = fileTimings.marks
        targets = fileTimings.targets
      })
      .catch(() => {})
  }

  const loadTimed = (n: number) => {
    marks = []
    return fetch(`${data.audioUrl}${n}/timed`)
      .then((r) => {
        if (!r.ok) throw new Error(String(r.status))
        return r.json() as Promise<{ audio: string; marks?: Mark[]; parts?: number }>
      })
      .then((j) => {
        parts = Number(j.parts) || 1
        timings = j
        sentences ??= collectSentences(articleOf(root))
        const aligned = alignMarks(Array.isArray(j.marks) ? j.marks : [], sentences, cursor)
        marks = Array.isArray(j.marks) ? j.marks : []
        targets = aligned.targets
        cursor = aligned.cursor
        const bytes = Uint8Array.from(atob(j.audio), (c) => c.charCodeAt(0))
        if (blobUrl) URL.revokeObjectURL(blobUrl)
        blobUrl = URL.createObjectURL(new Blob([bytes], { type: 'audio/mpeg' }))
        audio.src = blobUrl
        audio.playbackRate = speed
        if (playing) start()
      })
      .catch(() => failed())
  }
  const loadPart = (n: number) => {
    if (timed) return void loadTimed(n)
    audio.src = `${data.audioUrl}${n}`
    audio.playbackRate = speed
    start()
  }
  audio.addEventListener('timeupdate', () => {
    if (!marks.length) return
    const time = audio.currentTime
    const i = markAt(marks, time)
    const sentence = i >= 0 ? (targets[i] ?? null) : null
    paint(filterRange(sentence?.range ?? null, { time, index: i, sentence, marks, timings }))
  })

  // A live part failed: rate limited (429) or a provider error (502). Before anything was
  // heard, switch to the browser voice; mid-article, stop rather than change voice.
  const failed = () => {
    if (!playing || local) return
    audio.removeAttribute('src')
    if (part === 0 && synth) {
      local = true
      label.textContent = t("Playing with your browser's voice.")
      speakFrom(0)
      return
    }
    playing = false
    label.textContent = t("Audio isn't available right now. Try again in a few minutes.")
    render()
  }
  audio.addEventListener('error', failed)
  audio.addEventListener('ended', () => {
    if (part + 1 < parts) loadPart(++part)
    else {
      playing = false
      part = 0
      cursor = 0
      marks = fileTimings?.marks ?? []
      targets = fileTimings?.targets ?? []
      mark(null)
      render()
    }
  })
  audio.addEventListener('loadedmetadata', () => {
    audio.playbackRate = speed
  })
  // How many parts the post has: a cheap route, no synthesis.
  const fetchParts = () =>
    fetch(`${data.audioUrl}info`)
      .then((r) => r.json())
      .then((j: { parts?: number }) => {
        parts = Number(j.parts) || 1
      })
      .catch(() => {})

  play.addEventListener('click', () => {
    playing = !playing
    if (playing && !local) label.textContent = t('Listen to this article')
    if (local && synth) {
      if (!playing) synth.pause()
      else if (synth.paused) synth.resume()
      else speakFrom(index)
    } else if (playing) {
      if (audio.src) start()
      else if (data.file) {
        audio.src = data.file
        audio.playbackRate = speed
        start()
        loadFileTimings()
      } else if (timed) loadPart(part)
      else void fetchParts().then(() => loadPart(part))
    } else {
      audio.pause()
    }
    render()
  })
  rate.addEventListener('click', () => {
    speed = SPEEDS[(SPEEDS.indexOf(speed) + 1) % SPEEDS.length] ?? 1
    audio.playbackRate = speed
    if (local && playing) speakFrom(index)
    render()
  })

  render()
  if (unsupported) {
    play.disabled = true
    rate.disabled = true
    label.textContent = t("This browser can't read articles aloud.")
  }
}
