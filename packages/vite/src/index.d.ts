import type { Plugin, PluginOption, LogLevel } from 'vite'

export interface LaRecipePluginOptions {
  /** The extension entry, relative to the Vite root. Default: resources/larecipe/extension.tsx */
  input?: string
  /** Where the built extension goes, relative to the Vite root. Default: public/build/larecipe (LaRecipe finds it there). */
  outDir?: string
  /** The project root; defaults to Vite's root. */
  root?: string
  /** Extra Vite plugins for the extension build (for example a CSS preprocessor). */
  plugins?: PluginOption[]
}

export interface BuildExtensionOptions extends LaRecipePluginOptions {
  mode?: string
  watch?: boolean
  logLevel?: LogLevel
}

/** Bare specifiers the page's import map provides; never bundled. */
export declare const SHARED_MODULES: readonly string[]

/** Build the extension once, or keep rebuilding it with `watch: true`. */
export declare function buildExtension(options?: BuildExtensionOptions): Promise<unknown>

/** The Vite plugin: builds the LaRecipe extension next to the host app. */
export declare function larecipe(options?: LaRecipePluginOptions): Plugin

export default larecipe
