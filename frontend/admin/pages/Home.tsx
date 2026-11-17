import { useState } from 'react'
import { __ } from '../lib/i18n'
import { config, type Settings } from '../lib/config'
import { api } from '../lib/api'
import { playUrl, SAMPLE_TEXT, speak } from '../lib/sound'
import { POSITIONS } from '../components/Placement'
import { Icon } from '../components/Icon'

export type Tile = { id: string; label: string; description?: string; icon?: string }
type Props = { settings: Settings; tiles: Tile[]; onOpen: (page: string) => void }

// The default screen: the voice in use, shortcuts to every page and the newest posts.
// Design: docs/ui.md screen 0.
export function Home({ settings, tiles, onOpen }: Props) {
  const cfg = config()
  const [playing, setPlaying] = useState(false)
  const [error, setError] = useState('')
  const provider = cfg.providers.find((p) => p.id === settings.provider) ?? cfg.providers[0]
  const ready = Boolean(provider && (provider.clientSide || provider.ready || (provider.id === 'google' && cfg.googleKeySet)))
  const voice = provider?.clientSide ? __("Each visitor's device voice", 'postaloud') : (provider?.voices.find((v) => v.id === settings.voice)?.label ?? __('Default voice', 'postaloud'))
  const position = POSITIONS().find((p) => p.id === settings.position)?.name ?? ''
  const posts = cfg.recentPosts ?? []
  const viewUrl = posts.find((p) => p.status.id !== 'off')?.viewUrl
  const name = cfg.userName?.trim()

  const sample = async () => {
    if (!provider) return
    setError('')
    setPlaying(true)
    try {
      if (provider.clientSide) {
        await speak(SAMPLE_TEXT, undefined, settings.speed || 1)
      } else {
        const res = await api<{ audio: string }>('preview', { method: 'POST', body: { provider: provider.id, voice: settings.voice } })
        await playUrl(res.audio)
      }
    } catch (e) {
      setError((e as Error).message)
    } finally {
      setPlaying(false)
    }
  }

  return (
    <div className="lv-home">
      <div className="lv-welcome">
        <div>
          <h1>{name ? `${__('Welcome back,', 'postaloud')} ${name}` : __('Welcome back', 'postaloud')}</h1>
          <p className="lv-muted">{__('Your posts, read aloud in the voice you choose.', 'postaloud')}</p>
        </div>
        {viewUrl && (
          <a className="lv-btn lv-btn-dark lv-btn-link" href={viewUrl} target="_blank" rel="noreferrer">
            {__('View a post', 'postaloud')}
            <Icon name="external" size={16} />
            <span className="screen-reader-text">{__('(opens in a new tab)', 'postaloud')}</span>
          </a>
        )}
      </div>

      {cfg.insertMiss && settings.position !== 'floating' && <InsertMiss miss={cfg.insertMiss} onOpen={onOpen} />}

      <div className="lv-home-top">
        <section className="lv-card lv-voice-card" aria-labelledby="lv-voice-title">
          <div className="lv-card-head">
            <h2 id="lv-voice-title">{__('Your voice', 'postaloud')}</h2>
            <span className={`lv-pill lv-pill-${ready ? 'ok' : 'warn'}`}>{ready ? __('Ready', 'postaloud') : __('Needs a key', 'postaloud')}</span>
          </div>
          <dl className="lv-facts">
            <div><dt>{__('Provider', 'postaloud')}</dt><dd>{provider?.label}</dd></div>
            <div><dt>{__('Voice', 'postaloud')}</dt><dd>{voice}</dd></div>
            <div><dt>{__('Speed', 'postaloud')}</dt><dd>{settings.speed}×</dd></div>
            <div><dt>{__('Player', 'postaloud')}</dt><dd>{position}</dd></div>
            <div><dt>{__('Plays', 'postaloud')}</dt><dd>{(cfg.plays ?? 0).toLocaleString()}</dd></div>
          </dl>
          <div className="lv-row">
            <button type="button" className="lv-btn lv-btn-play" onClick={sample} disabled={playing || !ready}>
              <span className="lv-play-dot"><Icon name="play" size={14} /></span>
              {playing ? __('Playing…', 'postaloud') : __('Play a sample', 'postaloud')}
            </button>
            <button type="button" className="lv-btn" onClick={() => onOpen('voices')}>
              {ready ? __('Change voice', 'postaloud') : __('Add your key', 'postaloud')}
            </button>
          </div>
          {error && <p className="lv-error" role="alert">{error}</p>}
        </section>

        <section className="lv-card" aria-labelledby="lv-tiles-title">
          <h2 id="lv-tiles-title">{__('Go to', 'postaloud')}</h2>
          <div className="lv-tiles">
            {tiles.map((t) => (
              <button key={t.id} type="button" className="lv-tile" onClick={() => onOpen(t.id)}>
                <span className="lv-tile-icon"><Icon name={t.icon ?? 'page'} /></span>
                <span className="lv-tile-label">{t.label}</span>
                {t.description && <span className="lv-tile-desc">{t.description}</span>}
              </button>
            ))}
          </div>
        </section>
      </div>

      <section className="lv-card" aria-labelledby="lv-recent-title">
        <div className="lv-card-head">
          <h2 id="lv-recent-title">{__('Recent posts', 'postaloud')}</h2>
        </div>
        {settings.post_types.length === 0 ? (
          <div className="lv-empty">
            <p className="lv-muted">{__("The player isn't shown on any post type yet.", 'postaloud')}</p>
            <button type="button" className="lv-btn" onClick={() => onOpen('player')}>{__('Choose where it shows', 'postaloud')}</button>
          </div>
        ) : posts.length === 0 ? (
          <p className="lv-muted lv-empty">{__('No published posts yet. The player appears on them as soon as they go live.', 'postaloud')}</p>
        ) : (
          <ul className="lv-posts">
            {posts.map((p) => (
              <li key={p.id} className="lv-post">
                <div className="lv-post-text">
                  <span className="lv-post-title">{p.title || __('(no title)', 'postaloud')}</span>
                  <span className="lv-post-date">
                    {p.date}
                    {typeof p.plays === 'number' && ` · ${p.plays === 1 ? __('1 play', 'postaloud') : `${p.plays.toLocaleString()} ${__('plays', 'postaloud')}`}`}
                  </span>
                </div>
                <span className={`lv-pill lv-pill-${p.status.id === 'off' ? 'off' : p.status.id === 'live' || p.status.id === 'ready' ? 'ok' : 'warn'}`}>{p.status.label}</span>
                <div className="lv-post-actions">
                  <a className="lv-btn lv-btn-small" href={p.viewUrl} target="_blank" rel="noreferrer" aria-label={`${__('View', 'postaloud')}: ${p.title} ${__('(opens in a new tab)', 'postaloud')}`}>{__('View', 'postaloud')}</a>
                  {p.editUrl && <a className="lv-btn lv-btn-small" href={p.editUrl} aria-label={`${__('Edit', 'postaloud')}: ${p.title}`}>{__('Edit', 'postaloud')}</a>}
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}

// The player should have shown on a post but the theme or page builder didn't give it a place.
function InsertMiss({ miss, onOpen }: { miss: NonNullable<ReturnType<typeof config>['insertMiss']>; onOpen: (page: string) => void }) {
  return (
    <section className="lv-warn lv-miss" role="status" aria-labelledby="lv-miss-title">
      <h2 id="lv-miss-title">{__('The player didn’t show on a post', 'postaloud')}</h2>
      <p>
        <a href={miss.viewUrl} target="_blank" rel="noreferrer">
          {miss.title || __('(no title)', 'postaloud')}
          <span className="screen-reader-text">{__('(opens in a new tab)', 'postaloud')}</span>
        </a>{' '}
        {miss.reason === 'loop'
          ? __('is shown by your theme or page builder outside the usual post layout, so the player had no place to go.', 'postaloud')
          : __('is built by your theme or page builder without the usual post content, so the player had no place to go.', 'postaloud')}
      </p>
      <p>{__('Any one of these fixes it:', 'postaloud')}</p>
      <ul>
        <li>{__('Choose Floating: it always shows, whatever builds the page.', 'postaloud')}</li>
        <li>{__('Add the PostAloud player block, the Elementor widget or [postaloud_player] to the post or its template.', 'postaloud')}</li>
      </ul>
      <div className="lv-row">
        <button type="button" className="lv-btn lv-btn-dark" onClick={() => onOpen('player')}>
          {__('Change where it shows', 'postaloud')}
        </button>
        {miss.editUrl && (
          <a className="lv-btn lv-btn-link" href={miss.editUrl}>
            {__('Edit the post', 'postaloud')}
          </a>
        )}
      </div>
    </section>
  )
}
