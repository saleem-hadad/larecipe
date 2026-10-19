import { Icon, Loader, useDelayedFlag, useLaRecipe, type BootPayload, type IconName } from '@larecipe/ui'
import { Fragment, useCallback, useEffect, useMemo, useRef, useState, type KeyboardEvent, type ReactNode } from 'react'
import { askEnabled, useApp, useAppState } from '../app/context'
import { Dialog } from '../app/Dialog'
import { createSearchReporter } from './report'
import { highlightRuns, prepareSearch, suggestedPages, type SearchHit, type Searcher } from './search'

/** Warm the index after the first interaction, so the palette opens with results ready. */
export function warm(boot: BootPayload): void {
  void prepareSearch(boot)
}

type Option =
  | { id: string; kind: 'ask'; query: string }
  | { id: string; kind: 'hit'; hit: SearchHit }
  | { id: string; kind: 'action'; label: string; icon: IconName; run: () => void; hint?: string }

interface Group {
  id: string
  label: string
  options: Option[]
}

function Highlight({ text, query, terms }: { text: string; query: string; terms?: string[] }) {
  return (
    <>
      {highlightRuns(text, query, terms).map((run, index) => (run.match ? <mark key={index}>{run.text}</mark> : <Fragment key={index}>{run.text}</Fragment>))}
    </>
  )
}

/**
 * The command palette (⌘K and /): pages, sections and actions from one field,
 * answered in the browser as the reader types. When Ask is on, the first row
 * hands the query to the Ask panel. Fully keyboard driven.
 */
