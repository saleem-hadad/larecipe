import type { PageJson } from '@larecipe/ui'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { bootFixture, pageJsonFixture } from '../test/fixtures'
import { createRouter, PAGE_JSON_TYPE } from './router'
import { createStore, initialState } from './store'

function jsonResponse(json: PageJson, headers: Record<string, string> = {}) {
  const all: Record<string, string> = { 'content-type': PAGE_JSON_TYPE, 'x-larecipe-build': json.build, ...headers }
  return {
    ok: true,
    status: 200,
    headers: { get: (name: string) => all[name.toLowerCase()] ?? null },
    json: async () => json,
  } as unknown as Response
}

function setup(fetchImpl: (input: RequestInfo | URL, init?: RequestInit) => Promise<Response>) {
  const store = createStore(initialState(bootFixture(), {}, { preference: 'system', mode: 'light' }))
  const fullLoad = vi.fn()
  const fetchMock = vi.fn(fetchImpl)
  const router = createRouter({ store, fetch: fetchMock as unknown as typeof fetch, fullLoad, transition: async (update) => update() })
  return { store, router, fullLoad, fetchMock }
}

beforeEach(() => {
  window.scrollTo = vi.fn() as unknown as typeof window.scrollTo
  window.history.replaceState(null, '', '/guide/3.x/installation')
  document.documentElement.removeAttribute('data-lr-static')
  document.title = 'Installation · LaRecipe'
})

describe('which links are docs links', () => {
  const { router } = setup(async () => jsonResponse(pageJsonFixture()))
  const url = (path: string) => new URL(path, window.location.href)

  it('accepts pages inside the doc set', () => {
    expect(router.isDocsUrl(url('/guide/3.x/configuration'))).toBe(true)
    expect(router.isDocsUrl(url('/guide/2.x'))).toBe(true)
    expect(router.isDocsUrl(url('/guide'))).toBe(true)
  })

  it('refuses other sets, other origins, files and endpoints', () => {
    expect(router.isDocsUrl(url('/docs/installation'))).toBe(false)
    expect(router.isDocsUrl(new URL('https://example.com/guide/3.x'))).toBe(false)
    expect(router.isDocsUrl(url('/guide/3.x/installation.md'))).toBe(false)
    expect(router.isDocsUrl(url('/guide/3.x/llms.txt'))).toBe(false)
    expect(router.isDocsUrl(url('/guide/_assets/3.x/loop.svg'))).toBe(false)
    expect(router.isDocsUrl(url('/guide/3.x/_search/abc.json'))).toBe(false)
    expect(router.isDocsUrl(url('/guide/mcp'))).toBe(false)
  })
})

describe('client navigation', () => {
  it('fetches the page JSON and swaps the page in the store', async () => {
    const { store, router, fullLoad, fetchMock } = setup(async () => jsonResponse(pageJsonFixture()))

    const result = await router.navigate('/guide/3.x/configuration')

    expect(result).toBe('swapped')
    expect(fullLoad).not.toHaveBeenCalled()
    expect(fetchMock).toHaveBeenCalledWith('/guide/3.x/configuration', expect.objectContaining({ headers: expect.objectContaining({ Accept: PAGE_JSON_TYPE }) }))
    expect(window.location.pathname).toBe('/guide/3.x/configuration')
    expect(store.get().page.title).toBe('Configuration')
    expect(store.get().article).toEqual({ key: 1, html: '<h2 id="routes">Routes</h2><p>Hello.</p>' })
    expect(store.get().navigated).toBe(true)
    expect(document.title).toBe('Configuration · LaRecipe')
  })

  it('does a full load when the UI build changed', async () => {
    const { store, router, fullLoad } = setup(async () => jsonResponse(pageJsonFixture({ build: 'build-2' })))
    expect(await router.navigate('/guide/3.x/configuration')).toBe('full')
    expect(fullLoad).toHaveBeenCalledWith(expect.stringMatching(/\/guide\/3\.x\/configuration$/))
    expect(store.get().page.title).toBe('Installation')
  })

  it('does a full load into another locale', async () => {
    const { router, fullLoad } = setup(async () => jsonResponse(pageJsonFixture({ locale: { code: 'ar', dir: 'rtl' } })))
    expect(await router.navigate('/guide/ar/3.x/configuration')).toBe('full')
    expect(fullLoad).toHaveBeenCalled()
  })

  it('does a full load when the server answers with an error', async () => {
    const { router, fullLoad } = setup(async () => ({ ok: false, status: 404, headers: { get: () => 'application/json' }, json: async () => ({}) }) as unknown as Response)
    expect(await router.navigate('/guide/3.x/missing')).toBe('full')
    expect(fullLoad).toHaveBeenCalled()
  })

  it('never fetches JSON on a static export', async () => {
    document.documentElement.setAttribute('data-lr-static', '')
    const { router, fullLoad, fetchMock } = setup(async () => jsonResponse(pageJsonFixture()))
    expect(await router.navigate('/guide/3.x/configuration')).toBe('full')
    router.prefetch('/guide/3.x/configuration')
    expect(fetchMock).not.toHaveBeenCalled()
    expect(fullLoad).toHaveBeenCalled()
  })

  it('prefetches once and reuses the payload for the navigation', async () => {
    const { router, fetchMock } = setup(async () => jsonResponse(pageJsonFixture()))
    router.prefetch('/guide/3.x/configuration')
    router.prefetch('/guide/3.x/configuration')
    await router.navigate('/guide/3.x/configuration')
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('leaves same-page anchors to the browser', async () => {
    const { router, fetchMock } = setup(async () => jsonResponse(pageJsonFixture()))
    expect(await router.navigate('/guide/3.x/installation#requirements')).toBe('ignored')
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('intercepts plain clicks on docs links and leaves modified clicks alone', async () => {
    const { router, fetchMock } = setup(async () => jsonResponse(pageJsonFixture()))
    const uninstall = router.install()
    const link = document.createElement('a')
    link.href = '/guide/3.x/configuration'
    document.body.appendChild(link)

    const modified = new MouseEvent('click', { bubbles: true, cancelable: true, metaKey: true })
    link.dispatchEvent(modified)
    expect(modified.defaultPrevented).toBe(false)

    const plain = new MouseEvent('click', { bubbles: true, cancelable: true })
    link.dispatchEvent(plain)
    expect(plain.defaultPrevented).toBe(true)
    await vi.waitFor(() => expect(window.location.pathname).toBe('/guide/3.x/configuration'))
    expect(fetchMock).toHaveBeenCalled()

    uninstall()
    link.remove()
  })
})
