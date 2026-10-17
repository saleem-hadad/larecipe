import { Icon, splitTemplate, useLaRecipe } from '@larecipe/ui'
import { Fragment, type ReactNode } from 'react'
import { PageActionsSplit } from './PageActions'
import { Menu } from './Menu'

/**
 * "Updated 2 hours ago by Claude Code · 9f3a2c1 · 4 min read", with the fresh
 * dot in front when the page changed this week. Provenance is designed into
 * the page, not appended to it.
 */
export function ProvenanceLine() {
  const { page, t } = useLaRecipe()
  const ticket = page.ticket ?? null
  const name = page.provenance?.name ?? null
  const parts: ReactNode[] = []

  if (page.updated_at && page.updated_human) {
    const template = name ? t('page.updated_by') : t('page.updated')
    parts.push(
      <span key="updated">
        {splitTemplate(template, ['time', 'name']).map((part, index) =>
          typeof part === 'string' ? (
            <Fragment key={index}>{part}</Fragment>
          ) : part.key === 'time' ? (
            <time key={index} dateTime={page.updated_at ?? undefined}>
              {page.updated_human}
            </time>
          ) : (
            <bdi key={index}>{name}</bdi>
          ),
        )}
      </span>,
    )
  }

  if (ticket?.ref) {
    parts.push(
      <span key="ref" className="lr-hash">
        {ticket.ref}
      </span>,
    )
  }

  if (page.reading_label) {
    parts.push(<span key="reading">{page.reading_label}</span>)
  }

  if (parts.length === 0) return null

  return (
    <p className="lr-provenance">
      {page.fresh && page.updated_at && <span className="lr-fresh-dot" aria-hidden="true" />}
      {parts.map((part, index) => (
        <Fragment key={index}>
          {index > 0 && (
            <span className="lr-sep" aria-hidden="true">
              ·
            </span>
          )}
          {part}
        </Fragment>
      ))}
    </p>
  )
}

function MobileToc() {
  const { page, t } = useLaRecipe()
  const items = page.toc.filter((item) => item.level <= 3)
  if (items.length === 0) return null

  return (
    <Menu
      className="lr-toc-disclosure"
      summaryClassName="lr-button lr-button-outline lr-button-sm"
      summary={
        <>
          <Icon name="TextAlignStart" size={15} />
          {t('page.on_this_page')}
        </>
      }
    >
      <div className="lr-menu-panel">
        <ul className="lr-menu-list">
          {items.map((item) => (
            <li key={item.id} className={`lr-toc-level-${item.level}`}>
              <a className="lr-menu-item" href={`#${item.id}`}>
                {item.text}
              </a>
            </li>
          ))}
        </ul>
      </div>
    </Menu>
  )
}

export function PageHeader() {
  const { page, t } = useLaRecipe()
  const crumbs = page.breadcrumb ?? []

  return (
    <div className="lr-page-header" data-lr-region="PageHeader">
      {page.fallback_from_locale && (
        <p className="lr-fallback-notice" role="note">
          <Icon name="Languages" size={16} />
          {t('page.fallback')}
        </p>
      )}
      {crumbs.length > 0 && (
        <p className="lr-breadcrumb lr-label">
          {[...crumbs, page.title].map((crumb, index) => (
            <Fragment key={index}>
              {index > 0 && (
                <span className="lr-breadcrumb-sep" aria-hidden="true">
                  /
                </span>
              )}
              <span>{crumb}</span>
            </Fragment>
          ))}
        </p>
      )}
      <h1 className="lr-title">{page.title}</h1>
      {page.description && <p className="lr-lead">{page.description}</p>}
      <ProvenanceLine />
      <div className="lr-page-tools">
        <PageActionsSplit />
        <MobileToc />
      </div>
    </div>
  )
}
