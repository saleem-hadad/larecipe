import type { BootPayload, ThemePreference } from '@larecipe/ui'
import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react'
import type { Router } from './router'
import { useStoreState, type AppState, type Store } from './store'
import { applyTheme } from './theme'

/**
 * Services the regions share: the store, the router and a handful of actions.
 * Host extensions never see this; they get useLaRecipe() from @larecipe/ui.
 */
export interface Actions {
  openPalette(query?: string): void
  closePalette(): void
  openAsk(question?: string | null): void
  closeAsk(): void
  setShortcuts(open: boolean): void
  setSidebar(open: boolean): void
  setTheme(preference: ThemePreference): void
  copyText(text: string): Promise<boolean>
  copyMarkdown(): Promise<boolean>
  copyMcpCommand(): Promise<boolean>
  announce(message: string): void
}

export interface AppServices {
  store: Store
  router: Router
  actions: Actions
}

export const AppContext = createContext<AppServices | null>(null)

export function useApp(): AppServices {
  const value = useContext(AppContext)
  if (!value) throw new Error('LaRecipe regions must render inside <App>.')
  return value
}

export function useAppState<T>(selector: (state: AppState) => T): T {
  const { store } = useApp()
  return useStoreState(store, selector)
}

/** Ask is available only when the set enabled it and an endpoint exists. */
export function askEnabled(boot: BootPayload): boolean {
  return Boolean(boot.features.ask && boot.ask?.endpoint && boot.ask.enabled !== false)
}

export function feedbackEnabled(boot: BootPayload): boolean {
  return Boolean(boot.features.feedback && boot.feedback?.endpoint)
}

/** The command a reader pastes to connect this doc set's MCP server to Claude Code. */
export function mcpCommand(boot: BootPayload): string | null {
  if (boot.page.actions) return boot.page.actions.mcp_command
  if (!boot.urls.mcp) return null
  const name = boot.set.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || boot.set.key
  return `claude mcp add --transport http ${name} ${boot.urls.mcp}`
}

async function writeClipboard(text: string | Promise<string>): Promise<boolean> {
  try {
    if (typeof text !== 'string' && typeof ClipboardItem !== 'undefined' && navigator.clipboard?.write) {
      // Safari only allows async clipboard writes started inside the gesture.
      await navigator.clipboard.write([new ClipboardItem({ 'text/plain': text.then((value) => new Blob([value], { type: 'text/plain' })) })])
      return true
    }
    const value = await text
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value)
      return true
    }
    return legacyCopy(value)
  } catch {
    try {
      return legacyCopy(await text)
    } catch {
      return false
    }
  }
}

function legacyCopy(value: string): boolean {
  const area = document.createElement('textarea')
  area.value = value
  area.setAttribute('readonly', '')
  area.style.position = 'fixed'
  area.style.opacity = '0'
  document.body.appendChild(area)
  area.select()
  const ok = document.execCommand('copy')
  area.remove()
  return ok
}

export function createActions(store: Store, live: () => HTMLElement | null): Actions {
  const announce = (message: string) => {
    const region = live()
    if (!region) return
    region.textContent = ''
    window.setTimeout(() => {
      region.textContent = message
    }, 40)
  }

  return {
    openPalette: (query = '') => store.set({ palette: { open: true, query }, sidebar: false }),
    closePalette: () => store.set((state) => ({ palette: { ...state.palette, open: false } })),
    openAsk: (question = null) =>
      store.set((state) => ({ ask: { open: true, question, nonce: state.ask.nonce + 1 }, palette: { ...state.palette, open: false }, sidebar: false })),
    closeAsk: () => store.set((state) => ({ ask: { ...state.ask, open: false, question: null } })),
    setShortcuts: (open) => store.set({ shortcuts: open }),
    setSidebar: (open) => store.set({ sidebar: open }),
    setTheme(preference) {
      const mode = applyTheme(preference)
      store.set({ theme: { preference, mode } })
    },
    copyText: (text) => writeClipboard(text),
    copyMarkdown() {
      const { page } = store.get()
      const markdown = fetch(page.markdown_url, { headers: { Accept: 'text/markdown' }, credentials: 'same-origin' }).then((response) => {
        if (!response.ok) throw new Error(`Markdown request failed with ${response.status}`)
        return response.text()
      })
      return writeClipboard(markdown)
    },
    copyMcpCommand() {
      const command = mcpCommand(store.get().boot)
      return command ? writeClipboard(command) : Promise.resolve(false)
    },
    announce,
  }
}

/** A flag that turns on for a moment after a successful action ("Copied", 1.2 s). */
export function useFlash(duration = 1200): [boolean, () => void] {
  const [on, setOn] = useState(false)
  const timer = useRef<number | null>(null)

  useEffect(() => () => {
    if (timer.current !== null) window.clearTimeout(timer.current)
  }, [])

  const trigger = useCallback(() => {
    setOn(true)
    if (timer.current !== null) window.clearTimeout(timer.current)
    timer.current = window.setTimeout(() => setOn(false), duration)
  }, [duration])

  return [on, trigger]
}
