// Strings use wp.i18n so translations load with wp_set_script_translations().
// The function keeps the name __ in build/ (vite.config.ts), so `wp i18n make-pot`
// finds the strings in build/ as well as in frontend/.
declare global {
  interface Window {
    wp?: { i18n?: { __: (text: string, domain: string) => string } }
  }
}

export const __ = (text: string, domain: string): string => window.wp?.i18n?.__(text, domain) ?? text
