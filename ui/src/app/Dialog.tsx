import { useEffect, useLayoutEffect, useRef, type ReactNode } from 'react'

/**
 * A modal on the native <dialog>: the top layer, an inert page behind it,
 * Escape to close and focus returned to where it was. Clicking the backdrop
 * closes it too.
 */
export function Dialog({
  open,
  onClose,
  className,
  labelledBy,
  label,
  children,
}: {
  open: boolean
  onClose: () => void
  className?: string
  labelledBy?: string
  label?: string
  children: ReactNode
}) {
  const ref = useRef<HTMLDialogElement>(null)
  const onCloseRef = useRef(onClose)
  onCloseRef.current = onClose

  useLayoutEffect(() => {
    const dialog = ref.current
    if (!dialog) return
    if (open && !dialog.open) {
      try {
        dialog.showModal()
      } catch {
        dialog.setAttribute('open', '')
      }
    } else if (!open && dialog.open) {
      dialog.close()
    }
  }, [open])

  useEffect(() => {
    const dialog = ref.current
    if (!dialog) return
    const handleClose = () => onCloseRef.current()
    const handleClick = (event: MouseEvent) => {
      if (event.target === dialog) onCloseRef.current()
    }
    dialog.addEventListener('close', handleClose)
    dialog.addEventListener('click', handleClick)
    return () => {
      dialog.removeEventListener('close', handleClose)
      dialog.removeEventListener('click', handleClick)
    }
  }, [])

  return (
    <dialog ref={ref} className={['lr-dialog', className].filter(Boolean).join(' ')} aria-labelledby={labelledBy} aria-label={label}>
      {open && children}
    </dialog>
  )
}
