import { afterEach, describe, expect, it, vi } from 'vitest'
import { bootFixture } from '../test/fixtures'
import { displayPath, highlightRuns, prepareSearch, resetSearch, suggestedPages, toHit } from './search'

afterEach(() => {
  resetSearch()
  vi.unstubAllGlobals()
})

describe('search records as palette rows', () => {
  it('maps a section record from the client index (COMPONENTS.md shape)', () => {
    const hit = toHit({ id: 'webhooks#retries', title: 'Webhooks', heading: 'Retries', text: 'Failed deliveries are retried.', path: 'guides/webhooks', url: '/guide/3.x/guides/webhooks#retries', kind: 'section' }, '/guide')
    expect(hit).toMatchObject({ kind: 'section', title: 'Retries', page: 'Webhooks', path: '3.x/guides/webhooks#retries' })
  })

  it('maps a page record and a server hit', () => {
    expect(toHit({ id: 'webhooks', title: 'Webhooks', heading: null, url: '/guide/3.x/guides/webhooks', kind: 'page' }, '/guide')).toMatchObject({ kind: 'page', title: 'Webhooks', page: null })
    expect(toHit({ title: 'Webhooks', heading: 'Retries', url: '/guide/3.x/guides/webhooks#retries', snippet: 'Failed…' }, '/guide')).toMatchObject({ kind: 'section', snippet: 'Failed…' })
    expect(toHit({ title: 'No URL' }, '/guide')).toBeNull()
  })

  it('shows paths relative to the doc set', () => {
    expect(displayPath('/guide/3.x/installation', '/guide')).toBe('3.x/installation')
    expect(displayPath('/guide', '/guide')).toBe('/')
    expect(displayPath('/installation#env', '')).toBe('installation#env')
  })
})

describe('highlighting', () => {
  it('marks the characters the reader typed, at word starts', () => {
    expect(highlightRuns('Webhooks and web views', 'webh')).toEqual([
      { text: 'Webh', match: true },
      { text: 'ooks and web views', match: false },
    ])
  })

  it('falls back to the matched terms for fuzzy hits', () => {
    expect(highlightRuns('Webhooks', 'wbhooks', ['webhooks'])).toEqual([{ text: 'Webhooks', match: true }])
  })
})

describe('the searcher', () => {
  it('loads the client index into MiniSearch and answers in the browser', async () => {
    const index = {
      fields: ['title', 'heading', 'text'],
      storeFields: ['title', 'heading', 'url', 'kind'],
      searchOptions: { prefix: true, fuzzy: 0.2, combineWith: 'AND' },
      records: [
        { id: 'install', title: 'Installation', heading: null, text: 'Require the package.', url: '/guide/3.x/installation', kind: 'page' },
        { id: 'webhooks#retries', title: 'Webhooks', heading: 'Retries', text: 'Failed deliveries are retried with backoff.', url: '/guide/3.x/guides/webhooks#retries', kind: 'section' },
      ],
    }
    const fetchMock = vi.fn(async () => ({ ok: true, json: async () => index }))
    vi.stubGlobal('fetch', fetchMock)

    const boot = bootFixture({ search: { driver: 'client', index_url: '/guide/3.x/_search/guest.abc.json', endpoint: '/guide/3.x/_search' } })
    const searcher = await prepareSearch(boot)
    expect(searcher.mode).toBe('client')

    const hits = await searcher.search('retri')
    expect(hits[0]).toMatchObject({ kind: 'section', title: 'Retries', page: 'Webhooks' })

    // Every word must match; with none, any word may.
    expect(await searcher.search('install backoff')).not.toHaveLength(0)
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('queries the server endpoint when the set uses the server driver', async () => {
    const fetchMock = vi.fn(async () => ({ ok: true, json: async () => ({ query: 'retries', hits: [{ title: 'Webhooks', heading: 'Retries', url: '/guide/3.x/guides/webhooks#retries' }] }) }))
    vi.stubGlobal('fetch', fetchMock)

    const searcher = await prepareSearch(bootFixture({ search: { driver: 'server', index_url: null, endpoint: '/guide/3.x/_search' } }))
    expect(searcher.mode).toBe('server')
    const hits = await searcher.search('retries')
    expect(hits).toHaveLength(1)
    expect(String((fetchMock.mock.calls[0] as unknown[])[0])).toContain('/guide/3.x/_search?q=retries')
  })

  it('still finds pages by title from the navigation when there is no index', async () => {
    const searcher = await prepareSearch(bootFixture({ search: undefined }))
    expect(searcher.mode).toBe('navigation')
    expect((await searcher.search('intro'))[0]).toMatchObject({ title: 'Introduction' })
    expect(suggestedPages(bootFixture()).map((hit) => hit.title)).toEqual(['Introduction'])
  })
})
