import { createTranslator, LaRecipeContext, Slot, type LaRecipeContextValue } from '@larecipe/ui'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { Enhancements } from '../islands/Enhancements'
import { Overlays } from '../overlays/Overlays'
import { AskBar } from '../regions/AskBar'
import { BackToTop } from '../regions/BackToTop'
import { Header } from '../regions/Header'
import { PageHeader } from '../regions/PageHeader'
import { Footer, PageFooter } from '../regions/PageFooter'
import { Rail } from '../regions/Rail'
import { Sidebar } from '../regions/Sidebar'
import { ArticleHost } from './ArticleHost'
import { AppContext, askEnabled, type AppServices } from './context'
import { Insert } from './Insert'
import { IslandsHost } from './Islands'
import { installShortcuts } from './shortcuts'
import { markSeen, seenKey } from './seen'
import { useStoreState } from './store'
import { watchSystem } from './theme'

/**
 * The reading experience. Every region renders the same elements and class
 * names as the Blade prerender (resources/views/prerender), so React takes
 * over the server page in one commit without moving anything. Each region is
 * a named slot an extension can replace (ARCHITECTURE.md 8, 9.6).
 */
export function App({ services, article }: { services: AppServices; article: HTMLElement | null }) {
  const { store, router, actions } = services
  const boot = useStoreState(store, (state) => state.boot)
  const page = useStoreState(store, (state) => state.page)
  const theme = useStoreState(store, (state) => state.theme)
  const articleState = useStoreState(store, (state) => state.article)
  const [articleEl, setArticleEl] = useState<HTMLElement | null>(null)

  const t = useMemo(() => createTranslator(boot.strings), [boot.strings])
  const navigate = useCallback((url: string) => void router.navigate(url), [router])

  const context = useMemo<LaRecipeContextValue>(
    () => ({
      boot,
      page: { ...page, version: boot.version, locale: boot.locale.code },
      set: boot.set,
      theme,
      user: boot.user,
      t,
      locale: boot.locale,
      version: boot.version,
      navigate,
    }),
    [boot, page, theme, t, navigate],
  )

  useEffect(() => router.install(), [router])

  useEffect(
    () =>
      installShortcuts({
        palette: () => (store.get().palette.open ? actions.closePalette() : actions.openPalette()),
        ask: () => (store.get().ask.open ? actions.closeAsk() : actions.openAsk()),
        help: () => actions.setShortcuts(!store.get().shortcuts),
        askEnabled: () => askEnabled(store.get().boot),
      }),
    [store, actions],
  )

  // Follow the system while the reader's preference is "system".
  useEffect(
    () =>
      watchSystem((mode) => {
        if (store.get().theme.preference !== 'system') return
        document.documentElement.setAttribute('data-theme', mode)
        store.set({ theme: { preference: 'system', mode } })
      }),
    [store],
  )

  // Opening a recently changed page clears its fresh dot in the sidebar.
  useEffect(() => {
    if (page.fresh && page.updated_at) markSeen(seenKey(page.path_url, page.updated_at))
  }, [page.fresh, page.updated_at, page.path_url])

  // The layout attribute lives on the root element React does not render.
  useEffect(() => {
    document.getElementById('larecipe')?.setAttribute('data-layout', page.layout)
  }, [page.layout])

  return (
    <AppContext.Provider value={services}>
      <LaRecipeContext.Provider value={context}>
        <Slot name="Header" component={Header} />
        <div className="lr-shell">
          <Slot name="Sidebar" component={Sidebar} />
          <main className="lr-main" id="lr-main">
            <Slot name="PageHeader" component={PageHeader} />
            <Insert point="article.before" />
            <ArticleHost key={articleState.key} node={articleState.html === null ? article : null} html={articleState.html} onMount={setArticleEl} />
            <Insert point="article.after" />
            <Slot name="PageFooter" component={PageFooter} />
            {askEnabled(boot) && <AskBar />}
          </main>
          <Rail />
        </div>
        <Slot name="Footer" component={Footer} />
        <BackToTop />
        <IslandsHost root={articleEl} />
        <Enhancements root={articleEl} />
        <Overlays />
        <div className="lr-live" role="status" aria-live="polite" id="lr-live" />
      </LaRecipeContext.Provider>
    </AppContext.Provider>
  )
}
