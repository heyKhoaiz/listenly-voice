// Plays voice samples in the admin. Kept apart so tests can replace it (jsdom has no audio).
import { __ } from './i18n'

export const SAMPLE_TEXT = 'Hello. This is how your posts will sound when visitors press play.'

export const canSpeak = (): boolean => typeof window !== 'undefined' && 'speechSynthesis' in window

// Chrome fills the voice list asynchronously; wait briefly for it.
function loadVoices(synth: SpeechSynthesis): Promise<SpeechSynthesisVoice[]> {
  const now = synth.getVoices()
  if (now.length) return Promise.resolve(now)
  return new Promise((resolve) => {
    const done = () => {
      synth.removeEventListener('voiceschanged', done)
      clearTimeout(timer)
      resolve(synth.getVoices())
    }
    const timer = setTimeout(done, 1500)
    synth.addEventListener('voiceschanged', done)
  })
}

function pickVoice(voices: SpeechSynthesisVoice[], lang: string): SpeechSynthesisVoice | undefined {
  const want = lang.toLowerCase()
  const base = want.split('-')[0]
  return (
    voices.find((v) => v.lang.toLowerCase() === want) ??
    voices.find((v) => v.lang.toLowerCase().split(/[-_]/)[0] === base) ??
    voices.find((v) => v.default)
  )
}

// Held so Chrome doesn't garbage-collect the utterance and drop its end event.
const live = new Set<SpeechSynthesisUtterance>()

const noVoice = () => new Error(__('This browser has no voice installed, so it cannot read aloud. Try another browser, or pick a Google voice.', 'listenly-voice'))

/** Reads text with the browser's own voice. Resolves when it finishes, rejects if it can't speak. */
export async function speak(text: string, lang = document.documentElement.lang || 'en-US', rate = 1): Promise<void> {
  if (!canSpeak()) throw noVoice()
  const synth = window.speechSynthesis
  synth.cancel()
  const voices = await loadVoices(synth)
  const u = new SpeechSynthesisUtterance(text)
  u.lang = lang
  u.rate = rate
  const voice = pickVoice(voices, lang)
  if (voice) u.voice = voice
  live.add(u)

  await new Promise<void>((resolve, reject) => {
    let started = false
    // No start within a few seconds means the engine is missing or stuck (Linux without speech-dispatcher).
    const watchdog = setTimeout(() => {
      if (started) return
      synth.cancel()
      reject(noVoice())
    }, 4000)
    const settle = (fn: () => void) => {
      clearTimeout(watchdog)
      live.delete(u)
      fn()
    }
    u.onstart = () => {
      started = true
    }
    u.onend = () => settle(resolve)
    u.onerror = (e) => {
      if (e.error === 'interrupted' || e.error === 'canceled') return settle(resolve)
      settle(() => reject(voices.length ? new Error(`${__('The browser voice failed:', 'listenly-voice')} ${e.error}`) : noVoice()))
    }
    // Chrome drops a speak() issued in the same tick as cancel().
    setTimeout(() => {
      synth.resume()
      synth.speak(u)
    }, 50)
  })
}

/** Plays an audio URL (a data: URL from /preview). */
export function playUrl(url: string): Promise<void> {
  return new Audio(url).play()
}
