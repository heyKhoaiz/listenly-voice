import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { App } from '../../frontend/admin/App'
import * as sound from '../../frontend/admin/lib/sound'

vi.mock('../../frontend/admin/lib/sound', () => ({
  SAMPLE_TEXT: 'Hello sample.',
  canSpeak: vi.fn(() => true),
  speak: vi.fn(() => Promise.resolve()),
  playUrl: vi.fn(() => Promise.resolve()),
}))

type Route = { status?: number; body: unknown }
let routes: Record<string, Route | ((body: unknown) => Route)>
let calls: { path: string; method: string; body: unknown }[]

function config(overrides: Record<string, unknown> = {}) {
  ;(window as unknown as { listenlyConfig: unknown }).listenlyConfig = {
    restUrl: '/wp-json/listenly/v1/',
    nonce: 'n',
    pageUrl: '/wp-admin/admin.php?page=listenly-voice',
    version: '0.1.0',
    settings: { provider: 'browser', voice: '', speed: 1, post_types: ['post'], position: 'above', highlight: 'sentence' },
    providers: [
      { id: 'browser', label: 'Browser voice', ready: true, clientSide: true, voices: [] },
      {
        id: 'google',
        label: 'Google Cloud',
        ready: false,
        clientSide: false,
        voices: [
          { id: 'en-US-Neural2-F', label: 'en-US · Neural2-F', language: 'en-US' },
          { id: 'en-US-Neural2-D', label: 'en-US · Neural2-D', language: 'en-US' },
        ],
      },
    ],
    googleKeySet: false,
    setupDone: false,
    postTypes: [
      { id: 'post', label: 'Posts' },
      { id: 'page', label: 'Pages' },
    ],
    pro: false,
    ...overrides,
  }
}

beforeEach(() => {
  config()
  calls = []
  routes = {
    'settings POST': (body) => ({ body }),
    'setup POST': { body: { setupDone: true, viewUrl: 'https://example.test/hello-world/' } },
    'google-key POST': { body: { connected: true } },
    'preview POST': { body: { audio: 'data:audio/mpeg;base64,AAA' } },
  }
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string, init: RequestInit = {}) => {
      const path = url.replace('/wp-json/listenly/v1/', '')
      const method = init.method ?? 'GET'
      const body = init.body ? JSON.parse(String(init.body)) : undefined
      calls.push({ path, method, body })
      const route = routes[`${path} ${method}`]
      const r = typeof route === 'function' ? route(body) : route
      if (!r) return new Response('{}', { status: 404 })
      return new Response(JSON.stringify(r.body), { status: r.status ?? 200 })
    }),
  )
  vi.mocked(sound.canSpeak).mockReturnValue(true)
})

const user = () => userEvent.setup()
const continueButton = () => screen.getByRole('button', { name: 'Continue' })

describe('first run', () => {
  it('opens the wizard when setup is not done', () => {
    render(<App />)
    expect(screen.getByRole('heading', { level: 1, name: 'How should your posts sound?' })).toBeInTheDocument()
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument()
  })

  it('opens the settings app when setup is done', () => {
    config({ setupDone: true })
    render(<App />)
    expect(screen.queryByRole('heading', { name: 'How should your posts sound?' })).not.toBeInTheDocument()
    expect(screen.getByRole('navigation')).toBeInTheDocument()
  })

  it('marks the current step in the step list', () => {
    render(<App />)
    const steps = within(screen.getByRole('list', { name: 'Setup steps' })).getAllByRole('listitem')
    expect(steps.map((s) => s.textContent)).toEqual(['Voice', 'Placement', 'Done'])
    expect(steps[0]).toHaveAttribute('aria-current', 'step')
    expect(steps[1]).not.toHaveAttribute('aria-current')
  })

  it('moves focus to the step heading', () => {
    render(<App />)
    expect(screen.getByRole('heading', { level: 1 })).toHaveFocus()
  })
})

