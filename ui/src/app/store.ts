import type { BootPayload, PagePayload, ThemePreference } from '@larecipe/ui'
import { useCallback, useSyncExternalStore } from 'react'

/**
 * The reading experience's whole state in one small store on
 * useSyncExternalStore (ARCHITECTURE.md 8): route, page, theme and which panel
 * is open. No router or state library; the surface is tiny.
 */
export interface AppState {
  /** The boot payload, kept current: navigation, set, version and URLs change on client navigation. */
  boot: BootPayload
  page: PagePayload
  /** The article: null html means "the server-rendered node, adopted"; key changes on every swap. */
  article: { key: number; html: string | null }
  /** Server fallbacks for insert points (point => HTML). */
  insertsHtml: Record<string, string>
  theme: { preference: ThemePreference; mode: 'light' | 'dark' }
  palette: { open: boolean; query: string }
  ask: { open: boolean; question: string | null; nonce: number }
  shortcuts: boolean
  sidebar: boolean
  /** The URL being fetched by a client navigation, if any. */
  navigating: string | null
  /** Set after a client navigation, so arriving tickets can print. */
  navigated: boolean
}

type Listener = () => void

export interface Store {
  get(): AppState
  set(update: Partial<AppState> | ((state: AppState) => Partial<AppState>)): void
  subscribe(listener: Listener): () => void
}

export function createStore(initial: AppState): Store {
  let state = initial
  const listeners = new Set<Listener>()

  return {
    get: () => state,
    set(update) {
      const patch = typeof update === 'function' ? update(state) : update
      const next = { ...state, ...patch }
      const changed = (Object.keys(patch) as Array<keyof AppState>).some((key) => state[key] !== next[key])
      if (!changed) return
      state = next
      listeners.forEach((listener) => listener())
    },
    subscribe(listener) {
      listeners.add(listener)
      return () => {
        listeners.delete(listener)
      }
    },
  }
}

/** Select a slice of the store; re-renders only when the slice changes (by identity). */
export function useStoreState<T>(store: Store, selector: (state: AppState) => T): T {
  const subscribe = useCallback((listener: Listener) => store.subscribe(listener), [store])
  const snapshot = useCallback(() => selector(store.get()), [store, selector])
  return useSyncExternalStore(subscribe, snapshot, snapshot)
}

/** The initial state from the boot payload and the inserts captured from the prerender. */
export function initialState(boot: BootPayload, insertsHtml: Record<string, string>, theme: AppState['theme']): AppState {
  return {
    boot,
    page: boot.page,
    article: { key: 0, html: null },
    insertsHtml,
    theme,
    palette: { open: false, query: '' },
    ask: { open: false, question: null, nonce: 0 },
    shortcuts: false,
    sidebar: false,
    navigating: null,
    navigated: false,
  }
}
