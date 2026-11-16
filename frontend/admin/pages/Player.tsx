import { __ } from '../lib/i18n'
import type { Settings } from '../lib/config'
import { PositionPicker, PostTypePicker } from '../components/Placement'

type Props = { settings: Settings; onChange: (s: Settings) => void }

// Where the player shows and how it reads along. Saved with the top bar's "Save changes".
export function Player({ settings, onChange }: Props) {
  const highlightOn = settings.highlight !== 'off'

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
      </section>

      <section className="lv-card">
        <h2>{__('Place it by hand', 'listenly-voice')}</h2>
        <p className="lv-muted">{__('Put this shortcode in any post, page or widget to show the player there.', 'listenly-voice')}</p>
        <code className="lv-code">[listenly_player]</code>
      </section>
    </div>
  )
}
