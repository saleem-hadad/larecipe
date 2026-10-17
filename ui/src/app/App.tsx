import { LaRecipeContext, type BootPayload, type NavItem } from '@larecipe/ui'
import { useCallback, useMemo, useState } from 'react'
import { ArticleHost } from './ArticleHost'
import { useIslands } from './Islands'

const builtInIslands = {}

/**
 * The reading experience. Foundation version: the same regions and class names
 * as the Blade prerender, so the takeover is invisible. The full UI (command
 * palette, client navigation, page actions, Ask AI) builds on these regions.
 */
export function App({ boot, article }: { boot: BootPayload; article: HTMLElement | null }) {
  const [articleEl, setArticleEl] = useState<HTMLElement | null>(null)
  const islands = useIslands(articleEl, builtInIslands)
  const theme = useMemo(() => ({ mode: (document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light') as 'light' | 'dark' }), [])
  const navigate = useCallback((url: string) => window.location.assign(url), [])
  const context = useMemo(() => ({ boot, page: boot.page, set: boot.set, theme, user: boot.user, navigate }), [boot, theme, navigate])

  return (
    <LaRecipeContext.Provider value={context}>
      <header className="lr-header" data-lr-region="Header">
        <a className="lr-brand" href={boot.urls.base}>
          <span className="lr-brand-name">{boot.set.name}</span>
        </a>
        <div className="lr-header-spacer" />
      </header>
      <div className="lr-shell">
        <nav className="lr-sidebar" aria-label="Docs" data-lr-region="Sidebar">
          {boot.navigation.map((tab, index) => (
            <NavList key={index} items={tab.items} />
          ))}
        </nav>
        <main className="lr-main" id="lr-main">
          <div className="lr-page-header" data-lr-region="PageHeader">
            <h1 className="lr-title">{boot.page.title}</h1>
            {boot.page.description && <p className="lr-lead">{boot.page.description}</p>}
          </div>
          <ArticleHost node={article} onMount={setArticleEl} />
        </main>
        <aside className="lr-rail" data-lr-region="Toc">
          {boot.page.toc.length > 0 && (
            <nav className="lr-toc" aria-label="On this page">
              <div className="lr-label">On this page</div>
              <ul>
                {boot.page.toc.map((item) => (
                  <li key={item.id} className={`lr-toc-level-${item.level}`}>
                    <a href={`#${item.id}`}>{item.text}</a>
                  </li>
                ))}
              </ul>
            </nav>
          )}
        </aside>
      </div>
      {islands}
    </LaRecipeContext.Provider>
  )
}

function NavList({ items }: { items: NavItem[] }) {
  return (
    <ul className="lr-nav-list">
      {items.map((item, index) =>
        item.type === 'group' ? (
          <li key={index} className="lr-nav-group">
            <div className="lr-nav-label">{item.label}</div>
            <NavList items={item.items} />
          </li>
        ) : item.type === 'page' ? (
          <li key={index}>
            <a className="lr-nav-link" href={item.url} aria-current={item.active ? 'page' : undefined}>
              {item.title}
            </a>
          </li>
        ) : (
          <li key={index}>
            <a className="lr-nav-link" href={item.href} rel="noopener">
              {item.label}
            </a>
          </li>
        ),
      )}
    </ul>
  )
}
