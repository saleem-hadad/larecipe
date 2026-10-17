import type { ThemePreference } from '@larecipe/ui'

/**
 * Light, dark or system, remembered per reader. The inline head script applies
 * the same rule before first paint (resources/views/partials/head.blade.php),
 * so the page never flashes the wrong theme.
 */
export const THEME_KEY = 'larecipe:theme'

const preferences: ThemePreference[] = ['light', 'dark', 'system']

function storage(): Storage | null {
  try {
    return window.localStorage
  } catch {
    return null
  }
}

/** The reader's stored choice, else the set's default mode. */
export function readPreference(fallback: ThemePreference = 'system'): ThemePreference {
  const stored = storage()?.getItem(THEME_KEY)
  return preferences.includes(stored as ThemePreference) ? (stored as ThemePreference) : fallback
}

export function systemMode(): 'light' | 'dark' {
  return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export function resolveMode(preference: ThemePreference): 'light' | 'dark' {
  return preference === 'system' ? systemMode() : preference
}

/** Apply and remember a preference. Returns the resolved mode. */
export function applyTheme(preference: ThemePreference, remember = true): 'light' | 'dark' {
  const mode = resolveMode(preference)
  document.documentElement.setAttribute('data-theme', mode)

  if (remember) {
    try {
      storage()?.setItem(THEME_KEY, preference)
    } catch {
      // Private windows may refuse storage; the choice still applies to this page.
    }
  }

  return mode
}

/** Follow the system while the preference is "system". */
export function watchSystem(onChange: (mode: 'light' | 'dark') => void): () => void {
  if (typeof window.matchMedia !== 'function') return () => {}
  const query = window.matchMedia('(prefers-color-scheme: dark)')
  const listener = () => onChange(query.matches ? 'dark' : 'light')
  query.addEventListener('change', listener)
  return () => query.removeEventListener('change', listener)
}

export function nextPreference(current: ThemePreference): ThemePreference {
  return preferences[(preferences.indexOf(current) + 1) % preferences.length] ?? 'system'
}
