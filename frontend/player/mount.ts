// One player. Browser voice → speechSynthesis, one sentence at a time with read-along highlighting;
// a ready audio file (data.file, filled by extensions) → plays it;
// server voices → MP3 parts from /wp-json/listenly/v1/audio/{post}/{part}.
import { collectSentences, type Sentence } from './sentences'

type Data = { post: number; clientSide: boolean; file?: string; audioUrl: string; speed: number; highlight: string; position: string; lang: string }

const t = (text: string) => (window as unknown as { wp?: { i18n?: { __: (s: string, d: string) => string } } }).wp?.i18n?.__(text, 'listenly-voice') ?? text
const SPEEDS = [0.75, 1, 1.25, 1.5, 2]
const HIGHLIGHT = 'listenly-sentence'

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

  const unsupported = data.clientSide && !synth

  const render = () => {
    play.setAttribute('aria-label', playing ? t('Pause') : t('Play article'))
    play.innerHTML = playing
      ? '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>'
      : '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>'
    rate.textContent = `${speed}×`
    rate.setAttribute('aria-label', `${t('Playback speed')} ${speed}×`)
  }

  const mark = (sentence: Sentence | null) => {
    const reg = highlights()
    if (!reg || data.highlight === 'off') return
    if (!sentence) return reg.delete(HIGHLIGHT)
    const HighlightCtor = (globalThis as unknown as { Highlight: new (...r: Range[]) => unknown }).Highlight
    reg.set(HIGHLIGHT, new HighlightCtor(sentence.range))
  }

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
    if (sentences.length === 0) return finish()
    sentences.slice(from).forEach((sentence, i) => {
      const u = new SpeechSynthesisUtterance(sentence.text)
      u.lang = data.lang || document.documentElement.lang || 'en-US'
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

  const loadPart = (n: number) => {
    audio.src = `${data.audioUrl}${n}`
    audio.playbackRate = speed
    void audio.play()
  }
  audio.addEventListener('ended', () => {
    if (part + 1 < parts) loadPart(++part)
    else {
      playing = false
      part = 0
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
    if (data.clientSide && synth) {
      if (!playing) synth.pause()
      else if (synth.paused) synth.resume()
      else speakFrom(index)
    } else if (playing) {
      if (audio.src) void audio.play()
      else if (data.file) {
        audio.src = data.file
        audio.playbackRate = speed
        void audio.play()
      } else void fetchParts().then(() => loadPart(0))
    } else {
      audio.pause()
    }
    render()
  })
  rate.addEventListener('click', () => {
    speed = SPEEDS[(SPEEDS.indexOf(speed) + 1) % SPEEDS.length] ?? 1
    audio.playbackRate = speed
    if (data.clientSide && playing) speakFrom(index)
    render()
  })

  render()
  if (unsupported) {
    play.disabled = true
    rate.disabled = true
    label.textContent = t("This browser can't read articles aloud.")
  }
}
