// Splits an article into sentences and maps each one to a DOM Range, so the player can read
// one sentence at a time (Chrome cuts off long utterances) and highlight it without touching markup.

export type Span = { start: number; end: number }
export type Sentence = { text: string; range: Range }

const SKIP = '.postaloud-player, script, style, noscript, figure, nav, button, select, textarea, [aria-hidden="true"]'
const BLOCK = /^(P|H[1-6]|LI|BLOCKQUOTE|PRE|TD|TH|DT|DD|FIGCAPTION|ARTICLE|SECTION|MAIN|DIV|HEADER|FOOTER|ASIDE|UL|OL|TABLE|TR)$/
const CLOSERS = `"'”’)]»`
// Lower-case words that end in a full stop without ending the sentence.
const ABBREVIATIONS = new Set(['mr', 'mrs', 'ms', 'dr', 'prof', 'st', 'jr', 'sr', 'vs', 'etc', 'e.g', 'i.e', 'a.m', 'p.m', 'no', 'fig', 'approx', 'inc', 'ltd', 'co'])

function isAbbreviation(text: string, dot: number): boolean {
  let i = dot - 1
  while (i >= 0 && !/\s/.test(text[i])) i--
  const word = text.slice(i + 1, dot).toLowerCase().replace(/^[^a-z.]+/, '')
  return ABBREVIATIONS.has(word)
}

function trimSpan(text: string, start: number, end: number): Span | null {
  while (start < end && /\s/.test(text[start])) start++
  while (end > start && /\s/.test(text[end - 1])) end--
  return end > start ? { start, end } : null
}

/** Cuts a span longer than `max` at the last comma, semicolon or colon, else the last space. */
function cutLong(text: string, span: Span, max: number): Span[] {
  const out: Span[] = []
  let { start } = span
  while (span.end - start > max) {
    const window = text.slice(start, start + max)
    let cut = Math.max(window.lastIndexOf(', '), window.lastIndexOf('; '), window.lastIndexOf(': '))
    cut = cut > 0 ? cut + 1 : window.lastIndexOf(' ')
    if (cut <= 0) cut = max
    const part = trimSpan(text, start, start + cut)
    if (part) out.push(part)
    start += cut
  }
  const rest = trimSpan(text, start, span.end)
  if (rest) out.push(rest)
  return out
}

/** Sentence spans (offsets into `text`), trimmed, each at most `max` characters. */
export function splitSentences(text: string, max = 280): Span[] {
  const spans: Span[] = []
  let start = 0
  for (let i = 0; i < text.length; i++) {
    const ch = text[i]
    if (ch !== '.' && ch !== '!' && ch !== '?') continue
    let end = i + 1
    while (end < text.length && (CLOSERS.includes(text[end]) || '.!?'.includes(text[end]))) end++
    const atBreak = end === text.length || /\s/.test(text[end])
    if (!atBreak) continue
    if (ch === '.' && isAbbreviation(text, i)) continue
    const span = trimSpan(text, start, end)
    if (span) spans.push(span)
    start = end
    i = end - 1
  }
  const tail = trimSpan(text, start, text.length)
  if (tail) spans.push(tail)
  return spans.flatMap((s) => (s.end - s.start > max ? cutLong(text, s, max) : [s]))
}

function blockOf(node: Node, root: HTMLElement): Element {
  let el = node.parentElement
  while (el && el !== root && !BLOCK.test(el.tagName)) el = el.parentElement
  return el ?? root
}

/**
 * Sentences of an article in reading order. Text in separate blocks (paragraphs, headings,
 * list items) never joins into one sentence.
 */
export function collectSentences(root: HTMLElement, max = 280, skip = ''): Sentence[] {
  const doc = root.ownerDocument
  let selector = SKIP
  if (skip.trim()) {
    // An invalid selector from settings must not break reading: ignore it.
    try {
      doc.createDocumentFragment().querySelector(skip)
      selector = `${SKIP}, ${skip}`
    } catch {
      selector = SKIP
    }
  }
  const walker = doc.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
    acceptNode: (node) => (node.parentElement?.closest(selector) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
  })

  // Group text nodes by their block, keeping each node's offset in the group's text.
  const groups: { block: Element; text: string; nodes: { node: Text; at: number }[] }[] = []
  for (let n = walker.nextNode(); n; n = walker.nextNode()) {
    const node = n as Text
    const block = blockOf(node, root)
    let group = groups[groups.length - 1]
    if (!group || group.block !== block) {
      group = { block, text: '', nodes: [] }
      groups.push(group)
    }
    group.nodes.push({ node, at: group.text.length })
    group.text += node.data
  }

  const locate = (nodes: { node: Text; at: number }[], offset: number, isEnd: boolean) => {
    for (let i = nodes.length - 1; i >= 0; i--) {
      const { node, at } = nodes[i]
      if (offset > at || (offset === at && !isEnd)) return { node, offset: Math.min(offset - at, node.data.length) }
    }
    return { node: nodes[0].node, offset: 0 }
  }

  const sentences: Sentence[] = []
  for (const group of groups) {
    for (const span of splitSentences(group.text, max)) {
      const range = doc.createRange()
      const a = locate(group.nodes, span.start, false)
      const b = locate(group.nodes, span.end, true)
      range.setStart(a.node, a.offset)
      range.setEnd(b.node, b.offset)
      sentences.push({ text: group.text.slice(span.start, span.end).replace(/\s+/g, ' '), range })
    }
  }
  return sentences
}
