// window.listenlyConfig, printed by backend/Admin/Assets.php. It never holds API keys.
export type Voice = { id: string; label: string; language: string }
export type Provider = { id: string; label: string; ready: boolean; clientSide: boolean; voices: Voice[] }
export type Settings = {
  provider: string
  voice: string
  speed: number
  post_types: string[]
  position: 'above' | 'below' | 'floating'
  highlight: 'sentence' | 'off' | 'word'
}
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
  pro: boolean
}

export const config = (): Config => (window as unknown as { listenlyConfig: Config }).listenlyConfig
