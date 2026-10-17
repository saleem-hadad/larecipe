import { Icon, request, useLaRecipe } from '@larecipe/ui'
import { useState } from 'react'
import { feedbackEnabled } from '../app/context'

type Vote = { step: 'ask' } | { step: 'sending'; helpful: boolean } | { step: 'done' } | { step: 'error'; helpful: boolean }

/**
 * Thumbs up or down under a finished Ask answer. The vote goes to the same
 * feedback endpoint as the page widget, with the question the answer was for,
 * so the server tags it as Ask feedback (FeedbackSubmitted, source "ask").
 * LaRecipe stores nothing; the host's listeners and analytics receive it.
 */
export function AnswerFeedback({ question }: { question: string }) {
  const { boot, page, t } = useLaRecipe()
  const [vote, setVote] = useState<Vote>({ step: 'ask' })
  const endpoint = boot.feedback?.endpoint ?? null

  if (!feedbackEnabled(boot) || !endpoint) return null

  if (vote.step === 'done') {
    return (
      <p className="lr-ask-feedback-status" role="status">
        {t('feedback.thanks')}
      </p>
    )
  }

  async function send(helpful: boolean) {
    setVote({ step: 'sending', helpful })
    const detail = { path: page.path, version: boot.version, locale: boot.locale.code, helpful, question }
    // Analytics integrations configured in larecipe.json listen for this event.
    document.dispatchEvent(new CustomEvent('larecipe:feedback', { detail: { ...detail, source: 'ask' } }))
    try {
      const response = await request(endpoint as string, { method: 'POST', body: detail })
      setVote(response.ok ? { step: 'done' } : { step: 'error', helpful })
    } catch {
      setVote({ step: 'error', helpful })
    }
  }

  const chosen = vote.step === 'ask' ? null : vote.helpful

  return (
    <div className="lr-ask-feedback" role="group" aria-label={t('ask.rate')}>
      <button
        type="button"
        className="lr-icon-button"
        aria-label={t('ask.helpful')}
        aria-pressed={chosen === true}
        disabled={vote.step === 'sending'}
        onClick={() => void send(true)}
      >
        <Icon name="ThumbsUp" size={15} />
      </button>
      <button
        type="button"
        className="lr-icon-button"
        aria-label={t('ask.not_helpful')}
        aria-pressed={chosen === false}
        disabled={vote.step === 'sending'}
        onClick={() => void send(false)}
      >
        <Icon name="ThumbsDown" size={15} />
      </button>
      {vote.step === 'error' && (
        <span className="lr-field-error" role="alert">
          {t('feedback.error')}
        </span>
      )}
    </div>
  )
}
