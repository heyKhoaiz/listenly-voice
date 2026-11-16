// Block editor sidebar panel: a switch that turns the player off for one post by saving
// `_listenly_disabled` (registered in backend/Editor/PostToggle.php). Uses the editor's own
// window.wp packages, so the build has no React or WordPress code of its own.

import type { ComponentType, ReactNode } from 'react'

export const META = '_listenly_disabled'

type Meta = Record<string, unknown>
type Panel = ComponentType<{ name: string; title: string; className?: string; children?: ReactNode }>

/** The parts of window.wp this file uses. */
export type WpGlobals = {
  element: { createElement: (...args: any[]) => any }
  i18n: { __: (text: string, domain?: string) => string }
  components: { ToggleControl: ComponentType<any> }
  data: {
    useSelect: <T>(pick: (select: (store: string) => { getEditedPostAttribute: (key: string) => unknown }) => T, deps?: unknown[]) => T
    useDispatch: (store: string) => { editPost: (edits: { meta: Meta }) => void }
  }
  plugins: { registerPlugin: (name: string, settings: { render: () => ReactNode; icon?: unknown }) => void }
  editor?: { PluginDocumentSettingPanel?: Panel }
  editPost?: { PluginDocumentSettingPanel?: Panel }
}

/** Registers the panel. Returns false when the editor can't host it. */
export function registerPostToggle(wp: WpGlobals | undefined): boolean {
  // WordPress 6.6 moved the slot from wp.editPost to wp.editor.
  const Slot = wp?.editor?.PluginDocumentSettingPanel ?? wp?.editPost?.PluginDocumentSettingPanel
  if (!wp || !Slot) return false

  const { createElement: el } = wp.element
  const { __ } = wp.i18n
  const { ToggleControl } = wp.components

  function AudioPanel() {
    const meta = wp!.data.useSelect((select) => (select('core/editor').getEditedPostAttribute('meta') as Meta | undefined) ?? {}, [])
    const { editPost } = wp!.data.useDispatch('core/editor')
    const on = !meta[META]
    return el(
      Slot,
      { name: 'listenly-voice', title: __('Listenly Voice', 'listenly-voice'), className: 'listenly-voice-panel' },
      el(ToggleControl, {
        label: __('Audio player on this post', 'listenly-voice'),
        help: on ? __('Readers can listen to this post.', 'listenly-voice') : __("Readers won't see the player on this post.", 'listenly-voice'),
        checked: on,
        onChange: (value: boolean) => editPost({ meta: { [META]: !value } }),
        __nextHasNoMarginBottom: true,
      }),
    )
  }

  wp.plugins.registerPlugin('listenly-voice', { render: AudioPanel, icon: null })
  return true
}