export default function CommandPalette() {
  const { boot, t, navigate } = useLaRecipe()
  const { actions } = useApp()
  const palette = useAppState((state) => state.palette)
  const themePreference = useAppState((state) => state.theme.preference)
  const [query, setQuery] = useState(palette.query)
  const [hits, setHits] = useState<SearchHit[]>([])
  const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>('idle')
  const [active, setActive] = useState(0)
  const searcher = useRef<Searcher | null>(null)
  const input = useRef<HTMLInputElement>(null)
  const list = useRef<HTMLDivElement>(null)
  const showLoader = useDelayedFlag(status === 'loading')
  const ask = askEnabled(boot)
  const reportUrl = boot.search?.report_url ?? null
  const reporter = useMemo(() => createSearchReporter(reportUrl), [reportUrl])

  useEffect(() => {
    if (palette.open) setQuery(palette.query)
  }, [palette.open, palette.query])

  useEffect(() => {
    if (!palette.open) return
    let cancelled = false
    if (!searcher.current) setStatus('loading')
    prepareSearch(boot)
      .then((ready) => {
        if (cancelled) return
        searcher.current = ready
        setStatus('ready')
      })
      .catch(() => !cancelled && setStatus('error'))
    return () => {
      cancelled = true
    }
  }, [palette.open, boot])

  // Results as you type; the server driver is debounced, the client index is not.
  useEffect(() => {
    const ready = searcher.current
    const trimmed = query.trim()
    if (!ready || status !== 'ready' || trimmed === '') {
      setHits([])
      return
    }
    const controller = new AbortController()
    const delay = ready.mode === 'server' ? 140 : 0
    const timer = window.setTimeout(() => {
      ready
        .search(trimmed, controller.signal)
        .then((results) => {
          if (controller.signal.aborted) return
          setHits(results)
          // The server driver's endpoint reports its own searches.
          if (ready.mode !== 'server') reporter.record(trimmed, results.length)
        })
        .catch(() => !controller.signal.aborted && setHits([]))
    }, delay)
    return () => {
      controller.abort()
      window.clearTimeout(timer)
    }
  }, [query, status, reporter])

  // A search counts when the reader stops typing; closing the palette or leaving the page settles it.
  useEffect(() => {
    if (!palette.open) reporter.flush()
  }, [palette.open, reporter])

  // The palette unmounts when it closes.
  useEffect(() => () => reporter.flush(), [reporter])

  useEffect(() => {
    const flush = () => document.visibilityState === 'hidden' && reporter.flush()
    document.addEventListener('visibilitychange', flush)
    return () => document.removeEventListener('visibilitychange', flush)
  }, [reporter])

  const close = useCallback(() => actions.closePalette(), [actions])

  const go = useCallback(
    (url: string, newTab = false) => {
      close()
      if (newTab) window.open(url, '_blank', 'noopener')
      else navigate(url)
    },
    [close, navigate],
  )

  const groups = useMemo<Group[]>(() => {
    const trimmed = query.trim()
    const lower = trimmed.toLowerCase()
    const result: Group[] = []

    const actionOptions: Option[] = []
    if (ask) actionOptions.push({ id: 'action-ask', kind: 'action', label: t('palette.ask_docs'), icon: 'MessageCircle', run: () => actions.openAsk() })
    for (const version of boot.set.versions) {
      if (version.id === boot.version) continue
      actionOptions.push({ id: `action-version-${version.id}`, kind: 'action', label: t('palette.switch_version', { version: version.label }), icon: 'ArrowRight', hint: version.label, run: () => go(version.url) })
    }
    actionOptions.push(
      {
        id: 'action-copy',
        kind: 'action',
        label: t('palette.copy_page'),
        icon: 'Copy',
        run: () => {
          void actions.copyMarkdown().then((ok) => ok && actions.announce(t('actions.copied')))
          close()
        },
      },
      { id: 'action-markdown', kind: 'action', label: t('palette.view_markdown'), icon: 'FileText', run: () => go(boot.page.markdown_url) },
      {
        id: 'action-theme',
        kind: 'action',
        label: t('palette.toggle_theme'),
        icon: themePreference === 'dark' ? 'Sun' : 'Moon',
        run: () => {
          actions.setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark')
          close()
        },
      },
    )

    if (trimmed === '') {
      const pages = suggestedPages(boot)
      if (pages.length > 0) result.push({ id: 'pages', label: t('palette.pages'), options: pages.map((hit) => ({ id: `hit-${hit.id}`, kind: 'hit', hit })) })
      result.push({ id: 'actions', label: t('palette.actions'), options: actionOptions })
      return result
    }

    if (ask) result.push({ id: 'ask', label: '', options: [{ id: 'ask', kind: 'ask', query: trimmed }] })

    const pages = hits.filter((hit) => hit.kind === 'page')
    const sections = hits.filter((hit) => hit.kind === 'section')
    if (pages.length > 0) result.push({ id: 'pages', label: t('palette.pages'), options: pages.map((hit) => ({ id: `hit-${hit.id}`, kind: 'hit', hit })) })
    if (sections.length > 0) result.push({ id: 'sections', label: t('palette.sections'), options: sections.map((hit) => ({ id: `hit-${hit.id}`, kind: 'hit', hit })) })

    const matchingActions = actionOptions.filter((option) => option.kind === 'action' && option.label.toLowerCase().includes(lower))
    if (matchingActions.length > 0) result.push({ id: 'actions', label: t('palette.actions'), options: matchingActions })

    return result
  }, [query, hits, ask, boot, t, actions, go, close, themePreference])

  const options = useMemo(() => groups.flatMap((group) => group.options), [groups])

  // The first real result is selected; the Ask row only when nothing matched.
  useEffect(() => {
    const first = options.findIndex((option) => option.kind !== 'ask')
    setActive(query.trim() !== '' && first >= 0 && hits.length > 0 ? first : 0)
  }, [options, query, hits.length])

  useEffect(() => {
    const node = list.current?.querySelector<HTMLElement>(`[data-index="${active}"]`)
    node?.scrollIntoView({ block: 'nearest' })
  }, [active])

  function run(option: Option | undefined, newTab = false) {
    if (!option) return
    if (option.kind === 'ask') actions.openAsk(option.query)
    else if (option.kind === 'hit') go(option.hit.url, newTab)
    else option.run()
  }

  function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.nativeEvent.isComposing) return
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActive((index) => (options.length === 0 ? 0 : (index + 1) % options.length))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActive((index) => (options.length === 0 ? 0 : (index - 1 + options.length) % options.length))
    } else if (event.key === 'Home' && event.ctrlKey) {
      event.preventDefault()
      setActive(0)
    } else if (event.key === 'End' && event.ctrlKey) {
      event.preventDefault()
      setActive(Math.max(0, options.length - 1))
    } else if (event.key === 'Enter') {
      event.preventDefault()
      run(options[active], event.metaKey || event.ctrlKey)
    } else if (event.key === 'Tab') {
      event.preventDefault()
    }
  }

  const trimmed = query.trim()
  let index = -1
  const optionId = (option: Option) => `lr-palette-${option.id}`
  const activeOption = options[active]

  const body: ReactNode =
    status === 'error' && trimmed !== '' ? (
      <p className="lr-palette-status">{t('palette.error')}</p>
    ) : status === 'loading' && trimmed !== '' ? (
      <p className="lr-palette-status" role="status">
        {showLoader && (
          <>
            <Loader />
            {t('palette.loading')}
          </>
        )}
      </p>
    ) : (
      <>
        {groups.map((group) => (
          <ul key={group.id} className="lr-palette-group" role="group" aria-labelledby={group.label ? `lr-palette-group-${group.id}` : undefined} aria-label={group.label ? undefined : t('palette.ask_label')}>
            {group.label && (
              <li role="presentation" className="lr-label lr-palette-group-label" id={`lr-palette-group-${group.id}`}>
                {group.label}
              </li>
            )}
            {group.options.map((option) => {
              index += 1
              const position = index
              const selected = position === active
              return (
                <li
                  key={option.id}
                  id={optionId(option)}
                  role="option"
                  aria-selected={selected}
                  data-index={position}
                  className={`lr-palette-option${option.kind === 'ask' ? ' lr-palette-ask' : ''}`}
                  onPointerMove={() => !selected && setActive(position)}
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={(event) => run(option, event.metaKey || event.ctrlKey)}
                >
                  {option.kind === 'ask' ? (
                    <>
                      <span className="lr-palette-ask-label">{t('palette.ask_label')}</span>
                      <span className="lr-palette-option-text">
                        <span className="lr-palette-option-title">{t('palette.ask', { query: option.query })}</span>
                      </span>
                    </>
                  ) : option.kind === 'hit' ? (
                    <>
                      <span className="lr-palette-option-icon">
                        <Icon name={option.hit.kind === 'section' ? 'Hash' : 'FileText'} size={15} />
                      </span>
                      <span className="lr-palette-option-text">
                        <span className="lr-palette-option-title">
                          {option.hit.page && (
                            <span className="lr-palette-crumb">
                              {option.hit.page}
                              {' / '}
                            </span>
                          )}
                          <Highlight text={option.hit.title} query={trimmed} terms={option.hit.terms} />
                        </span>
                        <span className="lr-palette-option-path">{option.hit.path}</span>
                      </span>
                    </>
                  ) : (
                    <>
                      <span className="lr-palette-option-icon">
                        <Icon name={option.icon} size={15} />
                      </span>
                      <span className="lr-palette-option-text">
                        <span className="lr-palette-option-title">
                          <Highlight text={option.label} query={trimmed} />
                        </span>
                      </span>
                    </>
                  )}
                  <Icon name="CornerDownLeft" size={14} className="lr-palette-option-enter" />
                </li>
              )
            })}
          </ul>
        ))}
        {trimmed !== '' && status === 'ready' && hits.length === 0 && (
          <p className="lr-palette-status" role="status">
            {t('palette.empty', { query: trimmed })}
          </p>
        )}
      </>
    )

  return (
    <Dialog open={palette.open} onClose={close} className="lr-palette" label={t('palette.title')}>
      <div className="lr-palette-panel">
        <div className="lr-palette-search">
          <Icon name="Search" size={17} />
          <input
            ref={input}
            className="lr-palette-input"
            type="search"
            role="combobox"
            aria-label={t('search.label')}
            aria-expanded={options.length > 0}
            aria-controls="lr-palette-list"
            aria-autocomplete="list"
            aria-activedescendant={activeOption ? optionId(activeOption) : undefined}
            placeholder={t('palette.placeholder')}
            autoComplete="off"
            autoCorrect="off"
            spellCheck={false}
            enterKeyHint="go"
            autoFocus
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            onKeyDown={onKeyDown}
          />
          <kbd className="lr-kbd">Esc</kbd>
        </div>
        <div className="lr-palette-body" id="lr-palette-list" role="listbox" aria-label={t('palette.title')} ref={list}>
          {body}
        </div>
        <div className="lr-palette-footer" aria-hidden="true">
          <span className="lr-palette-hint">
            <kbd className="lr-kbd">↑</kbd>
            <kbd className="lr-kbd">↓</kbd>
            {t('palette.navigate')}
          </span>
          <span className="lr-palette-hint">
            <kbd className="lr-kbd">↵</kbd>
            {t('palette.select')}
          </span>
          <span className="lr-palette-hint">
            <kbd className="lr-kbd">Esc</kbd>
            {t('palette.close')}
          </span>
          {trimmed !== '' && status === 'ready' && <span className="lr-palette-footer-end">{t('palette.results', {}, hits.length)}</span>}
        </div>
      </div>
    </Dialog>
  )
}
