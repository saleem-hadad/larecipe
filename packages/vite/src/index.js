import { resolve } from 'node:path'
import { build, version as viteVersion } from 'vite'

/**
 * @larecipe/vite: builds a host app's LaRecipe extension (its custom React
 * components) next to the app's own Vite build.
 *
 * The extension is an ES module written to public/build/larecipe/ with a
 * manifest LaRecipe's PHP side detects. React, react-dom and @larecipe/ui stay
 * external: in the page, an import map resolves them to the LaRecipe runtime's
 * own copies, so there is exactly one React and one registry. Node is needed
 * only here, at build time; nothing runs Node on the server.
 */

/** Bare specifiers the page's import map provides. Never bundle these. */
export const SHARED_MODULES = ['react', 'react/jsx-runtime', 'react/jsx-dev-runtime', 'react-dom', 'react-dom/client', '@larecipe/ui']

const DEFAULTS = {
  input: 'resources/larecipe/extension.tsx',
  outDir: 'public/build/larecipe',
}

function isShared(id) {
  return SHARED_MODULES.includes(id)
}

/**
 * Vite 8 bundles with Rolldown and transforms with Oxc: it reads
 * build.rolldownOptions and oxc, and only maps the older rollupOptions and
 * esbuild keys (deprecated). Vite 6 and 7 read only the older keys.
 */
const ROLLDOWN = Number.parseInt(viteVersion, 10) >= 8

/** JSX with the automatic runtime, always the production jsx-runtime (the runtime's own copy). */
function jsxOptions() {
  return ROLLDOWN
    ? { oxc: { jsx: { runtime: 'automatic', development: false } } }
    : { esbuild: { jsx: 'automatic', jsxDev: false } }
}

/**
 * Build the extension once (or keep rebuilding it with `watch: true`).
 *
 * @param {import('./index').BuildExtensionOptions} options
 */
export async function buildExtension(options = {}) {
  const root = options.root ?? process.cwd()
  const mode = options.mode ?? 'production'
  const production = mode === 'production'

  return build({
    configFile: false,
    root,
    mode,
    logLevel: options.logLevel ?? 'info',
    clearScreen: false,
    plugins: options.plugins ?? [],
    define: {
      'process.env.NODE_ENV': JSON.stringify(production ? 'production' : 'development'),
    },
    // The runtime's jsx-runtime is the production build; dev mode maps to it too.
    ...jsxOptions(),
    build: {
      outDir: resolve(root, options.outDir ?? DEFAULTS.outDir),
      emptyOutDir: true,
      manifest: 'manifest.json',
      target: 'es2022',
      minify: production,
      sourcemap: !production,
      copyPublicDir: false,
      watch: options.watch ? {} : null,
      [ROLLDOWN ? 'rolldownOptions' : 'rollupOptions']: {
        input: { extension: resolve(root, options.input ?? DEFAULTS.input) },
        external: isShared,
        preserveEntrySignatures: 'allow-extension',
        output: {
          format: 'es',
          entryFileNames: '[name]-[hash].js',
          chunkFileNames: '[name]-[hash].js',
          assetFileNames: '[name]-[hash][extname]',
        },
      },
    },
  })
}

/**
 * The Vite plugin. Add it next to laravel-vite-plugin:
 *
 *   plugins: [laravel({ input: [...] }), larecipe({ input: 'resources/larecipe/extension.tsx' })]
 *
 * `vite build` builds the extension after the app. `vite` (dev) keeps the
 * extension rebuilding on change; reload the docs page to pick it up.
 *
 * @param {import('./index').LaRecipePluginOptions} options
 * @returns {import('vite').Plugin}
 */
export function larecipe(options = {}) {
  let config
  let watcher = null

  return {
    name: 'larecipe',
    enforce: 'post',

    configResolved(resolved) {
      config = resolved
    },

    async closeBundle() {
      if (!config || config.command !== 'build' || config.build.ssr) return
      await buildExtension({ ...options, root: options.root ?? config.root, mode: config.mode, logLevel: config.logLevel })
    },

    async configureServer(server) {
      if (watcher) return
      try {
        watcher = await buildExtension({ ...options, root: options.root ?? server.config.root, mode: 'development', watch: true, logLevel: 'warn' })
        server.config.logger.info('  larecipe  extension rebuilds on change into ' + (options.outDir ?? DEFAULTS.outDir))
      } catch (error) {
        server.config.logger.error('larecipe: the extension failed to build.\n' + String(error?.stack ?? error))
      }
      server.httpServer?.once('close', () => watcher?.close?.())
    },
  }
}

export default larecipe
