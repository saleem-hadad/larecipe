import {
  Children,
  cloneElement,
  forwardRef,
  isValidElement,
  useEffect,
  useId,
  useState,
  type AnchorHTMLAttributes,
  type ButtonHTMLAttributes,
  type HTMLAttributes,
  type InputHTMLAttributes,
  type ReactElement,
  type ReactNode,
  type TextareaHTMLAttributes,
} from 'react'
import { Icon } from './icons'

/**
 * Design-system primitives for custom components. They render the same class
 * names as LaRecipe's own UI, so a host's component follows the set's theme,
 * dark mode and right-to-left layout without extra CSS.
 */

const cx = (...values: Array<string | false | null | undefined>) => values.filter(Boolean).join(' ')

/** True once a flag has stayed on for `delay` ms. Nothing under 300 ms shows a loader. */
export function useDelayedFlag(flag: boolean, delay = 300): boolean {
  const [shown, setShown] = useState(false)

  useEffect(() => {
    if (!flag) {
      setShown(false)
      return
    }
    const timer = window.setTimeout(() => setShown(true), delay)
    return () => window.clearTimeout(timer)
  }, [flag, delay])

  return shown
}

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'ink'

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant
  size?: 'md' | 'sm'
  /** Shows the printing loader after 300 ms and disables the button. */
  loading?: boolean
  /** An icon before the label. */
  icon?: ReactNode
}

/** One filled violet (primary) button per screen; reach for secondary, outline or ghost first. */
export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'primary', size = 'md', loading = false, icon, className, children, disabled, type = 'button', ...props },
  ref,
) {
  const showLoader = useDelayedFlag(loading)

  return (
    <button
      ref={ref}
      type={type}
      {...props}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      data-loading={loading || undefined}
      className={cx('lr-button', `lr-button-${variant}`, size === 'sm' && 'lr-button-sm', className)}
    >
      {showLoader ? <Loader /> : icon}
      {children}
    </button>
  )
})

export interface LinkButtonProps extends AnchorHTMLAttributes<HTMLAnchorElement> {
  variant?: ButtonVariant
  size?: 'md' | 'sm'
  icon?: ReactNode
}

export const LinkButton = forwardRef<HTMLAnchorElement, LinkButtonProps>(function LinkButton({ variant = 'secondary', size = 'md', icon, className, children, ...props }, ref) {
  return (
    <a ref={ref} {...props} className={cx('lr-button', `lr-button-${variant}`, size === 'sm' && 'lr-button-sm', className)}>
      {icon}
      {children}
    </a>
  )
})

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input({ className, ...props }, ref) {
  return <input ref={ref} {...props} className={cx('lr-input', className)} />
})

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(function Textarea({ className, ...props }, ref) {
  return <textarea ref={ref} {...props} className={cx('lr-textarea', className)} />
})

export interface FieldProps {
  label: ReactNode
  /** Help text under the control. */
  hint?: ReactNode
  /** A validation message; marks the control invalid and announces it. */
  error?: ReactNode
  /** Visually hide the label (it stays the control's accessible name). */
  hideLabel?: boolean
  className?: string
  children: ReactElement<Record<string, unknown>>
}

/** A label, one control, and its hint or error, wired together for assistive tech. */
export function Field({ label, hint, error, hideLabel = false, className, children }: FieldProps) {
  const generated = useId()
  const child = Children.only(children)
  const id = (isValidElement(child) && typeof child.props.id === 'string' ? child.props.id : null) ?? `lr-field-${generated}`
  const hintId = hint ? `${id}-hint` : undefined
  const errorId = error ? `${id}-error` : undefined
  const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined

  return (
    <div className={cx('lr-field', className)}>
      <label className={cx('lr-field-label', hideLabel && 'lr-sr')} htmlFor={id}>
        {label}
      </label>
      {cloneElement(child, { id, 'aria-describedby': describedBy, 'aria-invalid': error ? true : undefined })}
      {hint && (
        <p className="lr-field-hint" id={hintId}>
          {hint}
        </p>
      )}
      {error && (
        <p className="lr-field-error" id={errorId} role="alert">
          {error}
        </p>
      )}
    </div>
  )
}

export type CalloutType = 'note' | 'tip' | 'important' | 'warning' | 'danger' | 'caution' | 'info' | 'success'

