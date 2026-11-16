import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { App } from '../../frontend/admin/App'

vi.mock('../../frontend/admin/lib/sound', () => ({
  SAMPLE_TEXT: 'Hello sample.',
  canSpeak: vi.fn(() => true),
  speak: vi.fn(() => Promise.resolve()),
  playUrl: vi.fn(() => Promise.resolve()),
}))

afterEach(() => vi.unstubAllGlobals())

beforeEach(() => {
  ;(window as unknown as { listenlyConfig: unknown }).listenlyConfig = {
    restUrl: '/wp-json/listenly/v1/',
    nonce: 'n',
    pageUrl: '/wp-admin/admin.php?page=listenly-voice',
    version: '0.1.0',
    settings: { provider: 'browser', voice: '', speed: 1, post_types: ['post'], position: 'above', highlight: 'sentence' },
    providers: [
      { id: 'browser', label: 'Browser voice', ready: true, clientSide: true, voices: [] },
      { id: 'google', label: 'Google Cloud', ready: false, clientSide: false, voices: [{ id: 'en-US-Neural2-F', label: 'en-US · Neural2-F', language: 'en-US' }] },
    ],
    googleKeySet: false,
    setupDone: true,
    postTypes: [{ id: 'post', label: 'Posts' }],
    pro: false,
  }
})

it('shows the Google key form after picking Google Cloud', async () => {
  render(<App />)
  await userEvent.click(within(screen.getByRole('navigation')).getByRole('button', { name: 'Voices' }))
  expect(screen.queryByLabelText('API key')).not.toBeInTheDocument()
  await userEvent.click(screen.getByRole('radio', { name: /Google Cloud/ }))
  expect(screen.getByRole('radio', { name: /Google Cloud/ })).toHaveAttribute('aria-checked', 'true')
  expect(screen.getByLabelText('API key')).toBeInTheDocument()
})

it('saves the key, tests it, and names the fix when the key is restricted', async () => {
  const paths: string[] = []
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string) => {
      const path = url.replace('/wp-json/listenly/v1/', '')
      paths.push(path)
      if (path === 'google-key') return new Response(JSON.stringify({ connected: true }))
      if (path === 'preview') {
        return new Response(
          JSON.stringify({
            code: 'listenly_google_key_restricted',
            message: "This key is restricted so it can't be used here.",
            data: { status: 502, fix: 'https://console.cloud.google.com/apis/credentials' },
          }),
          { status: 502 },
        )
      }
      return new Response('{}')
    }),
  )
  render(<App />)
  await userEvent.click(within(screen.getByRole('navigation')).getByRole('button', { name: 'Voices' }))
  await userEvent.click(screen.getByRole('radio', { name: /Google Cloud/ }))
  await userEvent.type(screen.getByLabelText('API key'), 'AIza-test')
  await userEvent.click(screen.getByRole('button', { name: 'Save and test' }))

  const alert = await screen.findByRole('alert')
  expect(paths.slice(0, 2)).toEqual(['google-key', 'preview'])
  expect(alert).toHaveTextContent("This key is restricted so it can't be used here.")
  expect(within(alert).getByRole('link', { name: /Open the fix/ })).toHaveAttribute('href', 'https://console.cloud.google.com/apis/credentials')
  expect(screen.getByRole('button', { name: 'Test voice' })).toBeInTheDocument()
})
