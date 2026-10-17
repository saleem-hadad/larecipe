import { useCallback, useEffect, useState } from 'react'

/**
 * The admin's screens, addressed by URL under /{prefix}/_admin so every view
 * can be linked, reloaded and opened in a new tab. The server answers every
 * admin URL with the same shell; this decides what to draw.
 */
export type Route =
  | { name: 'overview' }
  | { name: 'pages' }
  | { name: 'page'; path: string }
  | { name: 'new' }
  | { name: 'review' }
  | { name: 'ticket'; id: string }
  | { name: 'activity' }
  | { name: 'revision'; id: string }
  | { name: 'settings' }
  | { name: 'assets' }
  | { name: 'missing'; path: string }

export function parseRoute(pathname: string, base: string): Route {
  const relative = pathname.startsWith(base) ? pathname.slice(base.length) : pathname
  const parts = relative.split('/').filter(Boolean).map(decodeURIComponent)
  const [head, ...rest] = parts

  switch (head) {
    case undefined:
      return { name: 'overview' }
    case 'pages':
      return rest.length > 0 ? { name: 'page', path: rest.join('/') } : { name: 'pages' }
    case 'new':
      return { name: 'new' }
    case 'review':
      return rest[0] ? { name: 'ticket', id: rest[0] } : { name: 'review' }
    case 'activity':
      return { name: 'activity' }
    case 'revisions':
      return rest[0] ? { name: 'revision', id: rest[0] } : { name: 'activity' }
    case 'settings':
      return { name: 'settings' }
    case 'assets':
      return { name: 'assets' }
    default:
      return { name: 'missing', path: relative }
  }
}

export function routePath(route: Route): string {
  switch (route.name) {
    case 'overview':
      return ''
    case 'page':
      return `/pages/${route.path.split('/').map(encodeURIComponent).join('/')}`
    case 'ticket':
      return `/review/${route.id}`
    case 'revision':
      return `/revisions/${route.id}`
    case 'missing':
      return route.path
    default:
      return `/${route.name}`
  }
}

/** Current route plus a navigate() that keeps the query (version and locale) unless told otherwise. */
export function useRouter(base: string) {
  const [location, setLocation] = useState(() => ({ pathname: window.location.pathname, search: window.location.search }))

  useEffect(() => {
    const onPop = () => setLocation({ pathname: window.location.pathname, search: window.location.search })
    window.addEventListener('popstate', onPop)
    return () => window.removeEventListener('popstate', onPop)
  }, [])

  const navigate = useCallback(
    (to: Route | string, options: { replace?: boolean; search?: string } = {}) => {
      const pathname = typeof to === 'string' ? to : base + routePath(to)
      const search = options.search ?? window.location.search
      const url = pathname + search

      if (url !== window.location.pathname + window.location.search) {
        window.history[options.replace ? 'replaceState' : 'pushState'](null, '', url)
      }

      setLocation({ pathname, search })
      window.scrollTo?.({ top: 0 })
    },
    [base],
  )

  return { route: parseRoute(location.pathname, base), search: location.search, navigate, href: (route: Route) => base + routePath(route) + location.search }
}
