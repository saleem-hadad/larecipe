/**
 * Whether the reader hid the navigation on wide screens (the switch next to
 * the logo), remembered per reader. The inline head script applies it before
 * first paint (resources/views/partials/head.blade.php), so the page never
 * shows the sidebar and then removes it. Phones keep their sheet either way.
 */
export const SIDEBAR_KEY = 'larecipe:sidebar'

export function sidebarHidden(): boolean {
  return document.documentElement.getAttribute('data-lr-sidebar') === 'hidden'
}

/** Apply and remember the reader's choice. */
export function applySidebar(hidden: boolean): void {
  const root = document.documentElement
  if (hidden) root.setAttribute('data-lr-sidebar', 'hidden')
  else root.removeAttribute('data-lr-sidebar')

  try {
    window.localStorage.setItem(SIDEBAR_KEY, hidden ? 'hidden' : 'shown')
  } catch {
    // Private windows may refuse storage; the choice still applies to this page.
  }
}
