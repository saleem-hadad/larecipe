import larecipe from '@larecipe/vite'
import { defineConfig } from 'vite'

// The workbench stands in for a host Laravel app: its own entry, plus the one
// line a host adds to build its LaRecipe extension.
export default defineConfig({
  plugins: [larecipe({ input: 'resources/larecipe/extension.tsx', outDir: 'public/build/larecipe' })],
  // Like laravel-vite-plugin: public/ is served as is, never copied into a build inside it.
  publicDir: false,
  build: {
    outDir: 'public/build/app',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: { input: 'resources/js/app.js' },
  },
})
