/**
 * Whether the reader hid the navigation on wide screens (the switch next to
 * the logo), remembered per reader. The inline head script applies it before
 * first paint (resources/views/partials/head.blade.php), so the page never
 * shows the sidebar and then removes it. Phones keep their sheet either way.
 *
 * Hiding and showing run in two steps so nothing reflows mid-animation: the
 * sidebar slides out at its full width ("hiding"), then the layout changes
 * once and the article widens ("hidden"). Showing changes the layout first,
 * then slides the sidebar in ("showing").
 */
export const SIDEBAR_KEY = 'larecipe:sidebar'

type SidebarState = 'hiding' | 'hidden' | 'showing'

let timer: ReturnType<typeof setTimeout> | null = null

export function sidebarHidden(): boolean {
  const state = document.documentElement.getAttribute('data-lr-sidebar')
  return state === 'hidden' || state === 'hiding'
}

/** The panel duration from the design tokens (0 when the reader prefers reduced motion). */
function panelDuration(): number {
  const value = getComputedStyle(document.documentElement).getPropertyValue('--lr-duration-panel').trim()
  const ms = value.endsWith('ms') ? parseFloat(value) : value.endsWith('s') ? parseFloat(value) * 1000 : NaN
  return Number.isFinite(ms) ? ms : 240
}

function setState(state: SidebarState | null): void {
  const root = document.documentElement
  if (state === null) root.removeAttribute('data-lr-sidebar')
  else root.setAttribute('data-lr-sidebar', state)
}

/** Apply and remember the reader's choice, animating unless motion is reduced. */
export function applySidebar(hidden: boolean): void {
  if (timer !== null) clearTimeout(timer)
  timer = null
  const duration = panelDuration()

  if (duration <= 0) {
    setState(hidden ? 'hidden' : null)
  } else {
    setState(hidden ? 'hiding' : 'showing')
    timer = setTimeout(() => {
      timer = null
      setState(hidden ? 'hidden' : null)
    }, duration)
  }

  try {
    window.localStorage.setItem(SIDEBAR_KEY, hidden ? 'hidden' : 'shown')
  } catch {
    // Private windows may refuse storage; the choice still applies to this page.
  }
}
