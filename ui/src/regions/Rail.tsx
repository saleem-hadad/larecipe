import { Slot, splitTemplate, Ticket, useLaRecipe, type TocItem } from '@larecipe/ui'
import { Fragment, useEffect, useLayoutEffect, useMemo, useRef, useState, type RefObject } from 'react'
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

/** How far each heading level steps in, and how far the line runs on a slant between levels. */
const TRACK_STEP = 12
const TRACK_SLANT = 6

interface TrackGeometry {
  path: string
  width: number
  height: number
  top: number
  /** Each item's top and bottom inside the track. */
  rows: Array<{ top: number; bottom: number }>
}

/**
 * The line beside "On this page": one path down the list that steps in for
 * nested headings with a short slant, measured from the rendered items.
 */
function useTrackGeometry(list: RefObject<HTMLUListElement | null>, items: TocItem[]): TrackGeometry | null {
  const [geometry, setGeometry] = useState<TrackGeometry | null>(null)

  useLayoutEffect(() => {
    const element = list.current
    if (!element || items.length === 0) {
      setGeometry(null)
      return
    }

    const measure = () => {
      const box = element.getBoundingClientRect()
      const rows = Array.from(element.children).map((child) => {
        const rect = child.getBoundingClientRect()
        return { top: rect.top - box.top, bottom: rect.bottom - box.top }
      })
      if (rows.length === 0) return
      const xs = items.map((item) => 1 + Math.max(0, item.level - 2) * TRACK_STEP)
      let path = ''
      rows.forEach((row, index) => {
        const x = xs[index] ?? 1
        const previous = index > 0 ? xs[index - 1] : x
        const next = index < rows.length - 1 ? xs[index + 1] : x
        path += index === 0 ? `M ${x} ${row.top}` : previous === x ? ` L ${x} ${row.top}` : ` L ${x} ${row.top + TRACK_SLANT}`
        path += ` L ${x} ${next === x ? row.bottom : row.bottom - TRACK_SLANT}`
      })
      setGeometry({ path, width: Math.max(...xs) + 2, height: box.height, top: element.offsetTop, rows })
    }

    measure()
    const observer = typeof ResizeObserver === 'function' ? new ResizeObserver(measure) : null
    observer?.observe(element)
    return () => observer?.disconnect()
  }, [list, items])

  return geometry
}

/** The items whose sections are on screen, as a first and last index; the current section when none is. */
function useVisibleRange(items: TocItem[], active: string | null): [number, number] | null {
  const [range, setRange] = useState<[number, number] | null>(null)

  useEffect(() => {
    if (items.length === 0) {
      setRange(null)
      return
    }

    let frame = 0
    const compute = () => {
      frame = 0
      const headings = items.map((item) => document.getElementById(item.id))
      const header = Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--lr-header-height')) || 60
      const end = document.getElementById('lr-article')?.getBoundingClientRect().bottom ?? Number.POSITIVE_INFINITY
      let first = -1
      let last = -1
      headings.forEach((heading, index) => {
        if (!heading) return
        const top = heading.getBoundingClientRect().top
        const nextHeading = headings.slice(index + 1).find((node) => node !== null)
        const bottom = nextHeading ? nextHeading.getBoundingClientRect().top : end
        if (top < window.innerHeight && bottom > header) {
          if (first === -1) first = index
          last = index
        }
      })
      if (first === -1) {
        const current = items.findIndex((item) => item.id === active)
        setRange(current === -1 ? null : [current, current])
      } else {
        setRange((previous) => (previous && previous[0] === first && previous[1] === last ? previous : [first, last]))
      }
    }
    const schedule = () => {
      if (frame === 0) frame = window.requestAnimationFrame(compute)
    }

    compute()
    window.addEventListener('scroll', schedule, { passive: true })
    window.addEventListener('resize', schedule, { passive: true })
    // Diagrams, math and media render as they come into view and move the headings without a scroll.
    const article = document.getElementById('lr-article')
    const observer = article && typeof ResizeObserver === 'function' ? new ResizeObserver(schedule) : null
    if (article) observer?.observe(article)
    return () => {
      if (frame !== 0) window.cancelAnimationFrame(frame)
      window.removeEventListener('scroll', schedule)
      window.removeEventListener('resize', schedule)
      observer?.disconnect()
    }
  }, [items, active])

  return range
}

export function Toc() {
  const { page, t } = useLaRecipe()
  // Stable between renders: the track hooks measure again only when the headings change.
  const items = useMemo(() => page.toc.filter((item) => item.level <= 4), [page.toc])
  const active = useScrollSpy(page.toc)
  const list = useRef<HTMLUListElement>(null)
  const geometry = useTrackGeometry(list, items)
  const range = useVisibleRange(items, active)
  if (items.length === 0) return null

  const lit = geometry && range ? { top: geometry.rows[range[0]]?.top ?? 0, bottom: geometry.rows[range[1]]?.bottom ?? 0 } : null

  return (
    <nav className="lr-rail-section lr-toc" aria-labelledby="lr-toc-label">
      <p className="lr-label" id="lr-toc-label">
        {t('page.on_this_page')}
      </p>
      {geometry && (
        <div className="lr-toc-track" aria-hidden="true" style={{ top: geometry.top, width: geometry.width, height: geometry.height }}>
          <svg width={geometry.width} height={geometry.height} viewBox={`0 0 ${geometry.width} ${geometry.height}`}>
            <path className="lr-toc-track-line" d={geometry.path} />
          </svg>
          <svg
            className="lr-toc-track-lit"
            width={geometry.width}
            height={geometry.height}
            viewBox={`0 0 ${geometry.width} ${geometry.height}`}
            style={{ clipPath: lit ? `inset(${lit.top}px 0 ${Math.max(0, geometry.height - lit.bottom)}px 0)` : 'inset(0 0 100% 0)' }}
          >
            <path d={geometry.path} />
          </svg>
        </div>
      )}
      <ul ref={list} className="lr-toc-list" data-lr-track={geometry ? '' : undefined}>
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