/** The same markup as the built-in <Callout>: a colored label on a neutral surface. */
export function Callout({ type = 'note', title, children, className }: { type?: CalloutType; title?: ReactNode; children?: ReactNode; className?: string }) {
  return (
    <aside className={cx('lr-callout', className)} data-type={type} role="note">
      <div className="lr-callout-label">{title ?? type.charAt(0).toUpperCase() + type.slice(1)}</div>
      <div className="lr-callout-body">{children}</div>
    </aside>
  )
}

export type BadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger'

export function Badge({ tone = 'neutral', variant = 'outline', className, children, ...props }: HTMLAttributes<HTMLSpanElement> & { tone?: BadgeTone; variant?: 'outline' | 'surface' }) {
  return (
    <span {...props} className={cx('lr-badge', className)} data-tone={tone === 'neutral' ? undefined : tone} data-variant={variant === 'surface' ? 'surface' : undefined}>
      {children}
    </span>
  )
}

export interface CardProps {
  title: ReactNode
  href?: string
  children?: ReactNode
  className?: string
}

/** Hairline, flat at rest; a linked card darkens its border on hover and shows an arrow. */
export function Card({ title, href, children, className }: CardProps) {
  const body = (
    <>
      <div className="lr-card-title">{title}</div>
      {children !== undefined && <div className="lr-card-body">{children}</div>}
    </>
  )

  return href ? (
    <a className={cx('lr-card', className)} href={href}>
      {body}
    </a>
  ) : (
    <div className={cx('lr-card', className)}>{body}</div>
  )
}

export function Kbd({ children, className, ...props }: HTMLAttributes<HTMLElement>) {
  return (
    <kbd {...props} className={cx('lr-kbd', className)}>
      {children}
    </kbd>
  )
}

/**
 * Waiting: three mono dots that print left to right, one every 160 ms, like a
 * ticket printer. Use it only for waits longer than 300 ms (see useDelayedFlag).
 */
export function Loader({ label, className }: { label?: string; className?: string }) {
  return (
    <span className={cx('lr-loader', className)} role={label ? 'status' : undefined} aria-label={label} aria-hidden={label ? undefined : true}>
      <span>·</span>
      <span>·</span>
      <span>·</span>
    </span>
  )
}

/** The perforated rule that separates metadata from content. */
export function Perforation({ className }: { className?: string }) {
  return <div className={cx('lr-perf', className)} aria-hidden="true" />
}

export interface TicketProps {
  /** The mono header label ("Last change"). */
  label: ReactNode
  /** The ticket or revision number, shown after the label. */
  number?: ReactNode
  /** Time of the change, already formatted. */
  time?: ReactNode
  dateTime?: string
  fresh?: boolean
  /** One sentence: what changed and why. */
  children: ReactNode
  /** Mono provenance under the summary (hash, pages, lines, approval). */
  meta?: ReactNode
  /** A single violet action. */
  action?: { label: ReactNode; href: string }
  /** Play the print motion once (for tickets that arrive while the reader is here). */
  print?: boolean
  className?: string
  labelId?: string
}

/**
 * The ticket: the unit of change. A ticket carries real data; never use it as a
 * decorative card, and never put the bitten edge on anything that is not a change.
 */
export function Ticket({ label, number, time, dateTime, fresh = false, children, meta, action, print = false, className, labelId }: TicketProps) {
  return (
    <article className={cx('lr-ticket', className)} data-print={print || undefined} aria-labelledby={labelId}>
      <header className="lr-ticket-head">
        <span id={labelId}>
          {label}
          {number !== undefined && number !== null && <span className="lr-hash">#{number}</span>}
        </span>
        {time !== undefined && time !== null && (
          <span>
            {fresh && <span className="lr-fresh-dot" aria-hidden="true" />}
            <time className="lr-hash" dateTime={dateTime}>
              {time}
            </time>
          </span>
        )}
      </header>
      <Perforation />
      <p className="lr-ticket-summary">{children}</p>
      {meta && <div className="lr-ticket-meta">{meta}</div>}
      {action && (
        <a className="lr-ticket-action" href={action.href} rel="noopener">
          {action.label}
          <Icon name="ArrowRight" size={12} />
        </a>
      )}
    </article>
  )
}
