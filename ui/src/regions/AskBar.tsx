import { Icon, useLaRecipe } from '@larecipe/ui'
import { useState, type FormEvent } from 'react'
import { useApp } from '../app/context'
import { shortcutLabel } from './Header'

/**
 * The docked Ask bar at the bottom of the article column (desktop). It never
 * floats over the content as a bubble: it is a field that hands the question
 * to the Ask panel.
 */
export function AskBar() {
  const { t } = useLaRecipe()
  const { actions } = useApp()
  const [question, setQuestion] = useState('')

  function submit(event: FormEvent) {
    event.preventDefault()
    const value = question.trim()
    if (value === '') return
    actions.openAsk(value)
    setQuestion('')
  }

  return (
    <form className="lr-ask-bar lr-js-only" onSubmit={submit} data-lr-region="AskBar">
      <label className="lr-ask-bar-label" htmlFor="lr-ask-bar-input">
        {t('ask.label')}
      </label>
      <input
        id="lr-ask-bar-input"
        className="lr-ask-bar-input"
        type="text"
        autoComplete="off"
        enterKeyHint="send"
        placeholder={t('ask.placeholder')}
        aria-label={t('ask.title')}
        value={question}
        onChange={(event) => setQuestion(event.target.value)}
      />
      <kbd className="lr-kbd">{shortcutLabel('I')}</kbd>
      <button type="submit" className="lr-ask-bar-send" aria-label={t('ask.send')} disabled={question.trim() === ''}>
        <Icon name="ArrowUp" size={16} />
      </button>
    </form>
  )
}
