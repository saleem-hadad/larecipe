import type { PageJson } from '@larecipe/ui'
import { flushSync } from 'react-dom'
import { updateHead } from './head'
import type { Store } from './store'

/**
 * Client-side navigation (ARCHITECTURE.md 7.5).
 *
 * Same-origin docs links are intercepted, the page's JSON representation is
 * fetched, and only the article and what surrounds it are swapped, inside a
 * View Transition when motion is allowed. The head is updated, focus moves to
 * the new title and scroll is restored on back and forward. Links prefetch on
 * hover or focus intent; payloads are cached per URL. Anything unexpected (an
 * error, another doc set, another locale, a new UI build) falls back to a full
 * page load, so the server always has the last word.
 */
export const PAGE_JSON_TYPE = 'application/vnd.larecipe.page+json'

/** Paths inside a set that are files or endpoints, never pages. */
const NON_PAGE = /(\.(md|txt|json|xml|png|jpe?g|gif|svg|webp|avif|ico|pdf|zip|csv|mp4|webm|mp3|woff2?))$|\/_[a-z]+(\/|$)/i

const INTENT_DELAY = 65
const CACHE_LIMIT = 40

export type NavigateResult = 'swapped' | 'full' | 'ignored'

export interface NavigateOptions {
  /** Replace the current history entry instead of pushing one. */
  replace?: boolean
  /** Scroll position to restore (back and forward). Null scrolls to the hash or the top. */
  scroll?: number | null
  /** The navigation came from popstate: never push history. */
  pop?: boolean
}

export interface RouterDeps {
  store: Store
  fetch?: typeof fetch
  win?: Window & typeof globalThis
  /** Runs the DOM swap; the default wraps it in a View Transition when motion is allowed. */
  transition?: (update: () => void) => Promise<void>
  /** A full page load; replaced in tests. */
  fullLoad?: (url: string) => void
}

export interface Router {
  isDocsUrl(url: URL): boolean
  navigate(href: string, options?: NavigateOptions): Promise<NavigateResult>
  prefetch(href: string): void
  load(href: string): Promise<PageJson | null>
  install(): () => void
}

