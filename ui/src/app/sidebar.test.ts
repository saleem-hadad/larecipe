import { afterEach, describe, expect, it } from 'vitest'
import { applySidebar, sidebarHidden } from './sidebar'

describe('hiding the navigation on wide screens', () => {
  afterEach(() => {
    document.documentElement.removeAttribute('data-lr-sidebar')
    localStorage.clear()
  })

  it('marks the page and remembers the choice', () => {
    applySidebar(true)
    expect(document.documentElement.getAttribute('data-lr-sidebar')).toBe('hidden')
    expect(sidebarHidden()).toBe(true)
    expect(localStorage.getItem('larecipe:sidebar')).toBe('hidden')

    applySidebar(false)
    expect(document.documentElement.hasAttribute('data-lr-sidebar')).toBe(false)
    expect(sidebarHidden()).toBe(false)
    expect(localStorage.getItem('larecipe:sidebar')).toBe('shown')
  })
})
