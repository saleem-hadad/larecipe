import { createTranslator, installRuntime, LaRecipeContext, type BootPayload, type LaRecipeContextValue } from '@larecipe/ui'
import { act, fireEvent, render, screen } from '@testing-library/react'
import type { ReactNode } from 'react'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { AppContext, type AppServices } from '../app/context'
import { createRouter } from '../app/router'
import { createStore, initialState } from '../app/store'
import { bootFixture } from '../test/fixtures'
import { AnswerFeedback } from './AnswerFeedback'

beforeAll(() => installRuntime('test'))
afterEach(() => vi.unstubAllGlobals())

const strings = {
  'ask.rate': 'Did this answer help?',
  'ask.helpful': 'This answer helped',
  'ask.not_helpful': 'This answer did not help',
  'feedback.thanks': 'Thanks. Your feedback was sent.',
}

function Providers({ boot, children }: { boot: BootPayload; children: ReactNode }) {
  const store = createStore(initialState(boot, {}, { preference: 'system', mode: 'light' }))
  const services: AppServices = {
    store,
    router: createRouter({ store, fetch: (async () => new Response('{}')) as never, fullLoad: () => {} }),
    actions: {
      openPalette: () => {},
      closePalette: () => {},
      openAsk: () => {},
      closeAsk: () => {},
      setShortcuts: () => {},
      setSidebar: () => {},
      setTheme: () => {},
      copyText: async () => true,
      copyMarkdown: async () => true,
      copyMcpCommand: async () => true,
      announce: () => {},
    },
  }
  const value: LaRecipeContextValue = {
    boot,
    page: { ...boot.page, version: boot.version, locale: boot.locale.code },
    set: boot.set,
    theme: { mode: 'light', preference: 'system' },
    user: null,
    t: createTranslator(boot.strings),
    locale: boot.locale,
    version: boot.version,
    navigate: () => {},
  }
  return (
    <AppContext.Provider value={services}>
      <LaRecipeContext.Provider value={value}>{children}</LaRecipeContext.Provider>
    </AppContext.Provider>
  )
}

function withFeedback(): BootPayload {
  return bootFixture({ features: { search: 'client', ask: true, feedback: true }, feedback: { endpoint: '/guide/_feedback' }, strings } as Partial<BootPayload>)
}

describe('thumbs on an Ask answer', () => {
  it('sends the vote with the question to the feedback endpoint, tagged as Ask feedback', async () => {
    const fetchMock = vi.fn(async () => new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)
    const events: CustomEvent[] = []
    const listener = (event: Event) => events.push(event as CustomEvent)
    document.addEventListener('larecipe:feedback', listener)

    render(
      <Providers boot={withFeedback()}>
        <AnswerFeedback question="Can I download invoices?" />
      </Providers>,
    )

    expect(screen.getByRole('group', { name: 'Did this answer help?' })).toBeTruthy()

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'This answer did not help' }))
    })

    expect(fetchMock).toHaveBeenCalledTimes(1)
    const [url, init] = fetchMock.mock.calls[0] as unknown as [URL, RequestInit]
    expect(String(url)).toBe('http://localhost:3000/guide/_feedback')
    expect(JSON.parse(String(init.body))).toEqual({ path: 'installation', version: '3.x', locale: 'en', helpful: false, question: 'Can I download invoices?' })
    expect(events[0]?.detail).toMatchObject({ helpful: false, source: 'ask' })
    expect(screen.getByRole('status').textContent).toBe('Thanks. Your feedback was sent.')

    document.removeEventListener('larecipe:feedback', listener)
  })

  it('stays hidden when the set takes no feedback', () => {
    const { container } = render(
      <Providers boot={bootFixture({ strings } as Partial<BootPayload>)}>
        <AnswerFeedback question="Hi?" />
      </Providers>,
    )

    expect(container.innerHTML).toBe('')
  })
})
