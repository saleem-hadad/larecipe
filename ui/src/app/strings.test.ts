import { choose, createTranslator, format, splitTemplate } from '@larecipe/ui'
import { describe, expect, it } from 'vitest'
import { isTyping, shortcutFor } from './shortcuts'

describe('UI strings in Laravel format', () => {
  it('replaces placeholders, longest first', () => {
    expect(format('Updated :time by :name', { time: '2 hours ago', name: 'Claude Code' })).toBe('Updated 2 hours ago by Claude Code')
    expect(format(':count of :countdown', { count: 1, countdown: 'ten' })).toBe('1 of ten')
  })

  it('chooses plural forms with explicit ranges, including Arabic', () => {
    const arabic = '{1} صفحة واحدة|{2} صفحتان|[3,10] :count صفحات|[11,*] :count صفحة'
    expect(choose(arabic, 1)).toBe('صفحة واحدة')
    expect(choose(arabic, 2)).toBe('صفحتان')
    expect(choose(arabic, 7)).toBe(':count صفحات')
    expect(choose(arabic, 40)).toBe(':count صفحة')
    expect(choose('page|pages', 1)).toBe('page')
    expect(choose('page|pages', 3)).toBe('pages')
  })

  it('translates with a fallback to the key', () => {
    const t = createTranslator({ 'palette.results': '{0} No results|{1} :count result|[2,*] :count results', 'actions.copied': 'Copied' })
    expect(t('palette.results', {}, 0)).toBe('No results')
    expect(t('palette.results', {}, 4)).toBe('4 results')
    expect(t('actions.copied')).toBe('Copied')
    expect(t('missing.key')).toBe('missing.key')
  })

  it('splits a template around placeholders for rich rendering', () => {
    expect(splitTemplate('حُدّث :time بواسطة :name', ['time', 'name'])).toEqual(['حُدّث ', { key: 'time' }, ' بواسطة ', { key: 'name' }])
  })
})

describe('keyboard shortcuts', () => {
  const press = (key: string, init: KeyboardEventInit = {}, target?: HTMLElement) => {
    const event = new KeyboardEvent('keydown', { key, ...init })
    if (target) Object.defineProperty(event, 'target', { value: target })
    return shortcutFor(event)
  }

  it('maps ⌘K, /, ⌘I and ?', () => {
    expect(press('k', { metaKey: true })).toBe('palette')
    expect(press('k', { ctrlKey: true })).toBe('palette')
    expect(press('/')).toBe('palette')
    expect(press('i', { metaKey: true })).toBe('ask')
    expect(press('?', { shiftKey: true })).toBe('help')
  })

  it('ignores single keys while the reader is typing', () => {
    const input = document.createElement('input')
    expect(isTyping(input)).toBe(true)
    expect(press('/', {}, input)).toBeNull()
    expect(press('k', { metaKey: true }, input)).toBe('palette')
    const checkbox = document.createElement('input')
    checkbox.type = 'checkbox'
    expect(isTyping(checkbox)).toBe(false)
  })
})
