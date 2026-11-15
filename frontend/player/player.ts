// The frontend player. No React, no dependencies: it must stay small.
// Reads data-listenly (backend/Player/Player.php). Browser voice → speechSynthesis;
// a ready audio file (data.file, filled by extensions) → plays it;
// server voices → MP3 parts from /wp-json/listenly/v1/audio/{post}/{part}.
import './player.css'

type Data = { post: number; clientSide: boolean; file?: string; audioUrl: string; speed: number; highlight: string; position: string; lang: string }

const t = (text: string) => (window as unknown as { wp?: { i18n?: { __: (s: string, d: string) => string } } }).wp?.i18n?.__(text, 'listenly-voice') ?? text
const SPEEDS = [0.75, 1, 1.25, 1.5, 2]

function articleText(root: HTMLElement): string {
  const article = root.closest('article, .entry-content, main') ?? document.body
  const clone = article.cloneNode(true) as HTMLElement
  clone.querySelectorAll('.listenly-player, script, style, noscript, figure, nav').forEach((n) => n.remove())
  return (clone.textContent ?? '').replace(/\s+/g, ' ').trim()
}

function mount(root: HTMLElement) {
  const data = JSON.parse(root.dataset.listenly ?? '{}') as Data
  let speed = data.speed || 1
  let playing = false
  let part = 0
  let parts = 1
  const audio = new Audio()

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

  const render = () => {
    play.setAttribute('aria-label', playing ? t('Pause') : t('Play article'))
    play.innerHTML = playing
      ? '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>'
      : '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>'
    rate.textContent = `${speed}×`
    rate.setAttribute('aria-label', `${t('Playback speed')} ${speed}×`)
  }

  const speak = () => {
    const synth = window.speechSynthesis
    if (synth.paused) return synth.resume()
    synth.cancel()
    const u = new SpeechSynthesisUtterance(articleText(root))
    u.lang = data.lang || document.documentElement.lang || 'en-US'
    u.rate = speed
    u.onend = () => { playing = false; render() }
    synth.speak(u)
  }

  const loadPart = (n: number) => {
    audio.src = `${data.audioUrl}${n}`
    audio.playbackRate = speed
    void audio.play()
  }
  audio.addEventListener('ended', () => {
    if (part + 1 < parts) loadPart(++part)
    else { playing = false; part = 0; render() }
  })
  audio.addEventListener('loadedmetadata', () => { audio.playbackRate = speed })
  // How many parts the post has: a cheap route, no synthesis.
  const fetchParts = () =>
    fetch(`${data.audioUrl}info`)
      .then((r) => r.json())
      .then((j: { parts?: number }) => { parts = Number(j.parts) || 1 })
      .catch(() => {})

  play.addEventListener('click', () => {
    playing = !playing
    if (data.clientSide) {
      if (playing) speak()
      else window.speechSynthesis.pause()
    } else if (playing) {
      if (audio.src) void audio.play()
      else if (data.file) { audio.src = data.file; audio.playbackRate = speed; void audio.play() }
      else void fetchParts().then(() => loadPart(0))
    } else {
      audio.pause()
    }
    render()
  })
  rate.addEventListener('click', () => {
    speed = SPEEDS[(SPEEDS.indexOf(speed) + 1) % SPEEDS.length] ?? 1
    audio.playbackRate = speed
    if (data.clientSide && playing) speak()
    render()
  })
  render()
}

const start = () => document.querySelectorAll<HTMLElement>('.listenly-player[data-listenly]').forEach(mount)
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start)
else start()
