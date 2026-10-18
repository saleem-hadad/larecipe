import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { applySidebar, sidebarHidden } from './sidebar'

describe('hiding the navigation on wide screens', () => {
  const state = () => document.documentElement.getAttribute('data-lr-sidebar')

  beforeEach(() => {
    vi.useFakeTimers()
    document.documentElement.style.setProperty('--lr-duration-panel', '240ms')
  })

  afterEach(() => {
    vi.useRealTimers()
    document.documentElement.removeAttribute('data-lr-sidebar')
    document.documentElement.style.removeProperty('--lr-duration-panel')
    localStorage.clear()
  })

  it('slides the sidebar out first, then changes the layout once', () => {
    applySidebar(true)
    expect(state()).toBe('hiding')
    expect(sidebarHidden()).toBe(true)

    vi.advanceTimersByTime(240)
    expect(state()).toBe('hidden')
    expect(localStorage.getItem('larecipe:sidebar')).toBe('hidden')
  })

  it('changes the layout first when showing, then clears the state after the slide in', () => {
    applySidebar(true)
    vi.advanceTimersByTime(240)
    applySidebar(false)
    expect(state()).toBe('showing')
    expect(sidebarHidden()).toBe(false)

    vi.advanceTimersByTime(240)
    expect(state()).toBeNull()
    expect(localStorage.getItem('larecipe:sidebar')).toBe('shown')
  })

  it('a quick second click wins over the first', () => {
    applySidebar(true)
    vi.advanceTimersByTime(100)
    applySidebar(false)
    vi.advanceTimersByTime(240)
    expect(state()).toBeNull()
  })

  it('switches at once when the reader prefers reduced motion', () => {
    document.documentElement.style.setProperty('--lr-duration-panel', '0ms')
    applySidebar(true)
    expect(state()).toBe('hidden')
  })
})
