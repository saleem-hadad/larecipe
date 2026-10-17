import { Slot, splitTemplate, Ticket, useLaRecipe, type TocItem } from '@larecipe/ui'
import { Fragment, useEffect, useState } from 'react'
import { useAppState } from '../app/context'
import { Insert } from '../app/Insert'
import { PageActions } from './PageActions'

const SPY_OFFSET = 96

/**
 * Which section the reader is in: the last heading that has passed under the
 * header. At the very top that is the first heading, the same item the
 * prerender marks, so nothing changes when React takes over.
 */
export function useScrollSpy(items: TocItem[]): string | null {
  const [active, setActive] = useState<string | null>(items[0]?.id ?? null)

  useEffect(() => {
    if (items.length === 0) {
      setActive(null)
      return
    }

    let frame = 0
    const compute = () => {
      frame = 0
      const headings = items.map((item) => document.getElementById(item.id)).filter((node): node is HTMLElement => node !== null)
      if (headings.length === 0) return
      const header = Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--lr-header-height')) || 60
      const line = header + SPY_OFFSET
      let current = headings[0]?.id ?? null
      for (const heading of headings) {
        if (heading.getBoundingClientRect().top <= line) current = heading.id
        else break
      }
      if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2 && window.scrollY > 0) {
        current = headings[headings.length - 1]?.id ?? current
      }
      setActive(current)
    }
    const schedule = () => {
      if (frame === 0) frame = window.requestAnimationFrame(compute)
    }

    compute()
    window.addEventListener('scroll', schedule, { passive: true })
    window.addEventListener('resize', schedule, { passive: true })
    return () => {
      if (frame !== 0) window.cancelAnimationFrame(frame)
      window.removeEventListener('scroll', schedule)
      window.removeEventListener('resize', schedule)
    }
  }, [items])

  return active
}

export function Toc() {
  const { page, t } = useLaRecipe()
  const items = page.toc.filter((item) => item.level <= 4)
  const active = useScrollSpy(page.toc)
  if (items.length === 0) return null

  return (
    <nav className="lr-rail-section lr-toc" aria-labelledby="lr-toc-label">
      <p className="lr-label" id="lr-toc-label">
        {t('page.on_this_page')}
      </p>
      <ul className="lr-toc-list">
        {items.map((item) => (
          <li key={item.id} className={`lr-toc-item lr-toc-level-${item.level}`}>
            <a className="lr-toc-link" href={`#${item.id}`} data-active={item.id === active ? '' : undefined} aria-current={item.id === active ? 'true' : undefined}>
              {item.text}
            </a>
          </li>
        ))}
      </ul>
    </nav>
  )
}

/**
 * The Last change ticket. Drawn only when the source recorded a change; a page
 * with nothing but a file date gets no ticket.
 */
export function LastChange() {
  const { page, t } = useLaRecipe()
  const navigated = useAppState((state) => state.navigated)
  const ticket = page.ticket
  if (!ticket) return null

  const facts: Array<{ text: string; mono: boolean }> = []
  if (ticket.ref) facts.push({ text: ticket.ref, mono: true })
  if (ticket.pages_label) facts.push({ text: ticket.pages_label, mono: false })
  if (ticket.additions !== null || ticket.deletions !== null) facts.push({ text: `+${ticket.additions ?? 0} −${ticket.deletions ?? 0}`, mono: true })

  return (
    <Ticket
      key={`${page.path}:${ticket.at ?? ''}`}
      label={t('ticket.label')}
      labelId="lr-ticket-label"
      number={ticket.number ?? undefined}
      time={ticket.time ?? undefined}
      dateTime={ticket.at ?? undefined}
      fresh={page.fresh}
      print={navigated}
      meta={
        facts.length > 0 || ticket.approved_by ? (
          <>
            {facts.length > 0 && (
              <span className="lr-ticket-meta-line">
                {facts.map((fact, index) => (
                  <Fragment key={index}>
                    {index > 0 && <span aria-hidden="true"> · </span>}
                    <span className={fact.mono ? 'lr-hash' : undefined}>{fact.text}</span>
                  </Fragment>
                ))}
              </span>
            )}
            {ticket.approved_by && (
              <span className="lr-ticket-meta-line">
                {splitTemplate(t('ticket.approved_by'), ['name']).map((part, index) => (typeof part === 'string' ? <Fragment key={index}>{part}</Fragment> : <bdi key={index}>{ticket.approved_by}</bdi>))}
              </span>
            )}
          </>
        ) : undefined
      }
      action={ticket.url ? { label: t('ticket.view'), href: ticket.url } : undefined}
    >
      {ticket.summary}
    </Ticket>
  )
}

export function Rail() {
  return (
    <aside className="lr-rail" data-lr-region="Toc">
      <Slot name="Toc" component={Toc} />
      <Slot name="PageActions" component={PageActions} />
      <Slot name="LastChange" component={LastChange} />
      <Insert point="toc.after" />
    </aside>
  )
}
