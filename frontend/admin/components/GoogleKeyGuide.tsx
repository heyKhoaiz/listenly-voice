import { __ } from '../lib/i18n'
import { ApiError } from '../lib/api'

const CONSOLE = 'https://console.cloud.google.com/'

/** A Google key problem: what to say and where the fix is made. */
export type KeyProblem = { message: string; fix: string }

/**
 * Turns a failed key test into words the admin can act on. Errors the server recognised
 * (listenly_google_*) already say what to do; anything else is shown as "Google said: …".
 */
export function keyProblem(e: unknown): KeyProblem {
  const message = e instanceof Error ? e.message : String(e)
  if (e instanceof ApiError && e.code.startsWith('listenly_google_')) {
    return { message, fix: typeof e.data.fix === 'string' ? e.data.fix : '' }
  }
  return { message: `${__('Google said:', 'listenly-voice')} ${message}`, fix: '' }
}

/** The error box for a key test, with a link to the Google Cloud page where it's fixed. */
export function KeyProblemNotice({ problem, className = 'lv-error' }: { problem: KeyProblem; className?: string }) {
  return (
    <div className={`${className} lv-key-problem`} role="alert">
      <p>{problem.message}</p>
      {problem.fix && (
        <a href={problem.fix} target="_blank" rel="noreferrer" className="lv-key-fix">
          {__('Open the fix in Google Cloud (new tab)', 'listenly-voice')}
        </a>
      )}
    </div>
  )
}

/** Step-by-step: from no Google account to a working key. Collapsed until opened. */
export function GoogleKeyGuide({ open = false }: { open?: boolean }) {
  return (
    <details className="lv-guide" open={open}>
      <summary>{__('How to get a Google Cloud key (about 5 minutes)', 'listenly-voice')}</summary>
      <ol>
        <li>
          <a href={`${CONSOLE}projectcreate`} target="_blank" rel="noreferrer">{__('Create a Google Cloud project', 'listenly-voice')}</a>{' '}
          {__('or pick one you already have. Google asks for billing details, but there is a free monthly allowance and most blogs stay inside it.', 'listenly-voice')}
        </li>
        <li>
          <a href={`${CONSOLE}apis/library/texttospeech.googleapis.com`} target="_blank" rel="noreferrer">{__('Open the Cloud Text-to-Speech API', 'listenly-voice')}</a>{' '}
          {__('and press Enable.', 'listenly-voice')}
        </li>
        <li>
          {__('Go to', 'listenly-voice')}{' '}
          <a href={`${CONSOLE}apis/credentials`} target="_blank" rel="noreferrer">{__('Credentials', 'listenly-voice')}</a>
          {', '}
          {__('choose "Create credentials", then "API key".', 'listenly-voice')}
        </li>
        <li>
          {__('Edit the key: under "API restrictions" allow only the Cloud Text-to-Speech API. Leave "Application restrictions" on None: your server makes the requests, not visitors\' browsers.', 'listenly-voice')}
        </li>
        <li>{__('Copy the key (it starts with "AIza"), paste it here and test it. It stays on your server.', 'listenly-voice')}</li>
      </ol>
    </details>
  )
}
