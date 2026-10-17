import { useEffect, useRef, type ReactNode } from 'react'

/**
 * A disclosure menu built on <details>, the same element the prerender uses,
 * so it opens without JavaScript. With JavaScript it also closes on an outside
 * click, on Escape (returning focus to its button) and after a choice, and the
 * arrow keys move between its items.
 */
export function Menu({
  className,
  summary,
  summaryClassName,
  summaryLabel,
  children,
  onOpenChange,
}: {
  className?: string
  summary: ReactNode
  summaryClassName: string
  summaryLabel?: string
  children: ReactNode
  onOpenChange?: (open: boolean) => void
}) {
  const ref = useRef<HTMLDetailsElement>(null)

  useEffect(() => {
    const details = ref.current
    if (!details) return

    const close = (focusSummary = false) => {
      if (!details.open) return
      details.open = false
      if (focusSummary) details.querySelector<HTMLElement>(':scope > summary')?.focus()
    }

    const items = () => Array.from(details.querySelectorAll<HTMLElement>('.lr-menu-panel a[href], .lr-menu-panel button:not([disabled])')).filter((item) => item.offsetParent !== null)

    const onDocumentPointer = (event: PointerEvent) => {
      if (details.open && event.target instanceof Node && !details.contains(event.target)) close()
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (!details.open) return
      if (event.key === 'Escape') {
        event.preventDefault()
        event.stopPropagation()
        close(true)
        return
      }
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Home' || event.key === 'End') {
        const list = items()
        if (list.length === 0) return
        event.preventDefault()
        const index = list.indexOf(document.activeElement as HTMLElement)
        const next =
          event.key === 'Home' ? 0 : event.key === 'End' ? list.length - 1 : event.key === 'ArrowDown' ? (index + 1) % list.length : (index - 1 + list.length) % list.length
        list[next]?.focus()
      }
    }

    const onClick = (event: MouseEvent) => {
      const target = event.target instanceof Element ? event.target.closest('.lr-menu-panel a[href], .lr-menu-panel button') : null
      if (target) window.setTimeout(() => close(), 0)
    }

    const onToggle = () => {
      onOpenChange?.(details.open)
      if (details.open) {
        const current = details.querySelector<HTMLElement>('.lr-menu-panel [aria-current], .lr-menu-panel [aria-pressed="true"]')
        ;(current ?? items()[0])?.focus({ preventScroll: true })
      }
    }

    const onFocusOut = (event: FocusEvent) => {
      if (details.open && event.relatedTarget instanceof Node && !details.contains(event.relatedTarget)) close()
    }

    document.addEventListener('pointerdown', onDocumentPointer)
    details.addEventListener('keydown', onKeyDown)
    details.addEventListener('click', onClick)
    details.addEventListener('toggle', onToggle)
    details.addEventListener('focusout', onFocusOut)

    return () => {
      document.removeEventListener('pointerdown', onDocumentPointer)
      details.removeEventListener('keydown', onKeyDown)
      details.removeEventListener('click', onClick)
      details.removeEventListener('toggle', onToggle)
      details.removeEventListener('focusout', onFocusOut)
    }
  }, [onOpenChange])

  return (
    <details ref={ref} className={['lr-menu', className].filter(Boolean).join(' ')}>
      <summary className={summaryClassName} aria-label={summaryLabel}>
        {summary}
      </summary>
      {children}
    </details>
  )
}
