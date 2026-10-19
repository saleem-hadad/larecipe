import { Button, Loader, Perforation, useDelayedFlag } from '@larecipe/ui'
import { useEffect, useId, useRef, type ReactNode } from 'react'

const cx = (...values: Array<string | false | null | undefined>) => values.filter(Boolean).join(' ')

/** A screen's title block: a mono label, the title, an optional lead, actions at the end, a perforation under it. */
export function ScreenHeader({ label, title, lead, actions, dir }: { label: ReactNode; title: ReactNode; lead?: ReactNode; actions?: ReactNode; dir?: 'ltr' | 'rtl' }) {
  return (
    <header className="lra-screen-header">
      <div className="lra-screen-heading">
        <p className="lr-label">{label}</p>
        <h1 className="lra-title" dir={dir}>
          {title}
        </h1>
        {lead && <p className="lra-lead">{lead}</p>}
      </div>
      {actions && <div className="lra-actions">{actions}</div>}
      <Perforation className="lra-screen-perf" />
    </header>
  )
}

/** A hairline section with a mono label: structure without a box of its own. */
export function Panel({ label, actions, children, className, id }: { label?: ReactNode; actions?: ReactNode; children: ReactNode; className?: string; id?: string }) {
  const headingId = useId()

  return (
    <section className={cx('lra-panel', className)} aria-labelledby={label ? headingId : undefined} id={id}>
      {(label || actions) && (
        <div className="lra-panel-head">
          {label && (
            <h2 className="lr-label" id={headingId}>
              {label}
            </h2>
          )}
          {actions && <div className="lra-panel-actions">{actions}</div>}
        </div>
      )}
      {children}
    </section>
  )
}

/** The wait: printing dots, only once a wait has lasted 300 ms. */
export function Waiting({ loading, label = 'Loading' }: { loading: boolean; label?: string }) {
  const shown = useDelayedFlag(loading)
  return shown ? (
    <div className="lra-waiting">
      <Loader label={label} />
    </div>
  ) : null
}

export function Empty({ title, children, action }: { title: ReactNode; children?: ReactNode; action?: ReactNode }) {
  return (
    <div className="lra-empty">
      <p className="lra-empty-title">{title}</p>
      {children && <p className="lra-empty-text">{children}</p>}
      {action}
    </div>
  )
}

/** A problem said once, plainly, with what to do next. */
export function Problem({ children, onRetry }: { children: ReactNode; onRetry?: () => void }) {
  return (
    <div className="lra-problem" role="alert">
      <span className="lr-label" data-tone="danger">
        Problem
      </span>
      <p>{children}</p>
      {onRetry && (
        <Button variant="outline" size="sm" onClick={onRetry}>
          Try again
        </Button>
      )}
    </div>
  )
}

/** Two or three exclusive choices, as a radio group that looks like tabs. */
export function Segmented<T extends string>({ label, value, options, onChange, size = 'md' }: { label: string; value: T; options: { value: T; label: ReactNode }[]; onChange: (value: T) => void; size?: 'sm' | 'md' }) {
  const name = useId()

  return (
    <div className={cx('lra-segmented', size === 'sm' && 'lra-segmented-sm')} role="radiogroup" aria-label={label}>
      {options.map((option) => (
        <label key={option.value} className="lra-segment" data-checked={option.value === value || undefined}>
          <input type="radio" name={name} value={option.value} checked={option.value === value} onChange={() => onChange(option.value)} />
          <span>{option.label}</span>
        </label>
      ))}
    </div>
  )
}

/**
 * A modal question, on the native <dialog> (focus trap, Escape and inertness
 * come from the browser). Used for destructive or irreversible choices only.
 */
export function Dialog({ open, title, children, onClose, actions, wide = false }: { open: boolean; title: ReactNode; children: ReactNode; onClose: () => void; actions: ReactNode; wide?: boolean }) {
  const ref = useRef<HTMLDialogElement>(null)
  const titleId = useId()

  useEffect(() => {
    const dialog = ref.current
    if (!dialog) return
    if (open && !dialog.open) {
      typeof dialog.showModal === 'function' ? dialog.showModal() : dialog.setAttribute('open', '')
    }
    if (!open && dialog.open) {
      typeof dialog.close === 'function' ? dialog.close() : dialog.removeAttribute('open')
    }
  }, [open])

  return (
    <dialog ref={ref} className={cx('lra-dialog', wide && 'lra-dialog-wide')} aria-labelledby={titleId} onClose={onClose} onCancel={onClose}>
      {open && (
        <form
          method="dialog"
          className="lra-dialog-body"
          onSubmit={(event) => {
            event.preventDefault()
          }}
        >
          <h2 className="lra-dialog-title" id={titleId}>
            {title}
          </h2>
          <div className="lra-dialog-content">{children}</div>
          <div className="lra-dialog-actions">{actions}</div>
        </form>
      )}
    </dialog>
  )
}

/** A mono key and value, for provenance lines and ticket meta. */
export function Meta({ children, className }: { children: ReactNode; className?: string }) {
  return <span className={cx('lr-meta', className)}>{children}</span>
}
