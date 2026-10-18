import { Icon, useLaRecipe } from '@larecipe/ui'
import { useEffect, useState } from 'react'

/** How far down the page the button appears, in pixels. */
export const BACK_TO_TOP_AFTER = 600

/**
 * The round button that appears once the reader is well down a page and
 * takes them back to the top. Focus moves to the page title, so keyboard
 * readers land where they can start again.
 */
export function BackToTop() {
  const { t } = useLaRecipe()
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    let frame = 0
    const update = () => {
      frame = 0
      setVisible(window.scrollY > BACK_TO_TOP_AFTER)
    }
    const schedule = () => {
      if (!frame) frame = window.requestAnimationFrame(update)
    }
    update()
    window.addEventListener('scroll', schedule, { passive: true })
    return () => {
      window.removeEventListener('scroll', schedule)
      if (frame) window.cancelAnimationFrame(frame)
    }
  }, [])

  const toTop = () => {
    const reduce = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
    window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' })
    const title = document.querySelector<HTMLElement>('.lr-title')
    if (title) {
      title.setAttribute('tabindex', '-1')
      title.focus({ preventScroll: true })
    }
  }

  return (
    <button
      type="button"
      className="lr-back-to-top"
      data-visible={visible ? '' : undefined}
      aria-label={t('header.back_to_top')}
      aria-hidden={visible ? undefined : true}
      tabIndex={visible ? undefined : -1}
      onClick={toTop}
    >
      <Icon name="ArrowUp" size={18} />
    </button>
  )
}
