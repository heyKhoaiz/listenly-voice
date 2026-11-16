import { __ } from '../lib/i18n'
import { config, type Settings } from '../lib/config'
import { PositionPicker, PostTypePicker } from '../components/Placement'
import { highlightModes } from '../lib/extend'

type Props = { settings: Settings; onChange: (s: Settings) => void }

// Where the player shows and how it reads along. Saved with the top bar's "Save changes".
export function Player({ settings, onChange }: Props) {
  const highlightOn = settings.highlight !== 'off'
  const extraModes = highlightModes()
  const modes = [{ id: 'sentence', label: __('Each sentence', 'listenly-voice'), description: __('Works with every voice.', 'listenly-voice') }, ...extraModes]

  return (
    <div className="lv-grid">
      <section className="lv-card">
        <h2>{__('Where it shows', 'listenly-voice')}</h2>
        <p className="lv-muted">{__('Pick the post types and where the player sits on them.', 'listenly-voice')}</p>
        <div className="lv-stack">
          <PostTypePicker settings={settings} onChange={onChange} />
          {settings.post_types.length === 0 && (
            <p className="lv-warn">
              {__('The player is off everywhere. Tick a post type, or add [listenly_player] where you want it.', 'listenly-voice')}
            </p>
          )}
          <PositionPicker settings={settings} onChange={onChange} />
        </div>
      </section>

      <section className="lv-card">
        <h2>{__('Read along', 'listenly-voice')}</h2>
        <div className="lv-switch-row">
          <span className="lv-switch-text">
            <span id="lv-highlight-label" className="lv-option-name">{__('Highlight the sentence being read', 'listenly-voice')}</span>
            <span className="lv-voice-desc">
              {__('Helps readers follow along, with the browser voice and Google voices. Works in Chrome, Edge, Safari and Firefox.', 'listenly-voice')}
            </span>
          </span>
          <button
            type="button"
            role="switch"
            aria-checked={highlightOn}
            aria-labelledby="lv-highlight-label"
            className="lv-switch"
            onClick={() => onChange({ ...settings, highlight: highlightOn ? 'off' : 'sentence' })}
          >
            <span aria-hidden="true" />
          </button>
        </div>
        {highlightOn && extraModes.length > 0 && (
          <div role="radiogroup" aria-label={__('What to highlight', 'listenly-voice')} className="lv-options">
            {modes.map((m) => (
              <button
                key={m.id}
                type="button"
                role="radio"
                aria-checked={settings.highlight === m.id}
                className="lv-option"
                onClick={() => onChange({ ...settings, highlight: m.id })}
              >
                <span className="lv-radio" aria-hidden="true" />
                <span className="lv-voice-text">
                  <span className="lv-option-name">{m.label}</span>
                  {m.description && <span className="lv-voice-desc">{m.description}</span>}
                </span>
              </button>
            ))}
          </div>
        )}
      </section>

      <section className="lv-card">
        <h2>{__('Colours', 'listenly-voice')}</h2>
        <p className="lv-muted">{__('Auto picks light or dark to match the page behind the player, and follows themes that switch at night.', 'listenly-voice')}</p>
        <div role="radiogroup" aria-label={__('Player colours', 'listenly-voice')} className="lv-themes">
          {THEMES().map((t) => (
            <button
              key={t.id}
              type="button"
              role="radio"
              aria-checked={(settings.theme ?? 'auto') === t.id}
              className="lv-option lv-theme"
              onClick={() => onChange({ ...settings, theme: t.id })}
            >
              <span className={`lv-swatch lv-swatch-${t.id}`} aria-hidden="true"><b /></span>
              <span className="lv-voice-text">
                <span className="lv-option-name"><span className="lv-radio" aria-hidden="true" />{t.name}</span>
                <span className="lv-voice-desc">{t.desc}</span>
              </span>
            </button>
          ))}
        </div>
      </section>

      <section className="lv-card" aria-labelledby="lv-say-title">
        <h2 id="lv-say-title">{__('Pronunciation', 'listenly-voice')}</h2>
        <p className="lv-muted">
          {__('Tell the voices how to say names, brands and abbreviations. Whole words only; capitals don’t matter.', 'listenly-voice')}
        </p>
        <SayRows pairs={settings.say ?? []} onChange={(say) => onChange({ ...settings, say })} />
      </section>

      <section className="lv-card">
        <h2>{__('Place it by hand', 'listenly-voice')}</h2>
        <p className="lv-muted">{__('Put this shortcode in any post, page or widget to show the player there.', 'listenly-voice')}</p>
        <code className="lv-code">[listenly_player]</code>
      </section>
    </div>
  )
}

function SayRows({ pairs, onChange }: { pairs: [string, string][]; onChange: (pairs: [string, string][]) => void }) {
  const limit = config().sayLimit ?? 10
  const update = (i: number, col: 0 | 1, value: string) => onChange(pairs.map((p, j) => (j === i ? (col === 0 ? [value, p[1]] : [p[0], value]) : p)))
  return (
    <div className="lv-stack">
      {pairs.length === 0 && <p className="lv-muted">{__('No words yet. Example: “WP” said as “WordPress”.', 'listenly-voice')}</p>}
      {pairs.map((p, i) => (
        <div key={i} className="lv-say-row" role="group" aria-label={`${__('Word', 'listenly-voice')} ${i + 1}`}>
          <label className="lv-field">
            {__('Written', 'listenly-voice')}
            <input value={p[0]} maxLength={80} placeholder="WP" onChange={(e) => update(i, 0, e.target.value)} />
          </label>
          <label className="lv-field">
            {__('Said as', 'listenly-voice')}
            <input value={p[1]} maxLength={160} placeholder="WordPress" onChange={(e) => update(i, 1, e.target.value)} />
          </label>
          <button type="button" className="lv-btn" aria-label={`${__('Remove word', 'listenly-voice')} ${i + 1}`} onClick={() => onChange(pairs.filter((_, j) => j !== i))}>
            {__('Remove', 'listenly-voice')}
          </button>
        </div>
      ))}
      <div className="lv-row">
        <button type="button" className="lv-btn" disabled={pairs.length >= limit} onClick={() => onChange([...pairs, ['', '']])}>
          {__('Add a word', 'listenly-voice')}
        </button>
        <span className="lv-muted">{`${pairs.length} / ${limit}`}</span>
      </div>
    </div>
  )
}

const THEMES = (): { id: Settings['theme']; name: string; desc: string }[] => [
  { id: 'auto', name: __('Auto (recommended)', 'listenly-voice'), desc: __('Matches your theme', 'listenly-voice') },
  { id: 'light', name: __('Light', 'listenly-voice'), desc: __('Always light', 'listenly-voice') },
  { id: 'dark', name: __('Dark', 'listenly-voice'), desc: __('Always dark', 'listenly-voice') },
]
