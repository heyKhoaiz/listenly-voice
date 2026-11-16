import { defineConfig } from 'vite'

// The block editor panel: one classic script with a fixed name, enqueued by
// backend/Editor/PostToggle.php with LISTENLY_VERSION as ?ver=. It uses window.wp, so it bundles no React.
export default defineConfig({
  esbuild: { minifyIdentifiers: false, minifySyntax: false },
  build: {
    outDir: 'build/editor',
    emptyOutDir: true,
    lib: { entry: 'frontend/editor/main.ts', formats: ['iife'], name: 'listenlyEditor', fileName: () => 'editor.js' },
  },
})
