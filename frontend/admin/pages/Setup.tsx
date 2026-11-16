import { useEffect, useRef, useState } from 'react'
import { __ } from '../lib/i18n'
import { config, type Settings } from '../lib/config'
import { api } from '../lib/api'
import { canSpeak, playUrl, SAMPLE_TEXT, speak } from '../lib/sound'
import { POSITIONS, PositionPicker, PostTypePicker } from '../components/Placement'
import { providerPanel } from '../lib/extend'
import { GoogleKeyGuide, KeyProblemNotice, keyProblem, type KeyProblem } from '../components/GoogleKeyGuide'

type Props = {
  settings: Settings
  onChange: (s: Settings) => void
  /** Leave the wizard for the settings app. */
  onClose: () => void
}

const STEP_NAMES = () => [__('Voice', 'listenly-voice'), __('Placement', 'listenly-voice'), __('Done', 'listenly-voice')]

function voiceCopy(id: string, label: string): { name: string; desc: string } {
  if (id === 'google') {
    return {
      name: __('Natural voice (recommended)', 'listenly-voice'),
      desc: __('Google Cloud voices. Free for most blogs with your own key; takes about 2 minutes to set up.', 'listenly-voice'),
    }
  }
  if (id === 'browser') {
    return {
      name: label,
      desc: __('Works now with no setup. Each visitor hears their own device voice, so it sounds different on phones.', 'listenly-voice'),
    }
  }
  return { name: label, desc: __('Uses your own API key.', 'listenly-voice') }
}

