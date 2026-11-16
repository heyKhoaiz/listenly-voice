import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, expect, it } from 'vitest'
import { App } from '../../frontend/admin/App'

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
  expect(screen.queryByLabelText('API key')).not.toBeInTheDocument()
  await userEvent.click(screen.getByRole('radio', { name: /Google Cloud/ }))
  expect(screen.getByRole('radio', { name: /Google Cloud/ })).toHaveAttribute('aria-checked', 'true')
  expect(screen.getByLabelText('API key')).toBeInTheDocument()
})
