import { useCallback, useEffect, useLayoutEffect, useRef, useState, type KeyboardEvent } from 'react'
import { createPortal } from 'react-dom'
import { nextId, propsFor } from './shared'

/**
 * Groups that share a sync key (`<Tabs sync="pm">`) move together: picking
 * "pnpm" in one picks it in every group with the same key on the page.
 */
const channel = typeof EventTarget !== 'undefined' ? new EventTarget() : null

export function useSyncedSelection(labels: string[], initial: number, syncKey: string | null = null): [number, (index: number) => void] {
  const [active, setActive] = useState(initial)
  const labelsRef = useRef(labels)
  labelsRef.current = labels

  useEffect(() => {
    if (!channel || !syncKey) return
    const onSelect = (event: Event) => {
      const { key, label } = (event as CustomEvent<{ key: string; label: string }>).detail
      if (key !== syncKey) return
      const index = labelsRef.current.findIndex((candidate) => candidate.toLowerCase() === label.toLowerCase())
      if (index >= 0) setActive(index)
    }
    channel.addEventListener('select', onSelect)
    return () => channel.removeEventListener('select', onSelect)
  }, [syncKey])

  const select = useCallback(
    (index: number) => {
      setActive(index)
      const label = labelsRef.current[index]
      if (label && channel && syncKey) channel.dispatchEvent(new CustomEvent('select', { detail: { key: syncKey, label } }))
    },
    [syncKey],
  )

  return [active, select]
}

/** Arrow keys, Home and End across a tab list; arrows follow the reading direction. */
export function tabKeyHandler(count: number, active: number, select: (index: number) => void, focus: (index: number) => void) {
  return (event: KeyboardEvent<HTMLElement>) => {
    const rtl = getComputedStyle(event.currentTarget).direction === 'rtl'
    const forward = rtl ? 'ArrowLeft' : 'ArrowRight'
    const backward = rtl ? 'ArrowRight' : 'ArrowLeft'
    let next: number | null = null
    if (event.key === forward) next = (active + 1) % count
    else if (event.key === backward) next = (active - 1 + count) % count
    else if (event.key === 'Home') next = 0
    else if (event.key === 'End') next = count - 1
    if (next === null) return
    event.preventDefault()
    select(next)
    focus(next)
  }
}

/**
 * Tabs. The server renders every panel with an <h4> label, so all content
 * reads without JavaScript. With JavaScript the labels already sit in a strip
 * before React arrives (CSS); this island swaps them for a real tab list of
 * the same height and hides the inactive panels.
 */
export function TabsIsland({ element }: { element: HTMLElement }) {
  const [setup, setSetup] = useState<{ strip: HTMLElement; panels: HTMLElement[]; labels: string[]; base: string } | null>(null)
  const props = propsFor(element)
  const labels = setup?.labels ?? []
  const preferred = typeof props.default === 'string' ? labels.findIndex((label) => label.toLowerCase() === String(props.default).toLowerCase()) : -1
  const [active, select] = useSyncedSelection(labels, 0, typeof props.sync === 'string' && props.sync !== '' ? props.sync : null)
  const buttons = useRef<Array<HTMLButtonElement | null>>([])

  useLayoutEffect(() => {
    const panels = Array.from(element.children).filter((child): child is HTMLElement => child instanceof HTMLElement && child.classList.contains('lr-tab'))
    if (panels.length === 0) return
    const base = nextId('lr-tabs')
    const strip = document.createElement('div')
    strip.className = 'lr-tab-strip'
    strip.setAttribute('role', 'tablist')
    element.insertBefore(strip, element.firstChild)
    element.dataset.enhanced = ''

    const names = panels.map((panel, index) => panel.dataset.label ?? panel.querySelector(':scope > .lr-tab-label')?.textContent?.trim() ?? `Tab ${index + 1}`)
    panels.forEach((panel, index) => {
      panel.id ||= `${base}-panel-${index}`
      panel.setAttribute('role', 'tabpanel')
      panel.setAttribute('aria-labelledby', `${base}-tab-${index}`)
      panel.tabIndex = 0
    })
    setSetup({ strip, panels, labels: names, base })

    return () => {
      strip.remove()
      delete element.dataset.enhanced
      panels.forEach((panel) => {
        panel.hidden = false
        panel.removeAttribute('role')
        panel.removeAttribute('aria-labelledby')
        panel.removeAttribute('tabindex')
      })
    }
  }, [element])

  // An author's default tab, applied once.
  useLayoutEffect(() => {
    if (preferred > 0) select(preferred)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [preferred])

  useLayoutEffect(() => {
    setup?.panels.forEach((panel, index) => {
      panel.hidden = index !== active
    })
  }, [setup, active])

  if (!setup) return null

  const onKeyDown = tabKeyHandler(setup.labels.length, active, select, (index) => buttons.current[index]?.focus())

  return createPortal(
    <>
      {setup.labels.map((label, index) => (
        <button
          key={index}
          ref={(node) => {
            buttons.current[index] = node
          }}
          type="button"
          role="tab"
          id={`${setup.base}-tab-${index}`}
          className="lr-tab-trigger"
          aria-selected={index === active}
          aria-controls={setup.panels[index]?.id}
          tabIndex={index === active ? 0 : -1}
          onClick={() => select(index)}
          onKeyDown={onKeyDown}
        >
          {label}
        </button>
      ))}
    </>,
    setup.strip,
  )
}
