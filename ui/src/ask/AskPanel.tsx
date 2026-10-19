import { useChat } from '@ai-sdk/react'
import { Button, Icon, Loader, useDelayedFlag, useLaRecipe } from '@larecipe/ui'
import { DefaultChatTransport, type UIMessage } from 'ai'
import { useEffect, useLayoutEffect, useMemo, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { useApp, useAppState } from '../app/context'
import { displayPath } from '../palette/search'
import { AnswerFeedback } from './AnswerFeedback'
import { Markdown } from './markdown'

interface Citation {
  url: string
  title: string
  path: string
}

interface Exchange {
  id: string
  question: string
  answer: UIMessage | null
}

function xsrfHeaders(): Record<string, string> {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1] ?? '') } : {}
}

function textOf(message: UIMessage | null): string {
  if (!message) return ''
  return message.parts
    .filter((part): part is Extract<UIMessage['parts'][number], { type: 'text' }> => part.type === 'text')
    .map((part) => part.text)
    .join('')
}

/**
 * The pages an answer cites, kept only when they point at this site's docs.
 * A citation is validated on the server too; this is the reader-side guard.
 */
export function citationsOf(message: UIMessage | null, basePath: string): Citation[] {
  if (!message) return []
  const seen = new Set<string>()
  const citations: Citation[] = []

  for (const part of message.parts) {
    if (part.type !== 'source-url') continue
    try {
      const url = new URL(part.url, window.location.href)
      const base = basePath.replace(/\/$/, '')
      if (url.origin !== window.location.origin) continue
      if (base !== '' && url.pathname !== base && !url.pathname.startsWith(`${base}/`)) continue
      const key = url.pathname + url.hash
      if (seen.has(key)) continue
      seen.add(key)
      const path = displayPath(url.href, basePath)
      citations.push({ url: url.pathname + url.search + url.hash, title: part.title || path, path })
    } catch {
      // Not a URL: never rendered.
    }
  }

  return citations
}

function exchangesOf(messages: UIMessage[]): Exchange[] {
  const exchanges: Exchange[] = []
  for (const message of messages) {
    if (message.role === 'user') exchanges.push({ id: message.id, question: textOf(message), answer: null })
    else if (message.role === 'assistant' && exchanges.length > 0) {
      const last = exchanges[exchanges.length - 1]
      if (last) last.answer = message
    }
  }
  return exchanges
}

function Waiting() {
  const show = useDelayedFlag(true)
  const { t } = useLaRecipe()
  return <div className="lr-ask-waiting">{show && <Loader label={t('common.loading')} />}</div>
}

/**
 * Ask the docs (⌘I): a 420 px side sheet. Answers stream as prose and always
 * show their sources as compact tickets. When an answer cites no page, the
 * panel says plainly that the docs may not cover it, and offers search. A
 * finished answer takes a thumbs up or down.
 */
