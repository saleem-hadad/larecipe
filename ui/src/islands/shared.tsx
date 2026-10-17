import { Icon, useLaRecipe } from '@larecipe/ui'
import { useApp, useFlash } from '../app/context'
import { islandProps } from '../app/Islands'

/**
 * The text a reader expects when copying a code block: the code as written,
 * without lines a diff removed.
 */
export function codeText(container: Element | null | undefined): string {
  if (!container) return ''
  const code = container.querySelector('pre code') ?? container.querySelector('pre') ?? (container.matches('pre') ? container : null)
  if (!code) return ''
  const clone = code.cloneNode(true) as HTMLElement
  clone.querySelectorAll('[data-diff="remove"]').forEach((line) => {
    const next = line.nextSibling
    if (next && next.nodeType === Node.TEXT_NODE && next.textContent?.startsWith('\n')) {
      next.textContent = next.textContent.slice(1)
    }
    line.remove()
  })
  return (clone.textContent ?? '').replace(/\n$/, '')
}

/** The props of the island wrapping an element, if any. */
export function propsFor(element: Element): Record<string, unknown> {
  const island = element.closest('[data-lr-component]')
  return island ? islandProps(island) : {}
}

/** The copy button on every code block: the icon becomes a check and the label reads "Copied" for 1.2 s. */
export function CopyButton({ getText }: { getText: () => string }) {
  const { t } = useLaRecipe()
  const { actions } = useApp()
  const [copied, flash] = useFlash()

  return (
    <button
      type="button"
      className="lr-code-copy"
      aria-label={copied ? t('code.copied') : t('code.copy_label')}
      data-copied={copied || undefined}
      onClick={async () => {
        if (await actions.copyText(getText())) {
          flash()
          actions.announce(t('code.copied'))
        }
      }}
    >
      <Icon name={copied ? 'Check' : 'Copy'} size={13} />
      <span aria-hidden="true">{copied ? t('code.copied') : t('code.copy')}</span>
    </button>
  )
}

/** Run a callback once an element is near the viewport (lazy islands). */
export function whenNearViewport(element: Element, callback: () => void, margin = '400px'): () => void {
  if (typeof IntersectionObserver === 'undefined') {
    callback()
    return () => {}
  }
  const observer = new IntersectionObserver(
    (entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        observer.disconnect()
        callback()
      }
    },
    { rootMargin: margin },
  )
  observer.observe(element)
  return () => observer.disconnect()
}

let counter = 0
/** Stable-enough ids for elements the enhancers create. */
export function nextId(prefix: string): string {
  counter += 1
  return `${prefix}-${counter}`
}
