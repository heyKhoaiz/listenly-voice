import { useState } from 'react'
import { __ } from '../lib/i18n'
import { config, type Settings } from '../lib/config'
import { api } from '../lib/api'
import { providerPanel } from '../lib/extend'
import { playUrl } from '../lib/sound'
import { GoogleKeyGuide, KeyProblemNotice, keyProblem, type KeyProblem } from '../components/GoogleKeyGuide'

type Props = { settings: Settings; onChange: (s: Settings) => void }

// Voice provider cards, the Google key, voice and speed.
export function Voices({ settings, onChange }: Props) {
  const cfg = config()
  const [key, setKey] = useState('')
  const [keySet, setKeySet] = useState(cfg.googleKeySet)
  const [keyError, setKeyError] = useState('')
  const [testing, setTesting] = useState(false)
  const [tested, setTested] = useState<'' | 'ok'>('')
  const [problem, setProblem] = useState<KeyProblem | null>(null)
  const provider = cfg.providers.find((p) => p.id === settings.provider) ?? cfg.providers[0]

  // Plays a short sample with the saved key; a failure names the fix.
  const testVoice = async () => {
    setProblem(null)
    setTested('')
    setTesting(true)
    try {
      const res = await api<{ audio: string }>('preview', { method: 'POST', body: { provider: 'google', voice: settings.voice || provider?.voices[0]?.id || '' } })
      setTested('ok')
      await playUrl(res.audio).catch(() => {})
    } catch (e) {
      setProblem(keyProblem(e))
    } finally {
      setTesting(false)
    }
  }

  const saveKey = async () => {
    setKeyError('')
    try {
      const res = await api<{ connected: boolean }>('google-key', { method: 'POST', body: { key: key.trim() } })
      setKeySet(res.connected)
      setKey('')
      if (res.connected) await testVoice()
    } catch (e) {
      setKeyError((e as Error).message)
    }
  }

  return (
    <div className="lv-grid">
      <section className="lv-card">
        <h2>{__('Voice provider', 'listenly-voice')}</h2>
        <p className="lv-muted">{__('Who reads your posts. You pay the provider directly; Listenly adds nothing on top.', 'listenly-voice')}</p>
        <div role="radiogroup" aria-label={__('Voice provider', 'listenly-voice')} className="lv-options">
          {cfg.providers.map((p) => (
            <button
              key={p.id}
              type="button"
              role="radio"
              aria-checked={settings.provider === p.id}
              className="lv-option"
              onClick={() => onChange({ ...settings, provider: p.id, voice: p.voices[0]?.id ?? '' })}
            >
              <span className="lv-radio" aria-hidden="true" />
              <span className="lv-option-name">{p.label}</span>
              <span className="lv-badge">{p.clientSide ? __('No setup', 'listenly-voice') : __('Your key', 'listenly-voice')}</span>
            </button>
          ))}
        </div>
      </section>

      {provider?.id === 'google' && (
        <section className="lv-card">
          <h2>{__('Google Cloud connection', 'listenly-voice')}</h2>
          <div className="lv-panel">
            <label htmlFor="lv-key">{__('API key', 'listenly-voice')}</label>
            <div className="lv-row">
              <input id="lv-key" type="password" autoComplete="off" value={key} onChange={(e) => setKey(e.target.value)} placeholder={keySet ? '••••••••••••' : 'AIza…'} />
              <button type="button" className="lv-btn" onClick={saveKey} disabled={!key.trim() || testing}>
                {__('Save and test', 'listenly-voice')}
              </button>
            </div>
            {keySet && (
              <div className="lv-row">
                <button type="button" className="lv-btn" onClick={testVoice} disabled={testing}>
                  {testing ? __('Testing…', 'listenly-voice') : __('Test voice', 'listenly-voice')}
                </button>
              </div>
            )}
            <p className={keySet ? 'lv-ok' : 'lv-muted'} aria-live="polite">
              {tested === 'ok'
                ? __('It works. You should hear a sample; the key stays on your server.', 'listenly-voice')
                : keySet
                  ? __('Key saved. It stays on your server.', 'listenly-voice')
                  : __('Not connected yet.', 'listenly-voice')}
            </p>
            {keyError && <p className="lv-error" role="alert">{keyError}</p>}
            {problem && <KeyProblemNotice problem={problem} />}
            <GoogleKeyGuide open={!keySet} />
          </div>
          <div className="lv-two">
            <label>
              {__('Voice', 'listenly-voice')}
              <select value={settings.voice} onChange={(e) => onChange({ ...settings, voice: e.target.value })}>
                {provider.voices.map((v) => (
                  <option key={v.id} value={v.id}>{v.label}</option>
                ))}
              </select>
            </label>
            <label>
              {__('Default speed', 'listenly-voice')} · {settings.speed}×
              <input type="range" min={0.5} max={2} step={0.05} value={settings.speed} onChange={(e) => onChange({ ...settings, speed: Number(e.target.value) })} />
            </label>
          </div>
        </section>
      )}
      {provider && provider.id !== 'google' && !provider.clientSide && providerPanel(provider, { settings, onChange })}
    </div>
  )
}