export default function AskPanel() {
  const { boot, page, t } = useLaRecipe()
  const { actions } = useApp()
  const ask = useAppState((state) => state.ask)
  const [draft, setDraft] = useState('')
  const input = useRef<HTMLTextAreaElement>(null)
  const panel = useRef<HTMLElement>(null)
  const body = useRef<HTMLDivElement>(null)
  const context = useRef({ path: page.path, version: boot.version, locale: boot.locale.code })
  context.current = { path: page.path, version: boot.version, locale: boot.locale.code }
  const endpoint = boot.ask?.endpoint ?? ''

  const transport = useMemo(
    () =>
      new DefaultChatTransport<UIMessage>({
        api: endpoint,
        credentials: 'same-origin',
        headers: () => ({ Accept: 'text/event-stream', ...xsrfHeaders() }),
        body: () => ({ ...context.current }),
      }),
    [endpoint],
  )

  const { messages, sendMessage, status, stop, error, setMessages, clearError } = useChat({ transport })
  const exchanges = useMemo(() => exchangesOf(messages), [messages])
  const busy = status === 'submitted' || status === 'streaming'
  const lastQuestion = exchanges[exchanges.length - 1]?.question ?? draft

  // A question handed over from the Ask bar or the palette is sent right away.
  const handled = useRef(0)
  useEffect(() => {
    if (!ask.open || ask.nonce === handled.current) return
    handled.current = ask.nonce
    if (ask.question && ask.question.trim() !== '') {
      clearError()
      void sendMessage({ text: ask.question.trim() })
    }
  }, [ask.open, ask.nonce, ask.question, sendMessage, clearError])

  // Focus the field the moment the panel opens, so the first keystroke lands in it.
  useLayoutEffect(() => {
    if (ask.open) input.current?.focus()
  }, [ask.open, ask.nonce])

  useEffect(() => {
    if (!ask.open) return
    const onKey = (event: globalThis.KeyboardEvent) => {
      if (event.key === 'Escape' && panel.current?.contains(document.activeElement)) {
        event.preventDefault()
        actions.closeAsk()
      }
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [ask.open, actions])

  // Keep the newest answer in view while it streams.
  useEffect(() => {
    const node = body.current
    if (node) node.scrollTop = node.scrollHeight
  }, [messages, status])

  function submit(event?: FormEvent) {
    event?.preventDefault()
    const question = draft.trim()
    if (question === '' || busy) return
    clearError()
    setDraft('')
    void sendMessage({ text: question })
  }

  function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
      event.preventDefault()
      submit()
    }
  }

  return (
    <section ref={panel} className="lr-ask-panel" role="dialog" aria-modal="false" aria-labelledby="lr-ask-title" hidden={!ask.open}>
      <header className="lr-ask-head">
        <span className="lr-label lr-ask-head-label">{t('ask.label')}</span>
        <h2 className="lr-ask-title" id="lr-ask-title">
          {t('ask.title')}
        </h2>
        <button type="button" className="lr-icon-button" aria-label={t('ask.close')} onClick={() => actions.closeAsk()}>
          <Icon name="X" size={17} />
        </button>
      </header>

      <div className="lr-ask-body" ref={body} aria-live="polite" aria-busy={busy}>
        {exchanges.length === 0 ? (
          <p className="lr-ask-intro">{t('ask.intro')}</p>
        ) : (
          <ol className="lr-ask-thread">
            {exchanges.map((exchange, index) => {
              const last = index === exchanges.length - 1
              const answer = textOf(exchange.answer)
              const citations = citationsOf(exchange.answer, boot.set.base_path)
              const finished = !last || status === 'ready'
              return (
                <li key={exchange.id} className="lr-ask-exchange">
                  <p className="lr-ask-question">{exchange.question}</p>
                  {answer === '' && last && busy ? (
                    <Waiting />
                  ) : (
                    <div className="lr-ask-answer">
                      <Markdown source={answer} />
                    </div>
                  )}
                  {citations.length > 0 && (
                    <div className="lr-ask-sources">
                      <p className="lr-label">{t('ask.sources')}</p>
                      <ul className="lr-ask-sources-list">
                        {citations.map((citation, citationIndex) => (
                          <li key={citation.url}>
                            <a className="lr-citation" href={citation.url} data-print={last ? '' : undefined}>
                              <span className="lr-citation-index">{String(citationIndex + 1).padStart(2, '0')}</span>
                              <span className="lr-citation-title">{citation.title}</span>
                              <span className="lr-citation-path">{citation.path}</span>
                            </a>
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                  {finished && answer !== '' && citations.length === 0 && (
                    <div className="lr-ask-note" role="note">
                      {t('ask.not_covered')}
                      <Button variant="outline" size="sm" icon={<Icon name="Search" size={14} />} onClick={() => actions.openPalette(exchange.question)}>
                        {t('ask.search_instead', { query: exchange.question.length > 40 ? `${exchange.question.slice(0, 40)}…` : exchange.question })}
                      </Button>
                    </div>
                  )}
                  {finished && answer !== '' && <AnswerFeedback question={exchange.question} />}
                </li>
              )
            })}
          </ol>
        )}
        {error && (
          <div className="lr-ask-note" role="alert">
            {t('ask.error')}
            {lastQuestion && (
              <Button variant="outline" size="sm" icon={<Icon name="Search" size={14} />} onClick={() => actions.openPalette(lastQuestion)}>
                {t('ask.search_instead', { query: lastQuestion.length > 40 ? `${lastQuestion.slice(0, 40)}…` : lastQuestion })}
              </Button>
            )}
          </div>
        )}
      </div>

      <div className="lr-ask-composer">
        <form className="lr-ask-form" onSubmit={submit}>
          <label className="lr-sr" htmlFor="lr-ask-input">
            {t('ask.placeholder')}
          </label>
          <textarea
            ref={input}
            id="lr-ask-input"
            className="lr-ask-input"
            rows={1}
            placeholder={t('ask.placeholder')}
            value={draft}
            onChange={(event) => setDraft(event.target.value)}
            onKeyDown={onKeyDown}
          />
          {busy ? (
            <button type="button" className="lr-ask-send" aria-label={t('ask.stop')} onClick={() => void stop()}>
              <Icon name="Square" size={14} fill="currentColor" />
            </button>
          ) : (
            <button type="submit" className="lr-ask-send" aria-label={t('ask.send')} disabled={draft.trim() === ''}>
              <Icon name="ArrowUp" size={16} />
            </button>
          )}
        </form>
        {exchanges.length > 0 && (
          <div className="lr-ask-composer-meta">
            <span />
            <Button
              variant="ghost"
              size="sm"
              icon={<Icon name="RotateCcw" size={13} />}
              onClick={() => {
                void stop()
                setMessages([])
                clearError()
                input.current?.focus()
              }}
            >
              {t('ask.new')}
            </Button>
          </div>
        )}
      </div>
    </section>
  )
}
