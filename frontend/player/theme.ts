// Light or dark player. 'auto' reads the first solid background behind the player and
// checks again when the theme switches (a class or style change on <html>/<body>) or the
// visitor's dark-mode setting changes. The read-along highlight follows through a class on
// <html>, because it paints the post text, not the player.
const DARK = 'listenly-player--dark'
const PAGE_DARK = 'listenly-dark'

/** sRGB relative luminance, 0 (black) to 1 (white); null for a see-through colour. */
export function luminance(color: string): number | null {
  const m = color.match(/rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:[\s,/]+([\d.]+%?))?\s*\)/)
  if (!m) return null
  const alpha = m[4] === undefined ? 1 : m[4].endsWith('%') ? parseFloat(m[4]) / 100 : parseFloat(m[4])
  if (alpha < 0.5) return null
  const [r, g, b] = [m[1], m[2], m[3]].map((v) => {
    const c = parseFloat(v) / 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/** True when the page behind `el` is dark. A page with no background is the browser's white. */
export function pageIsDark(el: HTMLElement): boolean {
  for (let node = el.parentElement; node; node = node.parentElement) {
    const lum = luminance(getComputedStyle(node).backgroundColor)
    if (lum !== null) return lum < 0.18
  }
  return false
}

export function applyTheme(root: HTMLElement, dark: boolean): void {
  if (root.classList.contains(DARK) !== dark) root.classList.toggle(DARK, dark)
  const html = document.documentElement
  if (html.classList.contains(PAGE_DARK) !== dark) html.classList.toggle(PAGE_DARK, dark)
}

// True when a mutation only added or removed our own page class: reacting to it would let two
// players that disagree flip <html> back and forth forever.
const onlyOurClass = (m: MutationRecord): boolean => {
  if (m.attributeName !== 'class' || m.target !== document.documentElement) return false
  const strip = (v: string | null) => (v ?? '').split(/\s+/).filter((c) => c && c !== PAGE_DARK).sort().join(' ')
  return strip(m.oldValue) === strip(document.documentElement.getAttribute('class'))
}

export function watchTheme(root: HTMLElement, theme: string): void {
  if (theme === 'light' || theme === 'dark') return applyTheme(root, theme === 'dark')
  const check = () => applyTheme(root, pageIsDark(root))
  check()
  const media = window.matchMedia?.('(prefers-color-scheme: dark)')
  media?.addEventListener?.('change', check)
  if (typeof MutationObserver === 'undefined') return
  const observer = new MutationObserver((records) => {
    if (!root.isConnected) {
      observer.disconnect()
      media?.removeEventListener?.('change', check)
      return
    }
    if (records.every(onlyOurClass)) return
    check()
  })
  const options = { attributes: true, attributeOldValue: true, attributeFilter: ['class', 'style', 'data-theme'] }
  observer.observe(document.documentElement, options)
  if (document.body) observer.observe(document.body, options)
}
