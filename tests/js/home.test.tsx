import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, expect, it, vi } from 'vitest'
import { App } from '../../frontend/admin/App'

vi.mock('../../frontend/admin/lib/sound', () => ({ SAMPLE_TEXT: 'Hello.', canSpeak: () => true, speak: vi.fn(async () => {}), playUrl: vi.fn(async () => {}) }))

const base = (extra: Record<string, unknown> = {}) => ({
  restUrl: '/wp-json/listenly/v1/',
  nonce: 'n',
  pageUrl: '/wp-admin/admin.php?page=listenly-voice',
  version: '0.1.0',
  settings: { provider: 'browser', voice: '', speed: 1.25, post_types: ['post'], position: 'below', highlight: 'sentence' },
  providers: [
    { id: 'browser', label: 'Browser voice', ready: true, clientSide: true, voices: [] },
    { id: 'google', label: 'Google Cloud', ready: false, clientSide: false, voices: [{ id: 'en-US-Neural2-F', label: 'en-US · Neural2-F', language: 'en-US' }] },
  ],
  googleKeySet: false,
  setupDone: true,
  postTypes: [{ id: 'post', label: 'Posts' }],
  recentPosts: [
    { id: 2, title: 'Quiet one', date: 'Nov 15', viewUrl: 'https://example.test/?p=2', editUrl: 'https://example.test/edit/2', status: { id: 'off', label: 'Switched off' } },
    { id: 1, title: 'Hello world', date: 'Nov 14', viewUrl: 'https://example.test/?p=1', editUrl: 'https://example.test/edit/1', status: { id: 'live', label: 'Plays live' } },
  ],
  userName: 'Sam',
  pro: false,
  ...extra,
})
const setConfig = (extra: Record<string, unknown> = {}) => {
  ;(window as unknown as { listenlyConfig: unknown }).listenlyConfig = base(extra)
}

beforeEach(() => setConfig())

it('opens on Home with the voice in use and no Save button', () => {
  render(<App />)
  expect(screen.getByRole('heading', { level: 1, name: 'Welcome back, Sam' })).toBeInTheDocument()
  expect(within(screen.getByRole('navigation')).getByRole('button', { name: 'Home' })).toHaveAttribute('aria-current', 'page')
  const card = screen.getByRole('region', { name: 'Your voice' })
  expect(card).toHaveTextContent('Browser voice')
  expect(card).toHaveTextContent('1.25×')
  expect(card).toHaveTextContent('End of the post')
  expect(card).toHaveTextContent('Ready')
  expect(screen.queryByRole('button', { name: 'Save changes' })).not.toBeInTheDocument()
})

it('links "View a post" to the newest post that shows the player', () => {
  render(<App />)
  expect(screen.getByRole('link', { name: /View a post/ })).toHaveAttribute('href', 'https://example.test/?p=1')
})

it('lists recent posts with their status in words', () => {
  render(<App />)
  const list = within(screen.getByRole('region', { name: 'Recent posts' }))
  expect(list.getAllByRole('listitem')).toHaveLength(2)
  expect(list.getByText('Switched off')).toBeInTheDocument()
  expect(list.getByRole('link', { name: 'Edit: Hello world' })).toHaveAttribute('href', 'https://example.test/edit/1')
})

it('opens a page from its tile', async () => {
  render(<App />)
  await userEvent.click(within(screen.getByRole('region', { name: 'Go to' })).getByRole('button', { name: /Player/ }))
  expect(within(screen.getByRole('navigation')).getByRole('button', { name: 'Player' })).toHaveAttribute('aria-current', 'page')
})

it('asks for a key when the chosen voice needs one', async () => {
  setConfig({ settings: { ...base().settings, provider: 'google', voice: 'en-US-Neural2-F' } })
  render(<App />)
  const card = within(screen.getByRole('region', { name: 'Your voice' }))
  expect(card.getByText('Needs a key')).toBeInTheDocument()
  expect(card.getByRole('button', { name: 'Play a sample' })).toBeDisabled()
  await userEvent.click(card.getByRole('button', { name: 'Add your key' }))
  expect(screen.getByLabelText('API key')).toBeInTheDocument()
})

it('points to the Player screen when no post type shows the player', async () => {
  setConfig({ settings: { ...base().settings, post_types: [] }, recentPosts: [] })
  render(<App />)
  await userEvent.click(screen.getByRole('button', { name: 'Choose where it shows' }))
  expect(within(screen.getByRole('navigation')).getByRole('button', { name: 'Player' })).toHaveAttribute('aria-current', 'page')
})

it('says when the player did not show on a post, why, and how to fix it', async () => {
  setConfig({ insertMiss: { title: 'Built with a page builder', viewUrl: 'https://example.test/?p=9', editUrl: 'https://example.test/edit/9', reason: 'content' } })
  render(<App />)
  const box = screen.getByRole('status', { name: 'The player didn’t show on a post' })
  expect(within(box).getByRole('link', { name: /Built with a page builder/ })).toHaveAttribute('href', 'https://example.test/?p=9')
  expect(box).toHaveTextContent('without the usual post content')
  expect(within(box).getByRole('link', { name: 'Edit the post' })).toHaveAttribute('href', 'https://example.test/edit/9')
  await userEvent.click(within(box).getByRole('button', { name: 'Change where it shows' }))
  expect(screen.getByRole('heading', { name: 'Where it shows' })).toBeInTheDocument()
})

it('hides the notice once the player floats, which always shows', () => {
  setConfig({
    settings: { provider: 'browser', voice: '', speed: 1, post_types: ['post'], position: 'floating', highlight: 'sentence' },
    insertMiss: { title: 'X', viewUrl: '#', editUrl: '', reason: 'loop' },
  })
  render(<App />)
  expect(screen.queryByRole('status', { name: 'The player didn’t show on a post' })).toBeNull()
})
