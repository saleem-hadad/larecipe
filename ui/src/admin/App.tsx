import { Icon } from '@larecipe/ui'
import { useEffect, useState, type MouseEvent, type ReactNode } from 'react'
import { AdminIcon, type AdminIconName } from './components/AdminIcon'
import { AdminProvider, useAdmin, useNotices, type Notice } from './context'
import { useRouter, type Route } from './router'
import { Activity } from './screens/Activity'
import { Assets } from './screens/Assets'
import { NewPage } from './screens/NewPage'
import { Overview } from './screens/Overview'
import { PageEditor } from './screens/PageEditor'
import { Pages } from './screens/Pages'
import { Review } from './screens/Review'
import { RevisionScreen } from './screens/RevisionScreen'
import { Settings } from './screens/Settings'
import { Ticket } from './screens/Ticket'
import type { Boot } from './types'

const THEME_KEY = 'larecipe:theme'
type ThemePreference = 'light' | 'dark' | 'system'

function storedTheme(): ThemePreference {
  try {
    const value = window.localStorage.getItem(THEME_KEY)
    return value === 'light' || value === 'dark' ? value : 'system'
  } catch {
    return 'system'
  }
}

function applyTheme(preference: ThemePreference): void {
  const dark = preference === 'dark' || (preference === 'system' && typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches)
  document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light')
  try {
    window.localStorage.setItem(THEME_KEY, preference)
  } catch {
    // Storage may be refused; the choice still applies to this page.
  }
}

/**
 * The admin app: the header (set, version and locale, theme), the sidebar of
 * screens, and the screen for the current URL. Database sets get every
 * screen; a repository set gets the overview only, since its edits belong in git.
 */
export function AdminApp({ boot }: { boot: Boot }) {
  const router = useRouter(boot.urls.base)
  const notices = useNotices()

  return (
    <AdminProvider boot={boot} search={router.search} navigate={router.navigate} href={router.href} notices={notices}>
      <Shell route={router.route} notices={notices.notices} dismiss={notices.dismiss} />
    </AdminProvider>
  )
}

const SCREENS: { route: Route['name']; label: string; icon: AdminIconName; editOnly?: boolean }[] = [
  { route: 'overview', label: 'Overview', icon: 'Gauge' },
  { route: 'pages', label: 'Pages', icon: 'ListTree', editOnly: true },
  { route: 'review', label: 'The pass', icon: 'Inbox', editOnly: true },
  { route: 'activity', label: 'Activity', icon: 'Activity', editOnly: true },
  { route: 'assets', label: 'Assets', icon: 'Image', editOnly: true },
  { route: 'settings', label: 'Settings', icon: 'Settings', editOnly: true },
]

function sectionOf(route: Route): Route['name'] {
  switch (route.name) {
    case 'page':
    case 'new':
      return 'pages'
    case 'ticket':
      return 'review'
    case 'revision':
      return 'activity'
    default:
      return route.name
  }
}

