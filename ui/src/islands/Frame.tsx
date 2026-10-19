import { Icon, useLaRecipe } from '@larecipe/ui'
import { useEffect, useLayoutEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { Dialog } from '../app/Dialog'
import { propsFor } from './shared'

/**
 * Frame: captioned media that zooms. A zoom button sits in the corner (and the
 * image itself is a target); the zoomed view is a modal on the dark scrim,
 * closed by Escape, the button or a click anywhere.
 */
export function FrameIsland({ figure }: { figure: HTMLElement }) {
  const { t } = useLaRecipe()
  const [container, setContainer] = useState<HTMLElement | null>(null)
  const [open, setOpen] = useState(false)
  const image = figure.querySelector('img')
  const zoomable = propsFor(figure).zoom !== false && image !== null

  useLayoutEffect(() => {
    if (!zoomable) return
    const holder = document.createElement('span')
    figure.appendChild(holder)
    figure.dataset.zoomable = ''
    setContainer(holder)
    return () => {
      holder.remove()
      delete figure.dataset.zoomable
    }
  }, [figure, zoomable])

  useEffect(() => {
    if (!zoomable || !image) return
    const onClick = () => setOpen(true)
    image.addEventListener('click', onClick)
    return () => image.removeEventListener('click', onClick)
  }, [image, zoomable])

  if (!zoomable || !image || !container) return null

  const caption = figure.querySelector('figcaption')?.textContent?.trim() ?? ''

  return (
    <>
      {createPortal(
        <button type="button" className="lr-frame-zoom" aria-label={t('frame.zoom')} onClick={() => setOpen(true)}>
          <Icon name="ZoomIn" size={15} />
        </button>,
        container,
      )}
      {createPortal(
        <Dialog open={open} onClose={() => setOpen(false)} className="lr-zoom" label={image.alt || caption || t('frame.zoom')}>
          <figure className="lr-zoom-figure" onClick={() => setOpen(false)}>
            <img src={image.currentSrc || image.src} alt={image.alt} />
            {caption && <figcaption>{caption}</figcaption>}
          </figure>
          <button type="button" className="lr-icon-button lr-zoom-close" aria-label={t('frame.close')} onClick={() => setOpen(false)} autoFocus>
            <Icon name="X" size={18} />
          </button>
        </Dialog>,
        document.body,
      )}
    </>
  )
}
