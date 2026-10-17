/**
 * Global keyboard shortcuts: ⌘K and / open search, ⌘I opens Ask, ? shows the
 * shortcuts. Single-key shortcuts never fire while the reader is typing.
 */
export interface ShortcutHandlers {
  palette(): void
  ask(): void
  help(): void
  askEnabled(): boolean
}

export function isTyping(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false
  if (target.isContentEditable) return true
  const tag = target.tagName
  if (tag === 'TEXTAREA' || tag === 'SELECT') return true
  if (tag !== 'INPUT') return false
  const type = (target as HTMLInputElement).type
  return !['button', 'checkbox', 'radio', 'submit', 'reset', 'range', 'color', 'file'].includes(type)
}

export function shortcutFor(event: KeyboardEvent): 'palette' | 'ask' | 'help' | null {
  const mod = event.metaKey || event.ctrlKey
  const key = event.key.toLowerCase()

  if (mod && !event.altKey && !event.shiftKey && key === 'k') return 'palette'
  if (mod && !event.altKey && !event.shiftKey && key === 'i') return 'ask'
  if (mod || event.altKey || isTyping(event.target)) return null
  if (event.key === '/') return 'palette'
  if (event.key === '?') return 'help'
  return null
}

export function installShortcuts(handlers: ShortcutHandlers, doc: Document = document): () => void {
  const onKeyDown = (event: KeyboardEvent) => {
    if (event.defaultPrevented || event.isComposing) return
    const shortcut = shortcutFor(event)
    if (shortcut === null) return
    if (shortcut === 'ask' && !handlers.askEnabled()) return
    event.preventDefault()
    handlers[shortcut]()
  }

  doc.addEventListener('keydown', onKeyDown)
  return () => doc.removeEventListener('keydown', onKeyDown)
}