function Shell({ route, notices, dismiss }: { route: Route; notices: Notice[]; dismiss: (id: number) => void }) {
  const { boot, scope, setScope, navigate, href, api, pending, setPending, revision } = useAdmin()
  const [theme, setTheme] = useState<ThemePreference>(storedTheme)
  const [menuOpen, setMenuOpen] = useState(false)
  const editable = boot.permissions.edit
  const section = sectionOf(route)

  useEffect(() => {
    if (!editable) return
    void api.review().then((result) => result.ok && setPending(result.data.conflicts.length + result.data.drafts.length))
  }, [api, editable, revision, setPending])

  useEffect(() => setMenuOpen(false), [route])

  const cycleTheme = () => {
    const next: ThemePreference = theme === 'light' ? 'dark' : theme === 'dark' ? 'system' : 'light'
    setTheme(next)
    applyTheme(next)
  }

  const go = (to: Route) => (event: MouseEvent) => {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return
    event.preventDefault()
    navigate(to)
  }

  return (
    <div className="lra-app">
      <a className="lr-skip" href="#lra-main">
        Skip to content
      </a>
      <header className="lra-header">
        <button type="button" className="lra-icon-button lra-menu-toggle" aria-label="Menu" aria-expanded={menuOpen} onClick={() => setMenuOpen(!menuOpen)}>
          <Icon name="Menu" size={18} />
        </button>
        <a className="lra-brand" href={href({ name: 'overview' })} onClick={go({ name: 'overview' })}>
          <span className="lra-brand-name">{boot.set.name}</span>
          <span className="lr-label lra-brand-label">Admin</span>
        </a>
        <span className="lra-grow" />
        {boot.versions.length > 1 && (
          <label className="lra-scope">
            <span className="lr-sr">Version</span>
            <select className="lr-input lra-select lra-select-sm" value={scope.version} onChange={(event) => setScope({ ...scope, version: event.target.value })}>
              {boot.versions.map((version) => (
                <option key={version.id} value={version.id}>
                  {version.label}
                  {version.default ? ' (default)' : ''}
                </option>
              ))}
            </select>
          </label>
        )}
        {boot.locales.length > 1 && (
          <label className="lra-scope">
            <span className="lr-sr">Language</span>
            <select className="lr-input lra-select lra-select-sm" value={scope.locale} onChange={(event) => setScope({ ...scope, locale: event.target.value })}>
              {boot.locales.map((locale) => (
                <option key={locale.code} value={locale.code}>
                  {locale.label} ({locale.code})
                </option>
              ))}
            </select>
          </label>
        )}
        <button type="button" className="lra-icon-button" onClick={cycleTheme} aria-label={`Theme: ${theme}. Change it.`} title={`Theme: ${theme}`}>
          <Icon name={theme === 'light' ? 'Sun' : theme === 'dark' ? 'Moon' : 'Monitor'} size={16} />
        </button>
        <span className="lr-meta lra-user" title="Signed in">
          {boot.user.name}
        </span>
      </header>

      <div className="lra-body">
        <nav className="lra-sidebar" aria-label="Admin" data-open={menuOpen || undefined}>
          <p className="lr-nav-label lra-sidebar-label">{boot.set.driver === 'database' ? 'Doc set · database' : 'Doc set · repository'}</p>
          <ul className="lr-nav-list">
            {SCREENS.filter((screen) => editable || !screen.editOnly).map((screen) => (
              <li key={screen.route}>
                <a className="lr-nav-link lra-nav-link" href={href({ name: screen.route } as Route)} aria-current={section === screen.route ? 'page' : undefined} onClick={go({ name: screen.route } as Route)}>
                  <AdminIcon name={screen.icon} size={15} />
                  <span>{screen.label}</span>
                  {screen.route === 'review' && pending > 0 && (
                    <span className="lra-count" aria-label={`${pending} waiting`}>
                      {pending}
                    </span>
                  )}
                </a>
              </li>
            ))}
          </ul>
          <div className="lra-sidebar-foot">
            <a className="lr-nav-link lra-nav-link" href={boot.urls.reader} target="_blank" rel="noopener">
              <AdminIcon name="ExternalLink" size={15} />
              <span>Open the docs</span>
            </a>
            <p className="lr-meta">LaRecipe {boot.version}</p>
          </div>
        </nav>

        <main className="lra-main" id="lra-main" tabIndex={-1}>
          <Screen route={route} editable={editable} />
        </main>
      </div>

      <div className="lra-notices" role="status" aria-live="polite">
        {notices.map((notice) => (
          <div key={notice.id} className="lra-notice-toast" data-tone={notice.tone}>
            <span className="lr-label" data-tone={notice.tone}>
              {notice.tone === 'danger' ? 'Not done' : notice.tone === 'warning' ? 'Heads up' : notice.tone === 'info' ? 'Note' : 'Done'}
            </span>
            <span>{notice.message}</span>
            <button type="button" className="lra-icon-button" aria-label="Dismiss" onClick={() => dismiss(notice.id)}>
              <Icon name="X" size={14} />
            </button>
          </div>
        ))}
      </div>
    </div>
  )
}

function Screen({ route, editable }: { route: Route; editable: boolean }): ReactNode {
  if (!editable && route.name !== 'overview') {
    return <Overview />
  }

  switch (route.name) {
    case 'overview':
      return <Overview />
    case 'pages':
      return <Pages />
    case 'page':
      return <PageEditor path={route.path} />
    case 'new':
      return <NewPage />
    case 'review':
      return <Review />
    case 'ticket':
      return <Ticket id={route.id} />
    case 'activity':
      return <Activity />
    case 'revision':
      return <RevisionScreen id={route.id} />
    case 'settings':
      return <Settings />
    case 'assets':
      return <Assets />
    default:
      return (
        <div className="lra-screen">
          <p className="lr-label">404</p>
          <h1 className="lra-title">Nothing here</h1>
          <p className="lra-lead">The admin has no screen at {route.path}.</p>
        </div>
      )
  }
}
