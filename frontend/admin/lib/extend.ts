// Extension points for other plugins, through wp.hooks filters. Free works the same without them.
//   listenly.admin.pages          (pages: AdminPage[])                → extra sidebar pages
//   listenly.admin.providerPanel  (panel: ReactNode | null, ctx)      → connection card for a provider
//   listenly.admin.highlightModes (modes: HighlightMode[])            → extra read-along modes
//   listenly.admin.edition        (label: string)                     → the header chip
import type { ReactNode } from 'react'
import type { Provider, Settings } from './config'

export type PageContext = { settings: Settings; onChange: (s: Settings) => void }
// hideSave: the page saves on its own, so the top bar's Save button is hidden.
// description: one line on the Home tile; icon: an Icon name ('page' when missing).
export type AdminPage = { id: string; label: string; description?: string; icon?: string; render: (ctx: PageContext) => ReactNode; hideSave?: boolean }
export type HighlightMode = { id: string; label: string; description: string }

type Hooks = { applyFilters: (name: string, value: unknown, ...args: unknown[]) => unknown }
const hooks = (): Hooks | null => (window as unknown as { wp?: { hooks?: Hooks } }).wp?.hooks ?? null
const filter = <T>(name: string, value: T, ...args: unknown[]): unknown => hooks()?.applyFilters(name, value, ...args) ?? value

const isObject = (v: unknown): v is Record<string, unknown> => typeof v === 'object' && v !== null

/** Extra pages, minus malformed entries and ids that clash with `taken`. */
export function extraPages(taken: string[]): AdminPage[] {
  const pages = filter<AdminPage[]>('listenly.admin.pages', [])
  if (!Array.isArray(pages)) return []
  const seen = new Set(taken)
  return pages.filter((p): p is AdminPage => {
    const ok = isObject(p) && typeof p.id === 'string' && p.id !== '' && typeof p.label === 'string' && typeof p.render === 'function' && !seen.has(p.id)
    if (ok) seen.add((p as AdminPage).id)
    return ok
  })
}

/** A connection card an extension gives for `provider`, or null. */
export function providerPanel(provider: Provider, ctx: PageContext): ReactNode {
  return (filter<ReactNode>('listenly.admin.providerPanel', null, { provider, ...ctx }) as ReactNode) ?? null
}

/** Extra read-along modes besides sentence highlighting. */
export function highlightModes(): HighlightMode[] {
  const modes = filter<HighlightMode[]>('listenly.admin.highlightModes', [])
  return Array.isArray(modes) ? modes.filter((m): m is HighlightMode => isObject(m) && typeof m.id === 'string' && m.id !== '' && m.id !== 'off' && m.id !== 'sentence' && typeof m.label === 'string') : []
}

/** The header chip, e.g. "Free · v0.1.0". */
export function edition(fallback: string): string {
  const label = filter<string>('listenly.admin.edition', fallback)
  return typeof label === 'string' && label !== '' ? label : fallback
}
