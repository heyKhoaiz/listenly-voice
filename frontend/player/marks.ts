// Read-along for server-side voices: the server returns when each of its sentences starts in
// the audio; this matches those sentences to the ones on the page, by text.
import type { Sentence } from './sentences'

export type Mark = { t: number; text: string }

/** Lower-case letters and digits only, so punctuation, quotes and spacing don't matter. */
export const normalize = (text: string) => text.toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '')

// Match on the start of a sentence: the server's text and the page can differ further in.
const PROBE = 40

/**
 * The page sentence for each mark (null when it isn't on the page, like the post title), and
 * where to carry on searching for the next part.
 */
export function alignMarks(marks: Mark[], sentences: Sentence[], cursor: number): { targets: (Sentence | null)[]; cursor: number } {
  const starts: number[] = []
  let flat = ''
  for (const s of sentences) {
    starts.push(flat.length)
    flat += normalize(s.text)
  }
  const targets = marks.map((mark) => {
    const probe = normalize(mark.text).slice(0, PROBE)
    const at = probe ? flat.indexOf(probe, cursor) : -1
    if (at < 0) return null
    cursor = at + probe.length
    let i = starts.length - 1
    while (i > 0 && starts[i] > at) i--
    return sentences[i] ?? null
  })
  return { targets, cursor }
}

/** Index of the last mark that has started at `time` seconds, or -1. */
export function markAt(marks: Mark[], time: number): number {
  let found = -1
  for (let i = 0; i < marks.length && marks[i].t <= time; i++) found = i
  return found
}