export function createRouter({ store, fetch: fetcher, win = window, transition = viewTransition, fullLoad }: RouterDeps): Router {
  const cache = new Map<string, Promise<PageJson | null>>()
  const doFetch = fetcher ?? ((input: RequestInfo | URL, init?: RequestInit) => win.fetch(input, init))
  const loadFully = fullLoad ?? ((url: string) => win.location.assign(url))
  let current = win.location.pathname + win.location.search
  let pending: string | null = null

  /** A static export (larecipe:export) has no server to answer JSON: links load pages normally. */
  function isStatic(): boolean {
    return win.document.documentElement.hasAttribute('data-lr-static')
  }

  function basePath(): string {
    return store.get().boot.set.base_path.replace(/\/$/, '')
  }

  function isDocsUrl(url: URL): boolean {
    if (url.origin !== win.location.origin) return false
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return false
    const base = basePath()
    if (base !== '' && url.pathname !== base && !url.pathname.startsWith(`${base}/`)) return false
    const mcp = store.get().boot.urls.mcp
    if (mcp && url.pathname === new URL(mcp, win.location.href).pathname) return false
    return !NON_PAGE.test(url.pathname)
  }

  function key(url: URL): string {
    return url.pathname + url.search
  }

  function load(href: string): Promise<PageJson | null> {
    const url = new URL(href, win.location.href)
    const cacheKey = key(url)
    const cached = cache.get(cacheKey)
    if (cached) return cached

    const request = doFetch(cacheKey, {
      headers: { Accept: PAGE_JSON_TYPE, 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(async (response) => {
        if (!response.ok || !(response.headers.get('Content-Type') ?? '').includes('json')) return null
        const json = (await response.json()) as PageJson
        const build = response.headers.get('X-LaRecipe-Build') ?? json.build
        return { ...json, build }
      })
      .catch(() => null)
      .then((json) => {
        if (json === null) cache.delete(cacheKey)
        return json
      })

    cache.set(cacheKey, request)
    if (cache.size > CACHE_LIMIT) {
      const oldest = cache.keys().next().value
      if (oldest !== undefined) cache.delete(oldest)
    }
    return request
  }

  function prefetch(href: string): void {
    if (isStatic()) return
    try {
      const url = new URL(href, win.location.href)
      if (isDocsUrl(url) && key(url) !== current) void load(url.href)
    } catch {
      // Not a URL: nothing to prefetch.
    }
  }

  function saveScroll(): void {
    try {
      win.history.replaceState({ ...(win.history.state ?? {}), lr: true, scroll: win.scrollY }, '')
    } catch {
      // Some embedded browsers refuse replaceState; scroll restoration is a nicety.
    }
  }

  async function navigate(href: string, options: NavigateOptions = {}): Promise<NavigateResult> {
    const url = new URL(href, win.location.href)
    if (!isDocsUrl(url) || isStatic()) {
      loadFully(url.href)
      return 'full'
    }

    if (key(url) === current && !options.pop) {
      // Same page: only the hash changes, which the browser handles.
      if (url.hash && url.hash !== win.location.hash) win.history.pushState({ lr: true, scroll: null }, '', url.href)
      scrollToHash(url.hash)
      return 'ignored'
    }

    const state = store.get()
    pending = url.href
    store.set({ navigating: url.href })

    const json = await load(url.href)
    if (pending !== url.href) return 'ignored'
    pending = null

    if (json === null || json.build !== state.boot.build || json.set.key !== state.boot.set.key || json.locale.code !== state.boot.locale.code) {
      store.set({ navigating: null })
      loadFully(url.href)
      return 'full'
    }

    if (!options.pop) {
      saveScroll()
      const method = options.replace ? 'replaceState' : 'pushState'
      win.history[method]({ lr: true, scroll: null }, '', url.pathname + url.search + url.hash)
    }
    current = key(url)

    await transition(() => {
      flushSync(() => {
        const now = store.get()
        store.set({
          page: json.page,
          boot: {
            ...now.boot,
            page: json.page,
            navigation: json.navigation,
            set: json.set,
            urls: json.urls,
            version: json.version,
            locale: json.locale,
          },
          article: { key: now.article.key + 1, html: json.page.html },
          insertsHtml: json.inserts_html ?? now.insertsHtml,
          navigating: null,
          sidebar: false,
          navigated: true,
        })
      })
      updateHead(json, win.document)
      if (typeof options.scroll === 'number') {
        win.scrollTo(0, options.scroll)
      } else if (url.hash) {
        scrollToHash(url.hash)
      } else {
        win.scrollTo(0, 0)
      }
    })

    focusTitle()
    prefetchNeighbours()
    return 'swapped'
  }

  function scrollToHash(hash: string): void {
    if (!hash) return
    const id = decodeURIComponent(hash.slice(1))
    const target = win.document.getElementById(id)
    target?.scrollIntoView({ block: 'start' })
  }

  function focusTitle(): void {
    const title = win.document.querySelector<HTMLElement>('#larecipe .lr-title')
    if (title) {
      title.setAttribute('tabindex', '-1')
      title.focus({ preventScroll: true })
    }
  }

  function prefetchNeighbours(): void {
    const { page } = store.get()
    const idle = (win as Window & { requestIdleCallback?: (cb: () => void) => number }).requestIdleCallback ?? ((cb: () => void) => win.setTimeout(cb, 300))
    idle(() => {
      if (page.next) prefetch(page.next.url)
      if (page.previous) prefetch(page.previous.url)
    })
  }

  function linkFrom(event: Event): HTMLAnchorElement | null {
    const target = event.target
    if (!(target instanceof Element)) return null
    const anchor = target.closest('a[href]')
    return anchor instanceof HTMLAnchorElement ? anchor : null
  }

  function eligible(anchor: HTMLAnchorElement): URL | null {
    if (anchor.target && anchor.target !== '_self') return null
    if (anchor.hasAttribute('download') || anchor.dataset.lrReload !== undefined) return null
    if ((anchor.getAttribute('rel') ?? '').split(/\s+/).includes('external')) return null
    try {
      const url = new URL(anchor.href, win.location.href)
      return isDocsUrl(url) ? url : null
    } catch {
      return null
    }
  }

  function install(): () => void {
    const doc = win.document
    if (isStatic()) return () => {}
    try {
      win.history.scrollRestoration = 'manual'
    } catch {
      // Read-only in some environments.
    }
    saveScroll()

    const onClick = (event: MouseEvent) => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
      const anchor = linkFrom(event)
      if (!anchor) return
      const url = eligible(anchor)
      if (!url) return
      if (key(url) === current && url.hash) return
      event.preventDefault()
      void navigate(url.href)
    }

    let intent: number | null = null
    const onPointerOver = (event: Event) => {
      const anchor = linkFrom(event)
      if (!anchor) return
      const url = eligible(anchor)
      if (!url) return
      if (intent !== null) win.clearTimeout(intent)
      intent = win.setTimeout(() => prefetch(url.href), INTENT_DELAY)
    }
    const onPointerOut = () => {
      if (intent !== null) win.clearTimeout(intent)
      intent = null
    }
    const onFocusIn = (event: Event) => {
      const anchor = linkFrom(event)
      const url = anchor ? eligible(anchor) : null
      if (url) prefetch(url.href)
    }
    const onPopState = (event: PopStateEvent) => {
      const state = event.state as { lr?: boolean; scroll?: number | null } | null
      const target = win.location.pathname + win.location.search
      if (target === current) {
        if (typeof state?.scroll === 'number') win.scrollTo(0, state.scroll)
        return
      }
      void navigate(win.location.href, { pop: true, scroll: typeof state?.scroll === 'number' ? state.scroll : null })
    }

    let scrollTimer: number | null = null
    const onScroll = () => {
      if (scrollTimer !== null) win.clearTimeout(scrollTimer)
      scrollTimer = win.setTimeout(saveScroll, 150)
    }

    doc.addEventListener('click', onClick)
    doc.addEventListener('pointerover', onPointerOver, { passive: true })
    doc.addEventListener('pointerout', onPointerOut, { passive: true })
    doc.addEventListener('focusin', onFocusIn)
    doc.addEventListener('touchstart', onFocusIn, { passive: true })
    win.addEventListener('popstate', onPopState)
    win.addEventListener('scroll', onScroll, { passive: true })
    win.addEventListener('pagehide', saveScroll)

    prefetchNeighbours()

    return () => {
      doc.removeEventListener('click', onClick)
      doc.removeEventListener('pointerover', onPointerOver)
      doc.removeEventListener('pointerout', onPointerOut)
      doc.removeEventListener('focusin', onFocusIn)
      doc.removeEventListener('touchstart', onFocusIn)
      win.removeEventListener('popstate', onPopState)
      win.removeEventListener('scroll', onScroll)
      win.removeEventListener('pagehide', saveScroll)
    }
  }

  return { isDocsUrl, navigate, prefetch, load, install }
}

type TransitionDocument = Document & {
  startViewTransition?: (update: () => void) => { updateCallbackDone: Promise<void> }
}

/** The swap: a short crossfade in a still frame, or an instant change when motion is reduced. */
export async function viewTransition(update: () => void): Promise<void> {
  const doc = document as TransitionDocument
  const reduce = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

  if (typeof doc.startViewTransition !== 'function' || reduce) {
    update()
    return
  }

  try {
    await doc.startViewTransition(update).updateCallbackDone
  } catch {
    // A skipped transition still ran the update.
  }
}
