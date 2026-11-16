import { registerPostToggle, type WpGlobals } from './editor'

registerPostToggle((window as unknown as { wp?: WpGlobals }).wp)