// First-run wizard: voice → placement → done. Design: docs/ui.md screen 3.
export function Setup({ settings, onChange, onClose }: Props) {
  const cfg = config()
  const [step, setStep] = useState(1)
  const [busy, setBusy] = useState(false)
  // Plain text, or a Google key problem with a link to its fix.
  const [error, setError] = useState<string | KeyProblem>('')
  const [key, setKey] = useState('')
  const [keyWorks, setKeyWorks] = useState(cfg.googleKeySet)
  const [keyBusy, setKeyBusy] = useState(false)
  const [viewUrl, setViewUrl] = useState('')
  const heading = useRef<HTMLHeadingElement>(null)

  useEffect(() => {
    heading.current?.focus()
  }, [step])

  const provider = cfg.providers.find((p) => p.id === settings.provider) ?? cfg.providers[0]
  const needsKey = provider?.id === 'google'
  const positions = POSITIONS()

  const pickProvider = (id: string) => {
    const p = cfg.providers.find((x) => x.id === id)
    setError('')
    onChange({ ...settings, provider: id, voice: p?.voices.some((v) => v.id === settings.voice) ? settings.voice : (p?.voices[0]?.id ?? '') })
  }

  const playSample = async (id: string) => {
    setError('')
    const p = cfg.providers.find((x) => x.id === id)
    if (!p) return
    if (p.clientSide) {
      try {
        await speak(SAMPLE_TEXT)
      } catch (e) {
        setError((e as Error).message)
      }
      return
    }
    try {
      const voice = id === settings.provider ? settings.voice : (p.voices[0]?.id ?? '')
      const res = await api<{ audio: string }>('preview', { method: 'POST', body: { provider: id, voice } })
      await playUrl(res.audio)
    } catch (e) {
      setError(keyProblem(e))
    }
  }

  const connect = async () => {
    setError('')
    setKeyBusy(true)
    try {
      await api<{ connected: boolean }>('google-key', { method: 'POST', body: { key: key.trim() } })
      setKey('')
      const res = await api<{ audio: string }>('preview', { method: 'POST', body: { provider: 'google', voice: settings.voice || provider.voices[0]?.id || '' } })
      setKeyWorks(true)
      await playUrl(res.audio).catch(() => {})
    } catch (e) {
      setKeyWorks(false)
      setError(keyProblem(e))
    } finally {
      setKeyBusy(false)
    }
  }

  const next = () => {
    setError('')
    if (step === 1) {
      if (needsKey && !keyWorks) {
        setError(__('Connect a working Google key first, or choose the browser voice.', 'listenly-voice'))
        return
      }
      setStep(2)
    }
  }

  const finish = async () => {
    setError('')
    if (settings.post_types.length === 0) {
      setError(__('Choose at least one place to show the player.', 'listenly-voice'))
      return
    }
    setBusy(true)
    try {
      onChange(await api<Settings>('settings', { method: 'POST', body: settings }))
      const res = await api<{ setupDone: boolean; viewUrl: string }>('setup', { method: 'POST', body: { done: true } })
      setViewUrl(res.viewUrl)
      setStep(3)
    } catch (e) {
      setError((e as Error).message)
    } finally {
      setBusy(false)
    }
  }

  const skip = async () => {
    setError('')
    try {
      await api('setup', { method: 'POST', body: { done: true } })
      onClose()
    } catch (e) {
      setError((e as Error).message)
    }
  }

  const where = positions.find((p) => p.id === settings.position)?.done ?? positions[0].done
  const typeLabels = cfg.postTypes.filter((t) => settings.post_types.includes(t.id)).map((t) => t.label)
  const doneText =
    settings.post_types.length === 1 && settings.post_types[0] === 'post'
      ? `${__('The player is on', 'listenly-voice')} ${where} ${__('post.', 'listenly-voice')}`
      : `${__('The player is on', 'listenly-voice')} ${where} ${__('item in:', 'listenly-voice')} ${typeLabels.join(', ')}.`

  return (
    <div className="lv-wizard">
      <div className="lv-wizard-inner">
        <div className="lv-wizard-head">
          <span className="lv-brand">
            <span className="lv-logo" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M4 10v4M8 7v10M12 4v16M16 8v8M20 11v2" /></svg>
            </span>
            Listenly Voice
          </span>
          {step < 3 && (
            <button type="button" className="lv-link" onClick={skip}>
              {__('Skip setup', 'listenly-voice')}
            </button>
          )}
        </div>

        <ol className="lv-steps" aria-label={__('Setup steps', 'listenly-voice')}>
          {STEP_NAMES().map((name, i) => (
            <li key={name} aria-current={i + 1 === step ? 'step' : undefined} className={i + 1 <= step ? 'is-reached' : undefined}>
              <span className="lv-step-bar" aria-hidden="true" />
              {name}
            </li>
          ))}
        </ol>

        <section className="lv-card lv-wizard-card">
          {error && typeof error === 'string' && (
            <p className="lv-error lv-wizard-error" role="alert">
              {error}
            </p>
          )}
          {error && typeof error !== 'string' && <KeyProblemNotice problem={error} className="lv-error lv-wizard-error" />}

          {step === 1 && (
            <>
              <div>
                <p className="lv-step-meta">{__('Step 1 of 3 · about 1 minute', 'listenly-voice')}</p>
                <h1 ref={heading} tabIndex={-1}>{__('How should your posts sound?', 'listenly-voice')}</h1>
                <p className="lv-muted">{__('You can change this any time. Play each sample before you choose.', 'listenly-voice')}</p>
              </div>
              <div role="radiogroup" aria-label={__('Voice', 'listenly-voice')} className="lv-voice-list">
                {cfg.providers.map((p) => {
                  const copy = voiceCopy(p.id, p.label)
                  const checked = settings.provider === p.id
                  const sampleOff = p.clientSide ? !canSpeak() : !(p.id === 'google' && keyWorks)
                  return (
                    <div key={p.id} className={`lv-voice${checked ? ' is-checked' : ''}`}>
                      <button type="button" role="radio" aria-checked={checked} className="lv-voice-pick" onClick={() => pickProvider(p.id)}>
                        <span className="lv-radio" aria-hidden="true" />
                        <span className="lv-voice-text">
                          <span className="lv-option-name">{copy.name}</span>
                          <span className="lv-voice-desc">{copy.desc}</span>
                        </span>
                      </button>
                      <button
                        type="button"
                        className="lv-btn lv-sample"
                        aria-label={`${__('Play sample of', 'listenly-voice')} ${copy.name}`}
                        disabled={sampleOff}
                        onClick={() => playSample(p.id)}
                      >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" /></svg>
                        {__('Sample', 'listenly-voice')}
                      </button>
                    </div>
                  )
                })}
              </div>
              {!canSpeak() && <p className="lv-muted">{__('This browser has no built-in voice to play a sample.', 'listenly-voice')}</p>}

              {needsKey && (
                <div className="lv-panel">
                  {keyWorks ? (
                    <p className="lv-ok">{__('Connected. Your key works and stays on your server.', 'listenly-voice')}</p>
                  ) : (
                    <>
                      <label htmlFor="lv-wizard-key">{__('Paste your Google Cloud API key', 'listenly-voice')}</label>
                      <div className="lv-row">
                        <input id="lv-wizard-key" type="password" autoComplete="off" placeholder="AIza…" value={key} onChange={(e) => setKey(e.target.value)} />
                        <button type="button" className="lv-btn" onClick={connect} disabled={!key.trim() || keyBusy}>
                          {keyBusy ? __('Connecting…', 'listenly-voice') : __('Connect and play a sample', 'listenly-voice')}
                        </button>
                      </div>
                      <GoogleKeyGuide />
                    </>
                  )}
                  {provider.voices.length > 0 && (
                    <label className="lv-field">
                      {__('Google voice', 'listenly-voice')}
                      <select value={settings.voice} onChange={(e) => onChange({ ...settings, voice: e.target.value })}>
                        {provider.voices.map((v) => (
                          <option key={v.id} value={v.id}>{v.label}</option>
                        ))}
                      </select>
                    </label>
                  )}
                </div>
              )}
              {!needsKey && provider && !provider.clientSide && providerPanel(provider, { settings, onChange })}
            </>
          )}

          {step === 2 && (
            <>
              <div>
                <p className="lv-step-meta">{__('Step 2 of 3', 'listenly-voice')}</p>
                <h1 ref={heading} tabIndex={-1}>{__('Where should the player appear?', 'listenly-voice')}</h1>
              </div>
              <PostTypePicker settings={settings} onChange={(s) => { setError(''); onChange(s) }} />
              <PositionPicker settings={settings} onChange={onChange} />
            </>
          )}

          {step === 3 && (
            <>
              <span className="lv-done-icon" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12l5 5 9-10" /></svg>
              </span>
              <div>
                <h1 ref={heading} tabIndex={-1}>{__('Your posts can be heard now', 'listenly-voice')}</h1>
                <p className="lv-muted">
                  {`${doneText} ${__('Open one to hear it.', 'listenly-voice')}`}
                </p>
              </div>
              <div className="lv-row">
                <a className="lv-btn lv-btn-dark lv-btn-link" href={viewUrl}>
                  {__('View a post', 'listenly-voice')}
                </a>
                <button type="button" className="lv-btn" onClick={onClose}>
                  {__('Fine-tune settings', 'listenly-voice')}
                </button>
              </div>
            </>
          )}

          {step < 3 && (
            <div className={`lv-wizard-nav${step === 1 ? ' is-first' : ''}`}>
              {step > 1 && (
                <button type="button" className="lv-btn" onClick={() => { setError(''); setStep(step - 1) }}>
                  {__('Back', 'listenly-voice')}
                </button>
              )}
              {step === 1 ? (
                <button type="button" className="lv-btn lv-btn-dark" onClick={next}>
                  {__('Continue', 'listenly-voice')}
                </button>
              ) : (
                <button type="button" className="lv-btn lv-btn-dark" onClick={finish} disabled={busy}>
                  {busy ? __('Saving…', 'listenly-voice') : __('Turn on the player', 'listenly-voice')}
                </button>
              )}
            </div>
          )}
        </section>
      </div>
    </div>
  )
}
