import type { Extension, LaRecipeComponent } from './types'

type Listener = () => void

/**
 * Everything host extensions registered. One registry per page, shared by the
 * app and every extension because @larecipe/ui is a single module on the page.
 */
class Registry {
  components = new Map<string, LaRecipeComponent>()
  slots = new Map<string, React.ComponentType<Record<string, unknown>>>()
  inserts: Record<string, string[]> = {}
  private listeners = new Set<Listener>()

  register(extension: Extension): void {
    for (const [name, component] of Object.entries(extension.components ?? {})) {
      this.components.set(name, component)
    }
    for (const [name, slot] of Object.entries(extension.slots ?? {})) {
      this.slots.set(name, slot)
    }
    for (const [point, names] of Object.entries(extension.inserts ?? {})) {
      this.inserts[point] = names
    }
    this.listeners.forEach((listener) => listener())
  }

  subscribe(listener: Listener): () => void {
    this.listeners.add(listener)
    return () => this.listeners.delete(listener)
  }
}

export const registry = new Registry()

declare global {
  interface Window {
    LaRecipe?: { register: (extension: Extension) => void; version: string }
    __larecipeQueue?: Extension[]
  }
}

/**
 * Register a host extension. Works whether the extension module loads before or
 * after the LaRecipe runtime.
 */
export function defineExtension(extension: Extension): Extension {
  if (typeof window !== 'undefined') {
    if (window.LaRecipe) {
      window.LaRecipe.register(extension)
    } else {
      ;(window.__larecipeQueue ??= []).push(extension)
    }
  }
  return extension
}

/** Called once by the LaRecipe runtime when it boots. */
export function installRuntime(version: string): void {
  window.LaRecipe = { register: (extension) => registry.register(extension), version }
  for (const extension of window.__larecipeQueue ?? []) {
    registry.register(extension)
  }
  window.__larecipeQueue = []
}
