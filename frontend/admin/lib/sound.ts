// Plays voice samples in the admin. Kept apart so tests can replace it (jsdom has no audio).
export const SAMPLE_TEXT = 'Hello. This is how your posts will sound when visitors press play.'

export const canSpeak = (): boolean => typeof window !== 'undefined' && 'speechSynthesis' in window

/** Reads text with the browser's own voice. Resolves when it finishes. */
export function speak(text: string, lang = document.documentElement.lang || 'en-US'): Promise<void> {
  return new Promise((resolve) => {
    if (!canSpeak()) return resolve()
    window.speechSynthesis.cancel()
    const u = new SpeechSynthesisUtterance(text)
    u.lang = lang
    u.onend = () => resolve()
    u.onerror = () => resolve()
    window.speechSynthesis.speak(u)
  })
}

/** Plays an audio URL (a data: URL from /preview). */
export function playUrl(url: string): Promise<void> {
  return new Audio(url).play()
}
