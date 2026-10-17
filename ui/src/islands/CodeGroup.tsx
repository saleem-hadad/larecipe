import { useLayoutEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { CopyButton, codeText, nextId, propsFor } from './shared'
import { tabKeyHandler, useSyncedSelection } from './Tabs'

function titleOf(figure: HTMLElement, index: number): string {
  return figure.dataset.title || figure.querySelector('.lr-code-title')?.textContent?.trim() || figure.dataset.language || `Code ${index + 1}`
}

/**
 * Consecutive code blocks as one slab with mono tabs (2 px violet underline on
 * the active one) and a single copy button. The server renders every block in
 * order; readers with JavaScript see only the first before React arrives, so
 * the swap to tabs keeps the same height.
 */
export function CodeGroupIsland({ element }: { element: HTMLElement }) {
  const [setup, setSetup] = useState<{ strip: HTMLElement; list: HTMLElement; actions: HTMLElement; figures: HTMLElement[]; labels: string[]; base: string } | null>(null)
  const props = propsFor(element)
  const [active, select] = useSyncedSelection(setup?.labels ?? [], 0, typeof props.sync === 'string' && props.sync !== '' ? props.sync : null)
  const buttons = useRef<Array<HTMLButtonElement | null>>([])

  useLayoutEffect(() => {
    const group = element.classList.contains('lr-code-group') ? element : (element.querySelector<HTMLElement>('.lr-code-group') ?? element)
    const figures = Array.from(group.querySelectorAll<HTMLElement>('figure.lr-code')).filter((figure) => figure.parentElement?.closest('.lr-code-group, [data-lr-component="CodeGroup"]') === group || figure.parentElement === group)
    if (figures.length === 0) return

    const base = nextId('lr-code-group')
    const hadClass = group.classList.contains('lr-code-group')
    group.classList.add('lr-code-group')

    const strip = document.createElement('div')
    strip.className = 'lr-code-tabs'
    const list = document.createElement('div')
    list.className = 'lr-code-tablist'
    list.setAttribute('role', 'tablist')
    const actions = document.createElement('div')
    actions.className = 'lr-code-actions'
    strip.append(list, actions)
    group.insertBefore(strip, figures[0] ?? group.firstChild)

    group.dataset.enhanced = ''
    element.dataset.enhanced = ''
    figures.forEach((figure, index) => {
      figure.id ||= `${base}-panel-${index}`
      figure.setAttribute('role', 'tabpanel')
      figure.setAttribute('aria-labelledby', `${base}-tab-${index}`)
    })

    // Labels come from the server (derived prop), else from each block's title or language.
    const derived = propsFor(element).labels
    const labels = figures.map((figure, index) => (Array.isArray(derived) && typeof derived[index] === 'string' ? (derived[index] as string) : titleOf(figure, index)))
    setSetup({ strip, list, actions, figures, labels, base })

    return () => {
      strip.remove()
      if (!hadClass) group.classList.remove('lr-code-group')
      delete group.dataset.enhanced
      delete element.dataset.enhanced
      figures.forEach((figure) => {
        figure.hidden = false
        figure.removeAttribute('role')
        figure.removeAttribute('aria-labelledby')
      })
    }
  }, [element])

  useLayoutEffect(() => {
    setup?.figures.forEach((figure, index) => {
      figure.hidden = index !== active
    })
  }, [setup, active])

  if (!setup) return null

  const onKeyDown = tabKeyHandler(setup.labels.length, active, select, (index) => buttons.current[index]?.focus())

  return (
    <>
      {createPortal(
        setup.labels.map((label, index) => (
          <button
            key={index}
            ref={(node) => {
              buttons.current[index] = node
            }}
            type="button"
            role="tab"
            id={`${setup.base}-tab-${index}`}
            className="lr-code-tab"
            aria-selected={index === active}
            aria-controls={setup.figures[index]?.id}
            tabIndex={index === active ? 0 : -1}
            onClick={() => select(index)}
            onKeyDown={onKeyDown}
          >
            {label}
          </button>
        )),
        setup.list,
      )}
      {createPortal(<CopyButton getText={() => codeText(setup.figures[active])} />, setup.actions)}
    </>
  )
}
