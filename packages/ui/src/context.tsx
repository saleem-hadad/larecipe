import { createContext, useContext } from 'react'
import type { BootPayload } from './types'

export interface LaRecipeContextValue {
  boot: BootPayload
  page: BootPayload['page']
  set: BootPayload['set']
  theme: { mode: 'light' | 'dark' }
  user: BootPayload['user']
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
