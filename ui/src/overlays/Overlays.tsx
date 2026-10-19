import { useLaRecipe } from '@larecipe/ui'
import { lazy, Suspense, useEffect, useState } from 'react'
import { askEnabled, useAppState } from '../app/context'
import { Shortcuts } from './Shortcuts'

/** The palette and the Ask panel are lazy chunks: nothing loads until the reader asks. */
export const loadPalette = () => import('../palette/CommandPalette')
export const loadAsk = () => import('../ask/AskPanel')

const CommandPalette = lazy(loadPalette)
const AskPanel = lazy(loadAsk)

/**
 * Loads the palette chunk and warms the search index when the browser is idle
 * after the reader's first interaction (ARCHITECTURE.md 10.2), so ⌘K opens
 * with results ready.
 */
export function useWarmSearch(): void {
  const { boot } = useLaRecipe()

  useEffect(() => {
    let done = false
    const idle = (callback: () => void) =>
      (window as Window & { requestIdleCallback?: (cb: () => void, options?: { timeout: number }) => number }).requestIdleCallback?.(callback, { timeout: 2000 }) ??
      window.setTimeout(callback, 600)

    const warm = () => {
      if (done) return
      done = true
      remove()
      idle(() => {
        void loadPalette().then((module) => module.warm(boot))
        if (askEnabled(boot)) void loadAsk()
      })
    }
    const events = ['pointerdown', 'keydown', 'scroll', 'touchstart'] as const
    const remove = () => events.forEach((name) => window.removeEventListener(name, warm))
    events.forEach((name) => window.addEventListener(name, warm, { passive: true, once: true }))
    return remove
  }, [boot])
}

export function Overlays() {
  const { boot } = useLaRecipe()
  const paletteOpen = useAppState((state) => state.palette.open)
  const askOpen = useAppState((state) => state.ask.open)
  const [paletteMounted, setPaletteMounted] = useState(false)
  const [askMounted, setAskMounted] = useState(false)
  useWarmSearch()

  // Ask answers ⌘I instantly: its chunk loads once the page is idle.
  const ask = askEnabled(boot)
  useEffect(() => {
    if (!ask) return
    const handle = window.setTimeout(() => void loadAsk(), 1500)
    return () => window.clearTimeout(handle)
  }, [ask])

  useEffect(() => {
    if (paletteOpen) setPaletteMounted(true)
  }, [paletteOpen])

  useEffect(() => {
    if (askOpen) setAskMounted(true)
  }, [askOpen])

  return (
    <>
      <Shortcuts />
      {(paletteMounted || paletteOpen) && (
        <Suspense fallback={null}>
          <CommandPalette />
        </Suspense>
      )}
      {askEnabled(boot) && (askMounted || askOpen) && (
        <Suspense fallback={null}>
          <AskPanel />
        </Suspense>
      )}
    </>
  )
}
