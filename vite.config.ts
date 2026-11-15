import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// A release build uses the React that WordPress loads ('react', 'react-dom', 'wp-element'),
// so build/ does not contain a second copy. The dev server uses React from node_modules.
const wpReact = (file: string) => fileURLToPath(new URL(`frontend/admin/react/${file}`, import.meta.url))

// The admin app is loaded by backend/Admin/Assets.php from build/.vite/manifest.json.
export default defineConfig(({ command }) => ({
  plugins: [react()],
  base: './',
  resolve: {
    alias:
      command === 'build'
        ? [
            { find: /^react$/, replacement: wpReact('react.ts') },
            { find: /^react\/jsx-runtime$/, replacement: wpReact('jsx-runtime.ts') },
            { find: /^react-dom$/, replacement: wpReact('react-dom.ts') },
            { find: /^react-dom\/client$/, replacement: wpReact('react-dom-client.ts') },
          ]
        : [],
  },
  // Keep names such as __ in build/, so `wp i18n make-pot` finds the strings in the shipped files.
  // minifySyntax: false keeps each __() call whole.
  esbuild: { minifyIdentifiers: false, minifySyntax: false },
  build: {
    outDir: 'build',
    // A watch build keeps build/player/ (vite.player.config.ts) while both watch.
    emptyOutDir: !process.argv.includes('--watch'),
    manifest: true,
    rollupOptions: { input: 'frontend/admin/main.tsx' },
  },
  server: {
    host: '0.0.0.0',
    port: 5184,
    strictPort: true,
    cors: true,
    origin: 'http://localhost:5184',
  },
}))
