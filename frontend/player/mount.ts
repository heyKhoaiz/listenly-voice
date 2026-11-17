// One player. Browser voice → speechSynthesis, one sentence at a time with read-along highlighting;
// a ready audio file (data.file, filled by extensions) → plays it, with read-along when the
// extension also gives data.timingsUrl ({ marks: [{ t, text }] });
// server voices → MP3 parts from /wp-json/postaloud/v1/audio/{post}/{part}, or with read-along on,
// /{part}/timed: the same audio as JSON plus when each sentence starts.
//
// Optional data that extensions can fill (postaloud/player_data):
//   classes   extra classes on the player root      vars    CSS custom properties (--lvp-*)
//   download  a link to download the audio          skip    extra CSS selector the browser voice skips
//   say       [[word, spoken], …] for the browser voice
//   tracks    [{ label, file, timingsUrl? }, …]: other recordings the visitor can switch to
// Events, for extensions: wp.hooks.doAction('postaloud.player.event', name, detail, data) with
// name play | pause | progress ({ fraction }) | speed ({ speed }) | ended | painted ({ range }) | track ({ index }).
import { alignMarks, markAt, normalize, type Mark } from './marks'
import { collectSentences, type Sentence } from './sentences'
import { spokenApplies, spokenNumbers } from './spoken'
import { watchTheme } from './theme'

type Track = { label: string; file: string; timingsUrl?: string }
type Data = {
  post: number
  title?: string
  clientSide: boolean
  file?: string
  timingsUrl?: string
  audioUrl: string
  playUrl?: string
  speed: number
  highlight: string
  position: string
  lang: string
  theme?: string
  classes?: string[]
  vars?: Record<string, string>
  download?: string
  skip?: string
  say?: [string, string][]
  /** Spell out money and number shorthand ("$12.7bn"). Missing: decided by the language. */
  numbers?: boolean
  tracks?: Track[]
}

const t = (text: string) => (window as unknown as { wp?: { i18n?: { __: (s: string, d: string) => string } } }).wp?.i18n?.__(text, 'postaloud') ?? text
const SPEEDS = [0.75, 1, 1.25, 1.5, 2]
const HIGHLIGHT = 'postaloud-sentence'

// Extensions can narrow or replace the highlighted range (for example to one word):
//   wp.hooks.addFilter('postaloud.player.highlight', ns, (range, { time, index, sentence, marks, timings }) => range)
type HighlightContext = { time: number; index: number; sentence: Sentence | null; marks: Mark[]; timings: unknown }
const filterRange = (range: Range | null, ctx: HighlightContext): Range | null => {
  const hooks = (window as unknown as { wp?: { hooks?: { applyFilters: (n: string, v: unknown, c: unknown) => unknown } } }).wp?.hooks
  if (!hooks) return range
  const out = hooks.applyFilters('postaloud.player.highlight', range, ctx)
  return out && typeof out === 'object' && 'startContainer' in out ? (out as Range) : null
}

type WpHooks = { applyFilters: (n: string, v: unknown, c: unknown) => unknown; doAction?: (n: string, ...a: unknown[]) => void }
const emitter = () => (window as unknown as { wp?: { hooks?: WpHooks } }).wp?.hooks

const escapeRe = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
/** Applies [word, spoken] replacements, whole words, any case. */
export function applySay(text: string, say: [string, string][] | undefined): string {
  if (!Array.isArray(say)) return text
  let out = text
  for (const pair of say) {
    if (!Array.isArray(pair) || typeof pair[0] !== 'string' || typeof pair[1] !== 'string' || pair[0].trim() === '') continue
    out = out.replace(new RegExp(`(?<![\\p{L}\\p{N}])${escapeRe(pair[0].trim())}(?![\\p{L}\\p{N}])`, 'giu'), pair[1])
  }
  return out
}

type Highlights = { set(name: string, h: unknown): void; delete(name: string): void }
const highlights = (): Highlights | null => {
  const reg = (globalThis as { CSS?: { highlights?: Highlights } }).CSS?.highlights
  return reg && typeof (globalThis as { Highlight?: unknown }).Highlight === 'function' ? reg : null
}

