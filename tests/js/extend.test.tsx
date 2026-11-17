import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { App } from '../../frontend/admin/App'
import type { Settings } from '../../frontend/admin/lib/config'

// A small stand-in for wp.hooks: filters run in the order they were added.
type Fn = (value: unknown, ...args: unknown[]) => unknown
let filters: Record<string, Fn[]>
const addFilter = (name: string, fn: Fn) => (filters[name] ??= []).push(fn)

const base = (over: Partial<Settings> = {}) => ({
  restUrl: '/wp-json/postaloud/v1/',
  nonce: 'n',
  pageUrl: '/wp-admin/admin.php?page=postaloud',
  version: '0.1.0',
  settings: { provider: 'browser', voice: '', speed: 1, post_types: ['post'], position: 'above', highlight: 'sentence', ...over },
  providers: [
    { id: 'browser', label: 'Browser voice', ready: true, clientSide: true, voices: [] },
    { id: 'google', label: 'Google Cloud', ready: false, clientSide: false, voices: [] },
    { id: 'acme', label: 'Acme Voices', ready: false, clientSide: false, voices: [{ id: 'v1', label: 'Voice one', language: 'en' }] },
  ],
  googleKeySet: false,
  setupDone: true,
  postTypes: [{ id: 'post', label: 'Posts' }],
  pro: false,
})

beforeEach(() => {
  filters = {}
  ;(window as unknown as { wp: unknown }).wp = {
    hooks: { applyFilters: (name: string, value: unknown, ...args: unknown[]) => (filters[name] ?? []).reduce((v, fn) => fn(v, ...args), value) },
  }
  ;(window as unknown as { postaloudConfig: unknown }).postaloudConfig = base()
})

afterEach(() => {
  delete (window as unknown as { wp?: unknown }).wp
})

const nav = () => within(screen.getByRole('navigation'))

describe('admin extension points', () => {
  it('adds pages from the postaloud.admin.pages filter to the sidebar, and renders them with the settings', async () => {
    addFilter('postaloud.admin.pages', (pages) => [
      ...(pages as unknown[]),
      { id: 'library', label: 'Library', render: ({ settings }: { settings: Settings }) => <p>Library for {settings.provider}</p> },
    ])
    render(<App />)
    await userEvent.click(nav().getByRole('button', { name: 'Library' }))
    expect(screen.getByText('Library for browser')).toBeInTheDocument()
    expect(nav().getByRole('button', { name: 'Library' })).toHaveAttribute('aria-current', 'page')
  })

  it('ignores page entries without an id, label and render function, and ids that clash', () => {
    addFilter('postaloud.admin.pages', () => [{ id: 'x' }, { label: 'No id', render: () => null }, { id: 'voices', label: 'Clash', render: () => null }, 'junk'])
    render(<App />)
    expect(nav().getAllByRole('button', { name: /Voices|Player|Clash|No id/ }).map((b) => b.textContent)).toEqual(['Voices', 'Player'])
  })

  it('works without wp.hooks', () => {
    delete (window as unknown as { wp?: unknown }).wp
    render(<App />)
    expect(screen.getByRole('button', { name: 'Voices' })).toBeInTheDocument()
  })

  it('shows the connection panel an extension gives for a provider other than Google', async () => {
    addFilter('postaloud.admin.providerPanel', (panel, ctx) => {
      const { provider } = ctx as { provider: { id: string; label: string } }
      return provider.id === 'acme' ? <section aria-label="Acme connection">Connect {provider.label}</section> : panel
    })
    render(<App />)
    await userEvent.click(nav().getByRole('button', { name: 'Voices' }))
    expect(screen.queryByRole('region', { name: 'Acme connection' })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('radio', { name: /Acme Voices/ }))
    expect(screen.getByRole('region', { name: 'Acme connection' })).toHaveTextContent('Connect Acme Voices')
  })

  it('offers extra highlight modes on the Player screen and saves the choice', async () => {
    addFilter('postaloud.admin.highlightModes', (modes) => [...(modes as unknown[]), { id: 'word', label: 'Each word', description: 'Follows word by word.' }])
    render(<App />)
    await userEvent.click(nav().getByRole('button', { name: 'Player' }))
    const group = screen.getByRole('radiogroup', { name: 'What to highlight' })
    expect(group).toBeInTheDocument()
    await userEvent.click(screen.getByRole('radio', { name: /Each word/ }))
    expect(screen.getByRole('radio', { name: /Each word/ })).toHaveAttribute('aria-checked', 'true')
    await userEvent.click(screen.getByRole('radio', { name: /Each sentence/ }))
    expect(screen.getByRole('radio', { name: /Each sentence/ })).toHaveAttribute('aria-checked', 'true')
  })

  it('shows no mode choice when there are no extra modes', async () => {
    render(<App />)
    await userEvent.click(nav().getByRole('button', { name: 'Player' }))
    expect(screen.queryByRole('radiogroup', { name: 'What to highlight' })).not.toBeInTheDocument()
  })

  it('hides the mode choice while highlighting is off', async () => {
    addFilter('postaloud.admin.highlightModes', () => [{ id: 'word', label: 'Each word', description: '' }])
    ;(window as unknown as { postaloudConfig: unknown }).postaloudConfig = base({ highlight: 'off' })
    render(<App />)
    await userEvent.click(nav().getByRole('button', { name: 'Player' }))
    expect(screen.queryByRole('radiogroup', { name: 'What to highlight' })).not.toBeInTheDocument()
  })

  it('lets an extension name the edition in the header', () => {
    addFilter('postaloud.admin.edition', () => 'Plus · v2.0.0')
    render(<App />)
    expect(screen.getByText('Plus · v2.0.0')).toBeInTheDocument()
  })

  it('shows the extension’s connection panel in the setup wizard too', async () => {
    addFilter('postaloud.admin.providerPanel', (panel, ctx) => {
      const { provider } = ctx as { provider: { id: string } }
      return provider.id === 'acme' ? <section aria-label="Acme connection">Connect</section> : panel
    })
    ;(window as unknown as { postaloudConfig: Record<string, unknown> }).postaloudConfig = { ...base(), setupDone: false }
    render(<App />)
    expect(screen.queryByRole('region', { name: 'Acme connection' })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('radio', { name: /Acme Voices/ }))
    expect(screen.getByRole('region', { name: 'Acme connection' })).toBeInTheDocument()
  })

  it('hides the top Save button on extension pages that save on their own', async () => {
    addFilter('postaloud.admin.pages', () => [
      { id: 'own', label: 'Own save', render: () => <p>Own</p>, hideSave: true },
      { id: 'shared', label: 'Shared', render: () => <p>Shared</p> },
    ])
    render(<App />)
    await userEvent.click(nav().getByRole('button', { name: 'Own save' }))
    expect(screen.queryByRole('button', { name: 'Save changes' })).not.toBeInTheDocument()
    await userEvent.click(nav().getByRole('button', { name: 'Shared' }))
    expect(screen.getByRole('button', { name: 'Save changes' })).toBeInTheDocument()
  })
})
