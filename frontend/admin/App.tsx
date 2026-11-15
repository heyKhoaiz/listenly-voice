import { useState } from 'react'
import { __ } from './lib/i18n'
import { config, type Settings } from './lib/config'
import { api } from './lib/api'
import { Voices } from './pages/Voices'

type Page = 'voices' | 'player'

// The app shell from the UI design: light sidebar, top bar with pill buttons, cards.
export function App() {
  const cfg = config()
  const [page, setPage] = useState<Page>('voices')
  const [settings, setSettings] = useState<Settings>(cfg.settings)
  const [saving, setSaving] = useState(false)
  const [notice, setNotice] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null)

  const save = async () => {
    setSaving(true)
    setNotice(null)
    try {
      setSettings(await api<Settings>('settings', { method: 'POST', body: settings }))
      setNotice({ kind: 'ok', text: __('Settings saved.', 'listenly-voice') })
    } catch (e) {
      setNotice({ kind: 'error', text: (e as Error).message })
    } finally {
      setSaving(false)
    }
  }

  const nav: { id: Page; label: string }[] = [
    { id: 'voices', label: __('Voices', 'listenly-voice') },
    { id: 'player', label: __('Player', 'listenly-voice') },
  ]

  return (
    <div className="lv-frame">
      <nav className="lv-side" aria-label={__('Listenly Voice', 'listenly-voice')}>
        <div className="lv-brand">
          <span className="lv-logo" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M4 10v4M8 7v10M12 4v16M16 8v8M20 11v2" /></svg>
          </span>
          Listenly Voice
        </div>
        {nav.map((item) => (
          <button key={item.id} type="button" className="lv-nav" aria-current={page === item.id ? 'page' : undefined} onClick={() => setPage(item.id)}>
            {item.label}
          </button>
        ))}
      </nav>
      <div className="lv-main">
        <header className="lv-top">
          <span className="lv-title">{nav.find((n) => n.id === page)?.label}</span>
          <div className="lv-actions">
            <span className="lv-chip">{__('Free', 'listenly-voice')} · v{cfg.version}</span>
            <button type="button" className="lv-btn lv-btn-dark" onClick={save} disabled={saving}>
              {saving ? __('Saving…', 'listenly-voice') : __('Save changes', 'listenly-voice')}
            </button>
          </div>
        </header>
        <main className="lv-body">
          {notice && (
            <p className={`lv-notice lv-notice-${notice.kind}`} role={notice.kind === 'error' ? 'alert' : 'status'}>
              {notice.text}
            </p>
          )}
          {page === 'voices' && <Voices settings={settings} onChange={setSettings} />}
          {page === 'player' && (
            <section className="lv-card">
              <h2>{__('Player', 'listenly-voice')}</h2>
              <p className="lv-muted">{__('Player position and style settings come next.', 'listenly-voice')}</p>
            </section>
          )}
        </main>
      </div>
    </div>
  )
}
