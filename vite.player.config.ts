import { defineConfig } from 'vite'

// The frontend player: one small classic script plus its CSS, with fixed names, because
// backend/Player/Player.php enqueues build/player/player.js with LISTENLY_VERSION as ?ver=.
// No React on the frontend: the player must stay small (target < 30 KB gzipped).
export default defineConfig({
  esbuild: { minifyIdentifiers: false, minifySyntax: false },
  build: {
    outDir: 'build/player',
    emptyOutDir: true,
    lib: { entry: 'frontend/player/player.ts', formats: ['iife'], name: 'listenlyPlayer', fileName: () => 'player.js' },
    rollupOptions: { output: { assetFileNames: 'player[extname]' } },
  },
})