function articleOf(root: HTMLElement): HTMLElement {
  // Outside the post (a floating player added in the footer): the page's post content.
  return (root.closest('article, .entry-content, main') ?? document.querySelector('.entry-content, .wp-block-post-content, article, main') ?? document.body) as HTMLElement
}

export function mount(root: HTMLElement): void {
  const data = JSON.parse(root.dataset.postaloud ?? '{}') as Data
  const synth = (globalThis as { speechSynthesis?: SpeechSynthesis }).speechSynthesis
  // True for the browser voice, or after live audio failed and we switched to it.
  let local = data.clientSide
  let speed = data.speed || 1
  let playing = false
  let part = 0
  let parts = 1
  const audio = new Audio()

  // Browser voice state: the sentence list, where we are, and a generation counter so
  // callbacks from a canceled queue are ignored.
  let sentences: Sentence[] | null = null
  let index = 0
  let generation = 0

  const emit = (name: string, detail: Record<string, unknown> = {}) => {
    try {
      emitter()?.doAction?.('postaloud.player.event', name, detail, data)
    } catch {
      // A broken listener must not stop playback.
    }
  }
  const readNumbers = data.numbers ?? spokenApplies(data.lang)
  const spoken = (text: string) => {
    const said = applySay(text, data.say)
    return readNumbers ? spokenNumbers(said) : said
  }
  const sentencesOf = () => collectSentences(articleOf(root), 280, data.skip ?? '')

  root.innerHTML = ''
  for (const c of Array.isArray(data.classes) ? data.classes : []) if (typeof c === 'string' && /^[\w-]+$/.test(c)) root.classList.add(c)
  const bar = document.createElement('div')
  // On the bar itself: player.css sets the defaults on .lvp, so values on the root wouldn't reach it.
  for (const [k, v] of Object.entries(data.vars ?? {})) if (/^--lvp-[\w-]+$/.test(k) && typeof v === 'string') bar.style.setProperty(k, v)
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
  // Other recordings (for example a short summary): one toggle button per track.
  const tracks: Track[] = Array.isArray(data.tracks) ? data.tracks.filter((tr) => tr && typeof tr.file === 'string' && typeof tr.label === 'string') : []
  const trackButtons: HTMLButtonElement[] = []
  let track = -1 // -1: the article itself.
  if (tracks.length) {
    const group = document.createElement('div')
    group.className = 'lvp-tracks'
    group.setAttribute('role', 'group')
    group.setAttribute('aria-label', t('Choose what to play'))
    ;[{ label: t('Full article'), file: '' }, ...tracks].forEach((tr, i) => {
      const b = document.createElement('button')
      b.type = 'button'
      b.className = 'lvp-track'
      b.textContent = tr.label
      b.setAttribute('aria-pressed', String(i === 0))
      b.addEventListener('click', () => switchTrack(i - 1))
      trackButtons.push(b)
      group.append(b)
    })
    bar.append(group)
  }
  if (typeof data.download === 'string' && data.download) {
    const a = document.createElement('a')
    a.className = 'lvp-download'
    a.href = data.download
    a.setAttribute('download', '')
    a.setAttribute('aria-label', t('Download the audio'))
    a.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>'
    bar.append(a)
  }
  root.append(bar)
  watchTheme(root, data.theme ?? 'auto')

  const unsupported = local && !synth

  const render = () => {
    play.setAttribute('aria-label', playing ? t('Pause') : t('Play article'))
    play.innerHTML = playing
      ? '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>'
      : '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>'
    rate.textContent = `${speed}×`
    rate.setAttribute('aria-label', `${t('Playback speed')} ${speed}×`)
  }

  let painted: Range | null = null
  const paint = (range: Range | null) => {
    const reg = highlights()
    if (!reg || data.highlight === 'off') return
    if (range !== painted && !(range && painted && range.compareBoundaryPoints(Range.START_TO_START, painted) === 0 && range.compareBoundaryPoints(Range.END_TO_END, painted) === 0)) {
      painted = range
      emit('painted', { range })
    }
    if (!range) return reg.delete(HIGHLIGHT)
    const HighlightCtor = (globalThis as unknown as { Highlight: new (...r: Range[]) => unknown }).Highlight
    reg.set(HIGHLIGHT, new HighlightCtor(range))
  }
  // Browser voice: the sentence, through the same filter as timed audio (extensions may narrow it).
  const mark = (sentence: Sentence | null) => paint(filterRange(sentence?.range ?? null, { time: -1, index: sentence ? index : -1, sentence, marks: [], timings: null }))

  const finish = () => {
    playing = false
    index = 0
    mark(null)
    render()
    emit('progress', { fraction: 1 })
    emit('ended')
  }

  // Queue every sentence from `from` onwards. Chrome stops a single long utterance after
  // about 15 seconds, so short utterances also make long articles play to the end.
  const speakFrom = (from: number) => {
    if (!synth) return
    sentences ??= sentencesOf()
    const gen = ++generation
    synth.cancel()
    const lang = data.lang || document.documentElement.lang || 'en-US'
    // The title sits outside the post content in most themes, so read it first, unless the
    // content already starts with it. Google voices read it too (it's in the server's text).
    const title = (data.title ?? '').trim()
    const withTitle = from === 0 && title !== '' && !normalize(sentences[0]?.text ?? '').startsWith(normalize(title))
    if (sentences.length === 0 && !withTitle) return finish()
    if (withTitle) {
      const u = new SpeechSynthesisUtterance(spoken(title))
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
      const u = new SpeechSynthesisUtterance(spoken(sentence.text))
      u.lang = lang
      u.rate = speed
      u.onstart = () => {
        if (gen !== generation) return
        index = from + i
        mark(sentence)
        if (sentences!.length) emit('progress', { fraction: (from + i) / sentences!.length })
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
        sentences ??= sentencesOf()
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
        sentences ??= sentencesOf()
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
    if (audio.duration > 0 && Number.isFinite(audio.duration)) {
      // Live audio comes in parts; count each part as an equal share.
      const fraction = track >= 0 ? audio.currentTime / audio.duration : (part + audio.currentTime / audio.duration) / Math.max(1, data.file ? 1 : parts)
      emit('progress', { fraction: Math.min(1, fraction) })
    }
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
    if (track >= 0) {
      playing = false
      label.textContent = t("Audio isn't available right now. Try again in a few minutes.")
      return render()
    }
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
    if (track < 0 && part + 1 < parts) loadPart(++part)
    else {
      playing = false
      part = 0
      cursor = 0
      marks = fileTimings?.marks ?? []
      targets = fileTimings?.targets ?? []
      mark(null)
      render()
      emit('progress', { fraction: 1 })
      emit('ended')
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

  // The first play of a page view counts once (backend/Stats/Plays.php).
  let counted = false
  const count = () => {
    if (counted || !data.playUrl) return
    counted = true
    try {
      if (!navigator.sendBeacon?.(data.playUrl)) void fetch(data.playUrl, { method: 'POST', keepalive: true, credentials: 'omit' }).catch(() => {})
    } catch {
      // Counting must never stop playback.
    }
  }

  // Switch between the article (-1) and another track. Stops whatever is playing.
  const switchTrack = (i: number) => {
    if (i === track) return
    track = i
    trackButtons.forEach((b, n) => b.setAttribute('aria-pressed', String(n === i + 1)))
    synth?.cancel()
    audio.pause()
    audio.removeAttribute('src')
    playing = false
    part = 0
    cursor = 0
    marks = []
    targets = []
    fileTimings = null
    mark(null)
    render()
    emit('track', { index: i })
  }
  const playTrack = (tr: Track) => {
    audio.src = tr.file
    audio.playbackRate = speed
    start()
  }

  play.addEventListener('click', () => {
    playing = !playing
    if (playing) {
      count()
      emit('play', { track })
    } else emit('pause')
    if (playing && track >= 0 && tracks[track]) {
      if (!audio.src) playTrack(tracks[track])
      else start()
      return render()
    }
    if (!playing && track >= 0) {
      audio.pause()
      return render()
    }
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
    if (local && playing && track < 0) speakFrom(index)
    render()
    emit('speed', { speed })
  })

  render()
  if (unsupported) {
    play.disabled = true
    rate.disabled = true
    label.textContent = t("This browser can't read articles aloud.")
  }
}
