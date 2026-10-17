import { createContext, useContext } from 'react'
import type { Translate } from './strings'
import type { BootPayload, PagePayload, SetPayload, ThemePreference } from './types'

export interface LaRecipeContextValue {
  /** The full boot payload for the current page (navigation, features, URLs). */
  boot: BootPayload
  /** The current page, with its version and locale. Updates on client navigation. */
  page: PagePayload & { version: string; locale: string }
  /** The doc set: key, name, versions, locales. */
  set: SetPayload
  /** The resolved theme and the reader's preference. Tokens are CSS variables (--lr-*). */
  theme: { mode: 'light' | 'dark'; preference: ThemePreference }
  /** The signed-in user, only when the host shares fields with LaRecipe::shareUser(). */
  user: BootPayload['user']
  /** UI strings in the page's locale. */
  t: Translate
  locale: { code: string; dir: 'ltr' | 'rtl' }
  version: string
  /** Navigate to a docs URL with client-side navigation (full load for anything else). */
  navigate: (url: string) => void
}

export const LaRecipeContext = createContext<LaRecipeContextValue | null>(null)

/** The current page, set, theme and (when the host shares one) the signed-in user. */
export function useLaRecipe(): LaRecipeContextValue {
  const value = useContext(LaRecipeContext)
  if (!value) {
    throw new Error('useLaRecipe() must be used inside a LaRecipe page.')
  }
  return value
}
