import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { createApi, type Api, type Scope } from './api'
import type { Route } from './router'
import type { Boot } from './types'

export interface Notice {
  id: number
  tone: 'success' | 'warning' | 'danger' | 'info'
  message: string
}

export interface AdminContextValue {
  boot: Boot
  api: Api
  scope: Scope
  setScope: (scope: Scope) => void
  /** The writing direction of the locale being edited: Arabic pages are edited and previewed right to left. */
  contentDir: 'ltr' | 'rtl'
  navigate: (to: Route | string, options?: { replace?: boolean; search?: string }) => void
  href: (route: Route) => string
  notify: (message: string, tone?: Notice['tone']) => void
  /** Bumped after any write, so lists and counts that depend on content reload. */
  revision: number
  changed: () => void
  /** Pending conflicts and drafts, for the badge on the pass. */
  pending: number
  setPending: (count: number) => void
}

const AdminContext = createContext<AdminContextValue | null>(null)

export function useAdmin(): AdminContextValue {
  const value = useContext(AdminContext)
  if (!value) throw new Error('useAdmin() is used outside the admin app.')
  return value
}

function scopeFromSearch(boot: Boot, search: string): Scope {
  const params = new URLSearchParams(search)
  const version = params.get('version')
  const locale = params.get('locale')
  const defaultVersion = boot.versions.find((v) => v.default)?.id ?? boot.versions[0]?.id ?? 'current'
  const defaultLocale = boot.locales.find((l) => l.default)?.code ?? boot.locales[0]?.code ?? 'en'

  return {
    version: version && boot.versions.some((v) => v.id === version) ? version : defaultVersion,
    locale: locale && boot.locales.some((l) => l.code === locale) ? locale : defaultLocale,
  }
}

export function AdminProvider({
  boot,
  search,
  navigate,
  href,
  children,
  notices,
}: {
  boot: Boot
  search: string
  navigate: AdminContextValue['navigate']
  href: AdminContextValue['href']
  children: ReactNode
  notices: { push: (notice: Omit<Notice, 'id'>) => void }
}) {
  const api = useMemo(() => createApi(boot.urls.api), [boot.urls.api])
  const scope = useMemo(() => scopeFromSearch(boot, search), [boot, search])
  const [revision, setRevision] = useState(0)
  const [pending, setPending] = useState(0)

  const setScope = useCallback(
    (next: Scope) => {
      const params = new URLSearchParams(window.location.search)
      const defaults = scopeFromSearch(boot, '')
      next.version === defaults.version ? params.delete('version') : params.set('version', next.version)
      next.locale === defaults.locale ? params.delete('locale') : params.set('locale', next.locale)
      const query = params.toString()
      navigate(window.location.pathname, { search: query ? `?${query}` : '', replace: true })
    },
    [boot, navigate],
  )

  const contentDir = boot.locales.find((locale) => locale.code === scope.locale)?.dir ?? 'ltr'
  const notify = useCallback((message: string, tone: Notice['tone'] = 'success') => notices.push({ message, tone }), [notices])
  const changed = useCallback(() => setRevision((value) => value + 1), [])

  const value = useMemo<AdminContextValue>(
    () => ({ boot, api, scope, setScope, contentDir, navigate, href, notify, revision, changed, pending, setPending }),
    [boot, api, scope, setScope, contentDir, navigate, href, notify, revision, changed, pending],
  )

  return <AdminContext.Provider value={value}>{children}</AdminContext.Provider>
}

/** Notices that announce themselves (role=status) and leave after a few seconds. */
export function useNotices() {
  const [notices, setNotices] = useState<Notice[]>([])
  const counter = useRef(0)

  const push = useCallback((notice: Omit<Notice, 'id'>) => {
    const id = ++counter.current
    setNotices((list) => [...list.slice(-3), { ...notice, id }])
    window.setTimeout(() => setNotices((list) => list.filter((item) => item.id !== id)), notice.tone === 'danger' ? 9000 : 5000)
  }, [])

  const dismiss = useCallback((id: number) => setNotices((list) => list.filter((item) => item.id !== id)), [])

  return useMemo(() => ({ notices, push, dismiss }), [notices, push, dismiss])
}

/**
 * Load something from the API when its inputs change, with the states every
 * screen needs: loading, the data, an error to show, and a reload.
 */
export function useLoad<T>(load: (signal: { cancelled: boolean }) => Promise<{ ok: true; data: T } | { ok: false; message: string }>, deps: unknown[]) {
  const [state, setState] = useState<{ loading: boolean; data: T | null; error: string | null }>({ loading: true, data: null, error: null })
  const [tick, setTick] = useState(0)

  useEffect(() => {
    const signal = { cancelled: false }
    setState((previous) => ({ ...previous, loading: true, error: null }))

    load(signal)
      .then((result) => {
        if (signal.cancelled) return
        setState(result.ok ? { loading: false, data: result.data, error: null } : { loading: false, data: null, error: result.message })
      })
      .catch((error: unknown) => {
        if (!signal.cancelled) setState({ loading: false, data: null, error: error instanceof Error ? error.message : 'The request failed.' })
      })

    return () => {
      signal.cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, tick])

  const reload = useCallback(() => setTick((value) => value + 1), [])

  return { ...state, reload, setData: (data: T) => setState({ loading: false, data, error: null }) }
}
