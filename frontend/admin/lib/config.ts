// window.listenlyConfig, printed by backend/Admin/Assets.php. It never holds API keys.
export type Voice = { id: string; label: string; language: string }
export type Provider = { id: string; label: string; ready: boolean; clientSide: boolean; voices: Voice[] }
export type Settings = {
  provider: string
  voice: string
  speed: number
  post_types: string[]
  position: 'above' | 'below' | 'floating'
  // 'sentence' and 'off' in Free; extensions can add modes (listenly.admin.highlightModes).
  highlight: 'sentence' | 'off' | (string & {})
  // Player colours. 'auto' follows the page behind the player.
  theme: 'auto' | 'light' | 'dark'
  /** Pronunciation: [written, said as] pairs, at most Config.sayLimit. */
  say: [string, string][]
}
export type RecentPost = { id: number; title: string; date: string; viewUrl: string; editUrl: string; plays?: number; status: { id: string; label: string } }
export type Config = {
  restUrl: string
  nonce: string
  pageUrl: string
  version: string
  settings: Settings
  providers: Provider[]
  googleKeySet: boolean
  setupDone: boolean
  postTypes: { id: string; label: string }[]
  recentPosts: RecentPost[]
  userName: string
  pro: boolean
  /** Plays across the site. */
  plays?: number
  /** The plugin name in the header (white-label sites can change it). */
  brand?: string
  /** The last post where the player should have shown but didn't: 'loop' = shown outside the main loop, 'content' = the theme or builder skipped the content filter. */
  insertMiss?: { title: string; viewUrl: string; editUrl: string; reason: 'loop' | 'content' } | null
  /** How many pronunciation pairs can be saved. */
  sayLimit?: number
  /** Show the FAQ and forum links. */
  helpLinks?: boolean
}

export const config = (): Config => (window as unknown as { listenlyConfig: Config }).listenlyConfig
