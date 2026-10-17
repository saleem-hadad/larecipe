import { Icon, useLaRecipe, type NavItem } from '@larecipe/ui'
import { useEffect, useLayoutEffect, useRef } from 'react'
import { useApp, useAppState } from '../app/context'
import { Insert } from '../app/Insert'
import { Brand, LocaleLinks, Navbar } from './Header'

function containsActive(items: NavItem[]): boolean {
  return items.some((item) => (item.type === 'page' && item.active) || (item.type === 'group' && containsActive(item.items)))
}

function NavEntry({ item, depth }: { item: NavItem; depth: number }) {
  const { boot, t } = useLaRecipe()

  if (item.type === 'page') {
    const original = item.fallback ? boot.set.locales.find((locale) => locale.default) : undefined
    return (
      <li>
        <a className="lr-nav-link" href={item.url} aria-current={item.active ? 'page' : undefined}>
          <span className="lr-nav-text" lang={original?.code}>
            {item.title}
          </span>
          {item.badge?.label && (
            <span className="lr-badge" data-tone={item.badge.tone ?? undefined}>
              {item.badge.label}
            </span>
          )}
          {item.fresh && (
            <span className="lr-fresh-dot" title={t('nav.updated')}>
              <span className="lr-sr">{t('nav.updated')}</span>
            </span>
          )}
        </a>
      </li>
    )
  }

  if (item.type === 'link') {
    return (
      <li>
        <a className="lr-nav-link" href={item.href} rel="noopener">
          <span className="lr-nav-text">{item.label}</span>
          <Icon name="ArrowUpRight" size={13} className="lr-nav-external" />
        </a>
      </li>
    )
  }

  const collapsible = depth > 0 || item.collapsed === true

  if (!collapsible) {
    return (
      <li className="lr-nav-group">
        <div className="lr-nav-label">{item.label}</div>
        <NavList items={item.items} depth={depth + 1} />
      </li>
    )
  }

  return (
    <li className="lr-nav-group">
      <details open={!item.collapsed || containsActive(item.items)}>
        <summary className="lr-nav-label lr-nav-summary">
          <span>{item.label}</span>
          <Icon name="ChevronDown" size={14} />
        </summary>
        <NavList items={item.items} depth={depth + 1} />
      </details>
    </li>
  )
}

function NavList({ items, depth }: { items: NavItem[]; depth: number }) {
  return (
    <ul className="lr-nav-list">
      {items.map((item, index) => (
        <NavEntry key={`${item.type}:${'path' in item ? item.path : 'label' in item ? item.label : index}:${index}`} item={item} depth={depth} />
      ))}
    </ul>
  )
}

/**
 * The navigation. Mono group labels, the active page in violet soft, the fresh
 * dot for pages changed this week. It scrolls the active page into view, and
 * on phones it becomes a sheet (which also works without JavaScript, through
 * :target in the prerender).
 */
export function Sidebar() {
  const { boot, t } = useLaRecipe()
  const { actions } = useApp()
  const open = useAppState((state) => state.sidebar)
  const ref = useRef<HTMLElement>(null)
  const closeRef = useRef<HTMLButtonElement>(null)

  // Bring the active page into view without moving the page itself.
  useLayoutEffect(() => {
    const nav = ref.current
    const active = nav?.querySelector<HTMLElement>('[aria-current="page"]')
    if (!nav || !active) return
    const navBox = nav.getBoundingClientRect()
    const box = active.getBoundingClientRect()
    if (box.top < navBox.top + 24 || box.bottom > navBox.bottom - 24) {
      nav.scrollTop += box.top - navBox.top - nav.clientHeight / 2 + box.height / 2
    }
  }, [boot.page.path])

  // The sheet: focus moves in on open, Escape closes, focus returns to the menu button.
  useEffect(() => {
    if (!open) return
    const previous = document.activeElement as HTMLElement | null
    closeRef.current?.focus()
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault()
        actions.setSidebar(false)
      }
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
      if (previous && previous.isConnected) previous.focus()
    }
  }, [open, actions])

  return (
    <>
      <nav ref={ref} className="lr-sidebar" id="lr-sidebar" aria-label={t('nav.label')} data-lr-region="Sidebar" data-open={open || undefined}>
        <div className="lr-sidebar-head">
          <Brand />
          <button ref={closeRef} type="button" className="lr-icon-button" aria-label={t('header.close_menu')} onClick={() => actions.setSidebar(false)}>
            <Icon name="X" size={18} />
          </button>
        </div>
        <Insert point="sidebar.before" />
        {boot.navigation.map((tab, index) => (
          <div className="lr-nav-tab-group" key={tab.label ?? index}>
            {tab.label && <div className="lr-nav-tab">{tab.label}</div>}
            <NavList items={tab.items} depth={0} />
          </div>
        ))}
        <Insert point="sidebar.after" />
        <div className="lr-sidebar-extras">
          <Navbar />
          <LocaleLinks />
        </div>
      </nav>
      <div className="lr-scrim" aria-hidden="true" onClick={() => actions.setSidebar(false)} />
    </>
  )
}