describe('step 1: voice', () => {
  it('starts on the saved provider and recommends Google', () => {
    render(<App />)
    expect(screen.getByRole('radio', { name: /Browser voice/ })).toHaveAttribute('aria-checked', 'true')
    expect(screen.getByRole('radio', { name: /Natural voice \(recommended\)/ })).toHaveAttribute('aria-checked', 'false')
  })

  it('goes on with the browser voice and no key', async () => {
    render(<App />)
    await user().click(continueButton())
    expect(screen.getByRole('heading', { level: 1, name: 'Where should the player appear?' })).toBeInTheDocument()
  })

  it('plays the browser sample with the browser voice', async () => {
    render(<App />)
    await user().click(screen.getByRole('button', { name: 'Play sample of Browser voice' }))
    expect(sound.speak).toHaveBeenCalledWith('Hello sample.')
  })

  it('explains when the browser has no voice', () => {
    vi.mocked(sound.canSpeak).mockReturnValue(false)
    render(<App />)
    expect(screen.getByRole('button', { name: 'Play sample of Browser voice' })).toBeDisabled()
    expect(screen.getByText('This browser has no built-in voice to play a sample.')).toBeInTheDocument()
  })

  it('asks for a Google key after choosing the natural voice', async () => {
    render(<App />)
    await user().click(screen.getByRole('radio', { name: /Natural voice/ }))
    expect(screen.getByLabelText('Paste your Google Cloud API key')).toBeInTheDocument()
  })

  it('does not go on with Google until a key works', async () => {
    render(<App />)
    const u = user()
    await u.click(screen.getByRole('radio', { name: /Natural voice/ }))
    await u.click(continueButton())
    expect(screen.getByRole('alert')).toHaveTextContent('Connect a working Google key first, or choose the browser voice.')
    expect(screen.getByRole('heading', { level: 1, name: 'How should your posts sound?' })).toBeInTheDocument()
  })

  it('saves the key, proves it with a sample, then lets you go on', async () => {
    render(<App />)
    const u = user()
    await u.click(screen.getByRole('radio', { name: /Natural voice/ }))
    await u.type(screen.getByLabelText('Paste your Google Cloud API key'), 'AIza-test')
    await u.click(screen.getByRole('button', { name: 'Connect and play a sample' }))

    await screen.findByText('Connected. Your key works and stays on your server.')
    expect(calls.find((c) => c.path === 'google-key')?.body).toEqual({ key: 'AIza-test' })
    expect(calls.find((c) => c.path === 'preview')?.body).toEqual({ provider: 'google', voice: 'en-US-Neural2-F' })
    expect(sound.playUrl).toHaveBeenCalledWith('data:audio/mpeg;base64,AAA')
    expect(screen.queryByLabelText('Paste your Google Cloud API key')).not.toBeInTheDocument()

    await u.click(continueButton())
    expect(screen.getByRole('heading', { level: 1, name: 'Where should the player appear?' })).toBeInTheDocument()
  })

  it('shows what Google said when the key does not work', async () => {
    routes['preview POST'] = { status: 502, body: { message: 'API key not valid. Please pass a valid API key.' } }
    render(<App />)
    const u = user()
    await u.click(screen.getByRole('radio', { name: /Natural voice/ }))
    await u.type(screen.getByLabelText('Paste your Google Cloud API key'), 'bad')
    await u.click(screen.getByRole('button', { name: 'Connect and play a sample' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Google said: API key not valid. Please pass a valid API key.')
    await u.click(continueButton())
    expect(screen.getByRole('heading', { level: 1, name: 'How should your posts sound?' })).toBeInTheDocument()
  })

  it('needs a key before connecting', async () => {
    render(<App />)
    const u = user()
    await u.click(screen.getByRole('radio', { name: /Natural voice/ }))
    expect(screen.getByRole('button', { name: 'Connect and play a sample' })).toBeDisabled()
  })

  it('accepts a key saved earlier without asking again', async () => {
    config({ googleKeySet: true, settings: { provider: 'google', voice: 'en-US-Neural2-D', speed: 1, post_types: ['post'], position: 'above', highlight: 'sentence' } })
    render(<App />)
    expect(screen.getByText('Connected. Your key works and stays on your server.')).toBeInTheDocument()
    await user().click(continueButton())
    expect(screen.getByRole('heading', { level: 1, name: 'Where should the player appear?' })).toBeInTheDocument()
  })

  it('plays a Google sample in the chosen voice', async () => {
    config({ googleKeySet: true, settings: { provider: 'google', voice: 'en-US-Neural2-F', speed: 1, post_types: ['post'], position: 'above', highlight: 'sentence' } })
    render(<App />)
    const u = user()
    await u.selectOptions(screen.getByLabelText('Google voice'), 'en-US-Neural2-D')
    await u.click(screen.getByRole('button', { name: /Play sample of Natural voice/ }))
    await waitFor(() => expect(sound.playUrl).toHaveBeenCalled())
    expect(calls.find((c) => c.path === 'preview')?.body).toEqual({ provider: 'google', voice: 'en-US-Neural2-D' })
  })
})

describe('step 2: placement', () => {
  async function toStep2() {
    render(<App />)
    const u = user()
    await u.click(continueButton())
    return u
  }

  it('lists the site post types with the saved ones ticked', async () => {
    await toStep2()
    expect(screen.getByRole('checkbox', { name: 'Posts' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Pages' })).not.toBeChecked()
  })

  it('offers three placements with the saved one selected', async () => {
    await toStep2()
    const radios = within(screen.getByRole('radiogroup', { name: 'Player position' })).getAllByRole('radio')
    expect(radios.map((r) => r.textContent)).toEqual(['Top of the post', 'End of the post', 'Floating button'])
    expect(radios[0]).toHaveAttribute('aria-checked', 'true')
  })

  it('needs at least one place to show the player', async () => {
    const u = await toStep2()
    await u.click(screen.getByRole('checkbox', { name: 'Posts' }))
    await u.click(screen.getByRole('button', { name: 'Turn on the player' }))
    expect(screen.getByRole('alert')).toHaveTextContent('Choose at least one place to show the player.')
    expect(calls.some((c) => c.path === 'settings')).toBe(false)
  })

  it('saves the choices, marks setup done and shows the last step', async () => {
    const u = await toStep2()
    await u.click(screen.getByRole('checkbox', { name: 'Pages' }))
    await u.click(screen.getByRole('radio', { name: 'Floating button' }))
    await u.click(screen.getByRole('button', { name: 'Turn on the player' }))

    await screen.findByRole('heading', { level: 1, name: 'Your posts can be heard now' })
    const saved = calls.find((c) => c.path === 'settings' && c.method === 'POST')?.body as Record<string, unknown>
    expect(saved).toMatchObject({ provider: 'browser', post_types: ['post', 'page'], position: 'floating' })
    expect(calls.find((c) => c.path === 'setup')?.body).toEqual({ done: true })
    const saveIndex = calls.findIndex((c) => c.path === 'settings')
    const setupIndex = calls.findIndex((c) => c.path === 'setup')
    expect(saveIndex).toBeLessThan(setupIndex)
  })

  it('keeps step 1 choices when going back', async () => {
    config({ googleKeySet: true })
    render(<App />)
    const u = user()
    await u.click(screen.getByRole('radio', { name: /Natural voice/ }))
    await u.click(continueButton())
    await u.click(screen.getByRole('button', { name: 'Back' }))
    expect(screen.getByRole('radio', { name: /Natural voice/ })).toHaveAttribute('aria-checked', 'true')
  })

  it('has no Back button on the first step', () => {
    render(<App />)
    expect(screen.queryByRole('button', { name: 'Back' })).not.toBeInTheDocument()
  })

  it('stays on the step and explains when saving fails', async () => {
    routes['settings POST'] = { status: 500, body: { message: 'Could not save.' } }
    const u = await toStep2()
    await u.click(screen.getByRole('button', { name: 'Turn on the player' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Could not save.')
    expect(screen.getByRole('heading', { level: 1, name: 'Where should the player appear?' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Turn on the player' })).toBeEnabled()
  })

  it('shows "Saving…" while it saves', async () => {
    let release: () => void = () => {}
    routes['settings POST'] = (body) => ({ body })
    const original = vi.mocked(fetch).getMockImplementation()!
    vi.mocked(fetch).mockImplementation(async (url, init) => {
      if (String(url).endsWith('settings')) await new Promise<void>((r) => (release = r))
      return original(url as string, init)
    })
    const u = await toStep2()
    await u.click(screen.getByRole('button', { name: 'Turn on the player' }))
    expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled()
    release()
    await screen.findByRole('heading', { level: 1, name: 'Your posts can be heard now' })
  })
})

describe('step 3: done', () => {
  async function finish() {
    render(<App />)
    const u = user()
    await u.click(continueButton())
    await u.click(screen.getByRole('button', { name: 'Turn on the player' }))
    await screen.findByRole('heading', { level: 1, name: 'Your posts can be heard now' })
    return u
  }

  it('links to the newest post so you can hear it', async () => {
    await finish()
    expect(screen.getByRole('link', { name: 'View a post' })).toHaveAttribute('href', 'https://example.test/hello-world/')
  })

  it('says where the player now shows', async () => {
    await finish()
    expect(screen.getByText('The player is on at the top of every post. Open one to hear it.')).toBeInTheDocument()
  })

  it('opens the settings app from "Fine-tune settings"', async () => {
    const u = await finish()
    await u.click(screen.getByRole('button', { name: 'Fine-tune settings' }))
    expect(screen.getByRole('navigation')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Your posts can be heard now' })).not.toBeInTheDocument()
  })

  it('hides Back and Continue on the last step', async () => {
    await finish()
    expect(screen.queryByRole('button', { name: 'Back' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Continue' })).not.toBeInTheDocument()
  })
})

describe('skip and run again', () => {
  it('skips setup, marks it done and opens the settings app', async () => {
    render(<App />)
    await user().click(screen.getByRole('button', { name: 'Skip setup' }))
    await screen.findByRole('navigation')
    expect(calls.find((c) => c.path === 'setup')?.body).toEqual({ done: true })
    expect(calls.some((c) => c.path === 'settings')).toBe(false)
  })

  it('explains when skipping fails', async () => {
    routes['setup POST'] = { status: 500, body: { message: 'Server error.' } }
    render(<App />)
    await user().click(screen.getByRole('button', { name: 'Skip setup' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Server error.')
  })

  it('opens the wizard again from the settings app', async () => {
    config({ setupDone: true })
    render(<App />)
    await user().click(screen.getByRole('button', { name: 'Run setup' }))
    expect(screen.getByRole('heading', { level: 1, name: 'How should your posts sound?' })).toBeInTheDocument()
  })

  it('carries choices made in the wizard into the settings app', async () => {
    render(<App />)
    const u = user()
    await u.click(continueButton())
    await u.click(screen.getByRole('radio', { name: 'End of the post' }))
    await u.click(screen.getByRole('button', { name: 'Turn on the player' }))
    await u.click(await screen.findByRole('button', { name: 'Fine-tune settings' }))
    await u.click(screen.getByRole('button', { name: 'Save changes' }))
    const saves = calls.filter((c) => c.path === 'settings')
    const lastSave = saves[saves.length - 1]?.body as Record<string, unknown>
    expect(lastSave.position).toBe('below')
  })
})
