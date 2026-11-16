import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { App } from '../../frontend/admin/App'

let saved: Record<string, unknown> | undefined

beforeEach(() => {
  saved = undefined
  ;(window as unknown as { listenlyConfig: unknown }).listenlyConfig = {
    restUrl: '/wp-json/listenly/v1/',
    nonce: 'n',
    pageUrl: '/wp-admin/admin.php?page=listenly-voice',
    version: '0.1.0',
    settings: { provider: 'browser', voice: '', speed: 1, post_types: ['post'], position: 'below', highlight: 'sentence' },
    providers: [{ id: 'browser', label: 'Browser voice', ready: true, clientSide: true, voices: [] }],
    googleKeySet: false,
    setupDone: true,
    postTypes: [
      { id: 'post', label: 'Posts' },
      { id: 'page', label: 'Pages' },
    ],
    pro: false,
  }
  vi.stubGlobal(
    'fetch',
    vi.fn(async (_url: string, init: RequestInit = {}) => {
      saved = JSON.parse(String(init.body))
      return new Response(JSON.stringify(saved), { status: 200 })
    }),
  )
})

async function openPlayer() {
  render(<App />)
  const u = userEvent.setup()
  await u.click(screen.getByRole('button', { name: 'Player' }))
  return u
}

describe('Player screen', () => {
  it('replaces the placeholder with real settings', async () => {
    await openPlayer()
    expect(screen.queryByText('Player position and style settings come next.')).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Where it shows' })).toBeInTheDocument()
  })

  it('shows the saved placement and post types', async () => {
    await openPlayer()
    expect(screen.getByRole('radio', { name: 'End of the post' })).toHaveAttribute('aria-checked', 'true')
    expect(screen.getByRole('checkbox', { name: 'Posts' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Pages' })).not.toBeChecked()
  })

  it('saves a new placement, post types and highlighting with "Save changes"', async () => {
    const u = await openPlayer()
    await u.click(screen.getByRole('radio', { name: 'Floating button' }))
    await u.click(screen.getByRole('checkbox', { name: 'Pages' }))
    await u.click(screen.getByRole('switch', { name: 'Highlight the sentence being read' }))
    await u.click(screen.getByRole('button', { name: 'Save changes' }))
    expect(saved).toMatchObject({ position: 'floating', post_types: ['post', 'page'], highlight: 'off' })
    expect(await screen.findByText('Settings saved.')).toBeInTheDocument()
  })

  it('shows the highlight switch as on by default and toggles aria-checked', async () => {
    const u = await openPlayer()
    const toggle = screen.getByRole('switch', { name: 'Highlight the sentence being read' })
    expect(toggle).toHaveAttribute('aria-checked', 'true')
    await u.click(toggle)
    expect(toggle).toHaveAttribute('aria-checked', 'false')
  })

  it('warns, without blocking, when no post type is ticked', async () => {
    const u = await openPlayer()
    await u.click(screen.getByRole('checkbox', { name: 'Posts' }))
    expect(screen.getByText('The player is off everywhere. Tick a post type, or add [listenly_player] where you want it.')).toBeInTheDocument()
  })

  it('shows the shortcode for placing the player by hand', async () => {
    await openPlayer()
    const box = screen.getByRole('heading', { name: 'Place it by hand' }).closest('section')!
    expect(within(box).getByText('[listenly_player]')).toBeInTheDocument()
  })

  it('offers light, dark and auto colours, auto by default, and saves the choice', async () => {
    const u = await openPlayer()
    const group = within(screen.getByRole('radiogroup', { name: 'Player colours' }))
    expect(group.getByRole('radio', { name: /Auto/ })).toHaveAttribute('aria-checked', 'true')
    await u.click(group.getByRole('radio', { name: /^Dark/ }))
    expect(group.getByRole('radio', { name: /^Dark/ })).toHaveAttribute('aria-checked', 'true')
    await u.click(screen.getByRole('button', { name: 'Save changes' }))
    expect(saved).toMatchObject({ theme: 'dark' })
  })

  it('adds pronunciation words, saves them and stops at the limit', async () => {
    ;(window as unknown as { listenlyConfig: { sayLimit: number } }).listenlyConfig.sayLimit = 2
    const u = await openPlayer()
    expect(screen.getByText(/No words yet/)).toBeInTheDocument()
    await u.click(screen.getByRole('button', { name: 'Add a word' }))
    const row = screen.getByRole('group', { name: 'Word 1' })
    await u.type(within(row).getByLabelText('Written'), 'WP')
    await u.type(within(row).getByLabelText('Said as'), 'WordPress')
    await u.click(screen.getByRole('button', { name: 'Add a word' }))
    expect(screen.getByRole('button', { name: 'Add a word' })).toBeDisabled()
    expect(screen.getByText('2 / 2')).toBeInTheDocument()
    await u.click(screen.getByRole('button', { name: 'Remove word 2' }))
    await u.click(screen.getByRole('button', { name: 'Save changes' }))
    expect(saved?.say).toEqual([['WP', 'WordPress']])
  })
})
