import { Button, Icon, registry, request, Slot, Textarea, useLaRecipe } from '@larecipe/ui'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { feedbackEnabled, useApp } from '../app/context'
import { Insert } from '../app/Insert'
import { Mark } from './Header'

type FeedbackState = { step: 'ask' } | { step: 'comment'; helpful: boolean } | { step: 'sending'; helpful: boolean } | { step: 'done' } | { step: 'error'; helpful: boolean }

/**
 * "Was this page helpful?" with an optional comment. One POST per answer to the
 * feedback endpoint; LaRecipe stores nothing, the host's listeners and
 * analytics receive it.
 */
export function Feedback() {
  const { boot, page, t } = useLaRecipe()
  const { actions } = useApp()
  const [state, setState] = useState<FeedbackState>({ step: 'ask' })
  const [comment, setComment] = useState('')
  const pending = useRef<{ helpful: boolean } | null>(null)
  const endpoint = boot.feedback?.endpoint ?? null
  const context = useRef({ path: page.path, version: boot.version, locale: boot.locale.code })
  context.current = { path: page.path, version: boot.version, locale: boot.locale.code }

  // A vote left without "Send" or "Skip" is still sent when the reader moves on.
  useEffect(
    () => () => {
      if (pending.current && endpoint) {
        const detail = { ...context.current, helpful: pending.current.helpful }
        document.dispatchEvent(new CustomEvent('larecipe:feedback', { detail }))
        void request(endpoint, { method: 'POST', body: detail }).catch(() => undefined)
      }
    },
    [endpoint],
  )

  useEffect(() => {
    setState({ step: 'ask' })
    setComment('')
  }, [page.path])

  if (!endpoint) return null

  async function send(helpful: boolean, text: string | null) {
    pending.current = null
    setState({ step: 'sending', helpful })
    const detail = { ...context.current, helpful, ...(text ? { comment: text } : {}) }
    // Analytics integrations configured in larecipe.json listen for this event.
    document.dispatchEvent(new CustomEvent('larecipe:feedback', { detail }))
    try {
      const response = await request(endpoint as string, { method: 'POST', body: detail })
      if (response.ok) {
        setState({ step: 'done' })
        actions.announce(t('feedback.thanks'))
      } else {
        setState({ step: 'error', helpful })
      }
    } catch {
      setState({ step: 'error', helpful })
    }
  }

  function vote(helpful: boolean) {
    pending.current = { helpful }
    setState({ step: 'comment', helpful })
  }

  function submit(event: FormEvent) {
    event.preventDefault()
    if (state.step === 'comment' || state.step === 'error') void send(state.helpful, comment.trim() || null)
  }

  if (state.step === 'done') {
    return (
      <div className="lr-feedback lr-js-only">
        <p className="lr-feedback-status" role="status">
          {t('feedback.thanks')}
        </p>
      </div>
    )
  }

  const chosen = state.step === 'ask' ? null : state.helpful

  return (
    <div className="lr-feedback lr-js-only" role="group" aria-labelledby="lr-feedback-question">
      <p className="lr-feedback-question" id="lr-feedback-question">
        {t('feedback.question')}
      </p>
      <div className="lr-feedback-choices">
        <Button variant="outline" size="sm" aria-pressed={chosen === true} icon={<Icon name="ThumbsUp" size={15} />} onClick={() => vote(true)} disabled={state.step === 'sending'}>
          {t('feedback.yes')}
        </Button>
        <Button variant="outline" size="sm" aria-pressed={chosen === false} icon={<Icon name="ThumbsDown" size={15} />} onClick={() => vote(false)} disabled={state.step === 'sending'}>
          {t('feedback.no')}
        </Button>
      </div>
      {state.step !== 'ask' && (
        <form className="lr-feedback-form" onSubmit={submit}>
          <label className="lr-sr" htmlFor="lr-feedback-comment">
            {t('feedback.comment')}
          </label>
          <Textarea id="lr-feedback-comment" rows={3} maxLength={2000} placeholder={t('feedback.comment')} value={comment} onChange={(event) => setComment(event.target.value)} />
          {state.step === 'error' && (
            <p className="lr-field-error" role="alert">
              {t('feedback.error')}
            </p>
          )}
          <div className="lr-feedback-form-actions">
            <Button type="submit" variant="ink" size="sm" loading={state.step === 'sending'}>
              {t('feedback.send')}
            </Button>
            <Button variant="ghost" size="sm" disabled={state.step === 'sending'} onClick={() => void send(state.helpful, null)}>
              {t('feedback.skip')}
            </Button>
          </div>
        </form>
      )}
    </div>
  )
}

export function Pager() {
  const { page, t } = useLaRecipe()
  if (!page.previous && !page.next) return null

  return (
    <nav className="lr-pager" aria-label={t('footer.pager')}>
      {page.previous && (
        <a className="lr-pager-link lr-pager-prev" rel="prev" href={page.previous.url}>
          <span className="lr-pager-dir lr-label">
            <Icon name="ArrowLeft" size={12} />
            {t('footer.previous')}
          </span>
          <span className="lr-pager-title">{page.previous.title}</span>
        </a>
      )}
      {page.next && (
        <a className="lr-pager-link lr-pager-next" rel="next" href={page.next.url}>
          <span className="lr-pager-dir lr-label">
            {t('footer.next')}
            <Icon name="ArrowRight" size={12} />
          </span>
          <span className="lr-pager-title">{page.next.title}</span>
        </a>
      )}
    </nav>
  )
}

export function PageFooter() {
  const { boot, page, t } = useLaRecipe()
  const showFeedback = feedbackEnabled(boot)
  if (!showFeedback && !page.previous && !page.next && !page.edit_url) return null

  return (
    <footer className="lr-page-footer" data-lr-region="PageFooter">
      {showFeedback && <Slot name="Feedback" component={Feedback} />}
      <Pager />
      {page.edit_url && (
        <div className="lr-page-meta">
          <a className="lr-edit-link" href={page.edit_url} rel="noopener">
            <Icon name="SquarePen" size={15} />
            {t('footer.edit')}
          </a>
        </div>
      )}
    </footer>
  )
}

export function Footer() {
  const { boot, page, t } = useLaRecipe()
  const links = boot.set.footer?.links ?? []
  const text = boot.set.footer?.text ?? null
  const credit = boot.set.credit !== false
  const optOut = page.inserts
  const hasInsert = (boot.inserts.footer ?? registry.inserts.footer ?? []).length > 0 && optOut !== false && optOut?.footer !== false

  if (links.length === 0 && !text && !credit && !hasInsert) return null

  return (
    <footer className="lr-footer" data-lr-region="Footer">
      <div className="lr-footer-inner">
        <Insert point="footer" />
        {links.length > 0 && (
          <div className="lr-footer-links">
            {links.map((link) => (
              <a key={link.href} className="lr-footer-link" href={link.href}>
                {link.label}
              </a>
            ))}
          </div>
        )}
        {text && <p className="lr-footer-text">{text}</p>}
        {credit && (
          <a className="lr-credit" href="https://larecipe.saleem.dev" rel="noopener">
            <Mark size={14} />
            {t('footer.credit')}
          </a>
        )}
      </div>
    </footer>
  )
}
