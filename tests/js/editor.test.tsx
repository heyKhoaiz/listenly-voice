import { createElement, Fragment, useSyncExternalStore, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { META, registerPostToggle, type WpGlobals } from '../../frontend/editor/editor'

type Plugin = { render: () => ReactNode }

// A stand-in for the bits of window.wp the panel uses, backed by a tiny reactive store
// that behaves like core/editor's edited post meta.
function fakeWp(meta: Record<string, unknown> = {}, opts: { panelIn?: 'editor' | 'editPost' | 'none' } = {}) {
  let state = { meta }
  const listeners = new Set<() => void>()
  const editPost = vi.fn((edits: { meta: Record<string, unknown> }) => {
    state = { meta: { ...state.meta, ...edits.meta } }
    listeners.forEach((l) => l())
  })
  const plugins: Record<string, Plugin> = {}

  const Panel = ({ title, children }: { title: string; children?: ReactNode }) =>
    createElement('section', { 'aria-label': title }, createElement('h2', null, title), children)
  const ToggleControl = ({ label, help, checked, onChange }: { label: string; help?: string; checked: boolean; onChange: (v: boolean) => void }) =>
    createElement(
      Fragment,
      null,
      createElement('label', null, createElement('input', { type: 'checkbox', checked, onChange: (e: { target: { checked: boolean } }) => onChange(e.target.checked) }), label),
      help ? createElement('p', null, help) : null,
    )

  const panelIn = opts.panelIn ?? 'editor'
  const wp: WpGlobals = {
    element: { createElement },
    i18n: { __: (s: string) => s },
    components: { ToggleControl },
    data: {
      useSelect: (pick) =>
        useSyncExternalStore(
          (cb) => (listeners.add(cb), () => listeners.delete(cb)),
          () => pick(() => ({ getEditedPostAttribute: () => state.meta })),
        ),
      useDispatch: () => ({ editPost }),
    },
    plugins: { registerPlugin: (name: string, settings: Plugin) => void (plugins[name] = settings) },
    editor: panelIn === 'editor' ? { PluginDocumentSettingPanel: Panel } : {},
    editPost: panelIn === 'editPost' ? { PluginDocumentSettingPanel: Panel } : undefined,
  }
  return { wp, plugins, editPost }
}

function show(plugins: Record<string, Plugin>) {
  const Plugin = plugins['postaloud'].render
  return render(createElement(Plugin))
}

describe('per-post audio switch in the block editor', () => {
  it('registers a document settings panel', () => {
    const { wp, plugins } = fakeWp()
    expect(registerPostToggle(wp)).toBe(true)
    show(plugins)
    expect(screen.getByRole('region', { name: 'PostAloud' })).toBeInTheDocument()
  })

  it('is on by default, when the post has no setting', () => {
    const { wp, plugins } = fakeWp()
    registerPostToggle(wp)
    show(plugins)
    expect(screen.getByRole('checkbox', { name: 'Audio player on this post' })).toBeChecked()
    expect(screen.getByText('Readers can listen to this post.')).toBeInTheDocument()
  })

  it('turning it off saves the post meta as disabled', async () => {
    const { wp, plugins, editPost } = fakeWp()
    registerPostToggle(wp)
    show(plugins)
    await userEvent.click(screen.getByRole('checkbox', { name: 'Audio player on this post' }))
    expect(editPost).toHaveBeenCalledWith({ meta: { [META]: true } })
    expect(screen.getByRole('checkbox', { name: 'Audio player on this post' })).not.toBeChecked()
    expect(screen.getByText("Readers won't see the player on this post.")).toBeInTheDocument()
  })

  it('shows off for a post that already has audio turned off, and turns it back on', async () => {
    const { wp, plugins, editPost } = fakeWp({ [META]: true })
    registerPostToggle(wp)
    show(plugins)
    const box = screen.getByRole('checkbox', { name: 'Audio player on this post' })
    expect(box).not.toBeChecked()
    await userEvent.click(box)
    expect(editPost).toHaveBeenCalledWith({ meta: { [META]: false } })
    expect(box).toBeChecked()
  })

  it('keeps other meta untouched when editing', async () => {
    const { wp, plugins, editPost } = fakeWp({ other_key: 'x' })
    registerPostToggle(wp)
    show(plugins)
    await userEvent.click(screen.getByRole('checkbox'))
    expect(editPost.mock.calls[0][0]).toEqual({ meta: { [META]: true } })
  })

  it('uses wp.editPost on WordPress before 6.6', () => {
    const { wp, plugins } = fakeWp({}, { panelIn: 'editPost' })
    expect(registerPostToggle(wp)).toBe(true)
    show(plugins)
    expect(screen.getByRole('checkbox', { name: 'Audio player on this post' })).toBeInTheDocument()
  })

  it('does nothing when the editor has no settings panel slot', () => {
    const { wp, plugins } = fakeWp({}, { panelIn: 'none' })
    expect(registerPostToggle(wp)).toBe(false)
    expect(plugins).toEqual({})
  })

  it('does nothing without window.wp', () => {
    expect(registerPostToggle(undefined)).toBe(false)
  })
})
