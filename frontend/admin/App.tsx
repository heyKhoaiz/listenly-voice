import { useState } from 'react'
import { __ } from './lib/i18n'
import { config, type Settings } from './lib/config'
import { api } from './lib/api'
import { Voices } from './pages/Voices'
import { Setup } from './pages/Setup'
import { Player } from './pages/Player'
import { Home } from './pages/Home'
import { Icon } from './components/Icon'
import { edition, extraPages } from './lib/extend'

type Page = string

// The app shell from the UI design: light sidebar, top bar with pill buttons, cards.
export function App() {
  const cfg = config()
  const brand = cfg.brand?.trim() || 'PostAloud'
  const [page, setPage] = useState<Page>('home')
  const [settings, setSettings] = useState<Settings>(cfg.settings)
  const [saving, setSaving] = useState(false)
  const [notice, setNotice] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null)
  const [wizard, setWizard] = useState(!cfg.setupDone)

  const save = async () => {
    setSaving(true)
    setNotice(null)
    try {
      setSettings(await api<Settings>('settings', { method: 'POST', body: settings }))
      setNotice({ kind: 'ok', text: __('Settings saved.', 'postaloud') })
    } catch (e) {
      setNotice({ kind: 'error', text: (e as Error).message })
    } finally {
      setSaving(false)
    }
  }

  const home = { id: 'home', label: __('Home', 'postaloud'), icon: 'home' }
  const nav: { id: Page; label: string; description?: string; icon?: string }[] = [
    { id: 'voices', label: __('Voices', 'postaloud'), description: __('Provider, voice and speed', 'postaloud'), icon: 'voices' },
    { id: 'player', label: __('Player', 'postaloud'), description: __('Where it shows and how it looks', 'postaloud'), icon: 'player' },
  ]
  const extra = extraPages(['home', ...nav.map((n) => n.id)])
  nav.push(...extra.map(({ id, label, description, icon }) => ({ id, label, description, icon })))
  const extraPage = extra.find((p) => p.id === page)

  if (wizard) {
    return <Setup settings={settings} onChange={setSettings} onClose={() => { setNotice(null); setWizard(false) }} />
  }

  return (
    <div className="lv-frame">
      <nav className="lv-side" aria-label={brand}>
        <div className="lv-brand">
          <span className="lv-logo" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M4 10v4M8 7v10M12 4v16M16 8v8M20 11v2" /></svg>
          </span>
          {brand}
        </div>
        <NavItem item={home} current={page} onOpen={setPage} />
        <p className="lv-nav-label">{__('Settings', 'postaloud')}</p>
        {nav.map((item) => (
          <NavItem key={item.id} item={item} current={page} onOpen={setPage} />
        ))}
        {cfg.helpLinks !== false && <div className="lv-help">
          <span className="lv-help-title"><Icon name="help" size={16} />{__('Need a hand?', 'postaloud')}</span>
          <span className="lv-help-text">{__('Answers to common questions, or ask on the forum.', 'postaloud')}</span>
          <span className="lv-help-links">
            <a href="https://wordpress.org/plugins/postaloud/#faq" target="_blank" rel="noreferrer">{__('FAQ', 'postaloud')}</a>
            <a href="https://wordpress.org/support/plugin/postaloud/" target="_blank" rel="noreferrer">{__('Support forum', 'postaloud')}</a>
          </span>
        </div>}
      </nav>
      <div className="lv-main">
        <header className="lv-top">
          <span className="lv-title">{[home, ...nav].find((n) => n.id === page)?.label}</span>
          <div className="lv-actions">
            <span className="lv-chip">{edition(`${__('Free', 'postaloud')} · v${cfg.version}`)}</span>
            <button type="button" className="lv-btn" onClick={() => setWizard(true)}>
              {__('Run setup', 'postaloud')}
            </button>
            {page !== 'home' && !extraPage?.hideSave && (
              <button type="button" className="lv-btn lv-btn-dark" onClick={save} disabled={saving}>
                {saving ? __('Saving…', 'postaloud') : __('Save changes', 'postaloud')}
              </button>
            )}
          </div>
        </header>
        <main className="lv-body">
          {notice && (
            <p className={`lv-notice lv-notice-${notice.kind}`} role={notice.kind === 'error' ? 'alert' : 'status'}>
              {notice.text}
            </p>
          )}
          {page === 'home' && <Home settings={settings} tiles={nav} onOpen={setPage} />}
          {page === 'voices' && <Voices settings={settings} onChange={setSettings} />}
          {page === 'player' && <Player settings={settings} onChange={setSettings} />}
          {extraPage?.render({ settings, onChange: setSettings })}
        </main>
      </div>
    </div>
  )
}

type NavEntry = { id: string; label: string; icon?: string }
function NavItem({ item, current, onOpen }: { item: NavEntry; current: string; onOpen: (id: string) => void }) {
  return (
    <button type="button" className="lv-nav" aria-current={current === item.id ? 'page' : undefined} onClick={() => onOpen(item.id)}>
      <Icon name={item.icon ?? 'page'} />
      {item.label}
    </button>
  )
}
