import { __ } from '../lib/i18n'
import { config, type Settings } from '../lib/config'

type Position = Settings['position']

export const POSITIONS = (): { id: Position; name: string; done: string }[] => [
  { id: 'above', name: __('Top of the post', 'listenly-voice'), done: __('at the top of every', 'listenly-voice') },
  { id: 'below', name: __('End of the post', 'listenly-voice'), done: __('at the end of every', 'listenly-voice') },
  { id: 'floating', name: __('Floating button', 'listenly-voice'), done: __('as a floating button on every', 'listenly-voice') },
]

type Props = { settings: Settings; onChange: (s: Settings) => void }

/** Post type checkboxes ("Show on"). Keeps the site's order so the saved list is stable. */
export function PostTypePicker({ settings, onChange }: Props) {
  const cfg = config()
  const toggle = (id: string, on: boolean) => {
    const list = on ? [...settings.post_types, id] : settings.post_types.filter((t) => t !== id)
    onChange({ ...settings, post_types: cfg.postTypes.map((t) => t.id).filter((t) => list.includes(t)) })
  }
  return (
    <fieldset className="lv-fieldset">
      <legend>{__('Show on', 'listenly-voice')}</legend>
      {cfg.postTypes.map((t) => (
        <label key={t.id} className="lv-check">
          <input type="checkbox" checked={settings.post_types.includes(t.id)} onChange={(e) => toggle(t.id, e.target.checked)} />
          {t.label}
        </label>
      ))}
    </fieldset>
  )
}

/** Three placement cards with a small drawing of each. */
export function PositionPicker({ settings, onChange }: Props) {
  return (
    <div role="radiogroup" aria-label={__('Player position', 'listenly-voice')} className="lv-positions">
      {POSITIONS().map((p) => (
        <button
          key={p.id}
          type="button"
          role="radio"
          aria-checked={settings.position === p.id}
          className="lv-position"
          onClick={() => onChange({ ...settings, position: p.id })}
        >
          <span className={`lv-mini lv-mini-${p.id}`} aria-hidden="true">
            <i /><i /><i /><i /><b />
          </span>
          {p.name}
        </button>
      ))}
    </div>
  )
}
