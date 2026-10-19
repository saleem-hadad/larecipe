/**
 * The fresh dots a reader has already acted on. A sidebar dot marks a page
 * changed within larecipe.ui.fresh_days; once the reader opens that version of
 * the page, its dot goes away until the page changes again.
 *
 * Each opened page is remembered as "url|updated_at" in localStorage. A
 * constructed stylesheet hides the dots with those keys (data-lr-seen): the
 * inline head script builds it before first paint, so the prerender never
 * shows a dot and then drops it, and this module keeps it current. Stylesheets
 * built through CSSOM are allowed under a nonce-based Content Security Policy.
 */
export const SEEN_KEY = 'larecipe:seen'

/** How many opened pages to remember; the oldest go first. */
const LIMIT = 200

declare global {
  interface Window {
    __lrSeen?: CSSStyleSheet
  }
}

export function seenKey(url: string, updatedAt: string): string {
  return `${url}|${updatedAt}`
}

export function readSeen(): string[] {
  try {
    const keys: unknown = JSON.parse(window.localStorage.getItem(SEEN_KEY) ?? '[]')
    return Array.isArray(keys) ? keys.filter((key): key is string => typeof key === 'string') : []
  } catch {
    return []
  }
}

/** The rule the head script writes: every remembered dot, hidden. */
export function seenRule(keys: string[]): string {
  return keys.length === 0 ? '' : `${keys.map((key) => `[data-lr-seen="${key.replace(/["\\]/g, '\\$&')}"]`).join(',')}{display:none}`
}

function apply(keys: string[]): void {
  if (typeof CSSStyleSheet === 'undefined' || !('adoptedStyleSheets' in document)) return
  try {
    if (!window.__lrSeen) {
      window.__lrSeen = new CSSStyleSheet()
      document.adoptedStyleSheets = [...document.adoptedStyleSheets, window.__lrSeen]
    }
    window.__lrSeen.replaceSync(seenRule(keys))
  } catch {
    // An engine without constructable stylesheets keeps the dots; nothing breaks.
  }
}

/** Remember that the reader opened this version of a page, and hide its dot everywhere. */
export function markSeen(key: string): void {
  const keys = [...readSeen().filter((seen) => seen !== key), key].slice(-LIMIT)
  try {
    window.localStorage.setItem(SEEN_KEY, JSON.stringify(keys))
  } catch {
    // Private windows may refuse storage; the dot still hides for this visit.
  }
  apply(keys)
}
