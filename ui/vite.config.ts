import { resolve } from 'node:path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

/**
 * Builds the LaRecipe UI into the package's dist/ folder.
 *
 * Besides the reader ("app") and admin entries, the build emits one small entry
 * per shared module (react, react-dom, react/jsx-runtime, @larecipe/ui). The page's
 * import map points host extensions at those files, and because the app imports
 * the same modules, Rollup puts them in shared chunks: there is exactly one React
 * and one @larecipe/ui runtime on the page.
 */
export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: './',
  resolve: {
    alias: {
      '@larecipe/ui': resolve(__dirname, '../packages/ui/src/index.ts'),
      '@': resolve(__dirname, 'src'),
    },
  },
  build: {
    outDir: resolve(__dirname, '../dist'),
    emptyOutDir: true,
    manifest: true,
    target: 'es2022',
    sourcemap: false,
    rollupOptions: {
      input: {
        app: resolve(__dirname, 'src/app/main.tsx'),
        admin: resolve(__dirname, 'src/admin/main.tsx'),
        react: resolve(__dirname, 'src/shims/react.ts'),
        'react-dom': resolve(__dirname, 'src/shims/react-dom.ts'),
        'react-dom-client': resolve(__dirname, 'src/shims/react-dom-client.ts'),
        'jsx-runtime': resolve(__dirname, 'src/shims/jsx-runtime.ts'),
        'larecipe-ui': resolve(__dirname, 'src/shims/larecipe-ui.ts'),
      },
      preserveEntrySignatures: 'strict',
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
  server: {
    port: 5174,
    strictPort: true,
    cors: true,
    origin: 'http://localhost:5174',
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/*.test.{ts,tsx}'],
    setupFiles: ['src/test/setup.ts'],
    restoreMocks: true,
  },
} as never)
