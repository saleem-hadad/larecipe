import type { BootPayload } from '@larecipe/ui'
import { describe, expect, it, vi } from 'vitest'
import { createStore, initialState } from './store'
import { bootFixture } from '../test/fixtures'

describe('store', () => {
  it('starts from the boot payload with the adopted article and nothing open', () => {
    const boot = bootFixture()
    const state = initialState(boot, { 'article.after': '<form></form>' }, { preference: 'system', mode: 'light' })

    expect(state.page).toBe(boot.page)
    expect(state.article).toEqual({ key: 0, html: null })
    expect(state.insertsHtml['article.after']).toBe('<form></form>')
    expect(state.palette.open).toBe(false)
    expect(state.ask.open).toBe(false)
  })

  it('notifies subscribers only when a slice really changes', () => {
    const store = createStore(initialState(bootFixture(), {}, { preference: 'system', mode: 'light' }))
    const listener = vi.fn()
    store.subscribe(listener)

    store.set({ sidebar: true })
    store.set({ sidebar: true })
    store.set((state) => ({ palette: { ...state.palette, open: true } }))

    expect(listener).toHaveBeenCalledTimes(2)
    expect(store.get().sidebar).toBe(true)
    expect(store.get().palette.open).toBe(true)
  })

  it('stops notifying after unsubscribe', () => {
    const store = createStore(initialState(bootFixture() as BootPayload, {}, { preference: 'light', mode: 'light' }))
    const listener = vi.fn()
    const unsubscribe = store.subscribe(listener)
    unsubscribe()
    store.set({ shortcuts: true })
    expect(listener).not.toHaveBeenCalled()
  })
})
