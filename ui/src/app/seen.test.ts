import { beforeEach, describe, expect, it } from 'vitest'
import { markSeen, readSeen, SEEN_KEY, seenKey, seenRule } from './seen'

describe('seen fresh dots', () => {
  beforeEach(() => window.localStorage.clear())

  it('remembers each opened page version once, newest last', () => {
    markSeen(seenKey('/docs/a', '2026-10-18T10:00:00+00:00'))
    markSeen(seenKey('/docs/b', '2026-10-18T11:00:00+00:00'))
    markSeen(seenKey('/docs/a', '2026-10-18T10:00:00+00:00'))

    expect(readSeen()).toEqual(['/docs/b|2026-10-18T11:00:00+00:00', '/docs/a|2026-10-18T10:00:00+00:00'])
  })

  it('keeps the last 200 pages', () => {
    for (let i = 0; i < 205; i++) markSeen(seenKey(`/docs/${i}`, 't'))

    const seen = readSeen()
    expect(seen).toHaveLength(200)
    expect(seen[0]).toBe('/docs/5|t')
  })

  it('reads broken storage as nothing seen', () => {
    window.localStorage.setItem(SEEN_KEY, '{nope')
    expect(readSeen()).toEqual([])
    window.localStorage.setItem(SEEN_KEY, JSON.stringify(['/docs/a|t', 7]))
    expect(readSeen()).toEqual(['/docs/a|t'])
  })

  it('hides exactly the remembered dots, with quotes escaped', () => {
    expect(seenRule([])).toBe('')
    expect(seenRule(['/docs/a|t', '/docs/"b"|t'])).toBe('[data-lr-seen="/docs/a|t"],[data-lr-seen="/docs/\\"b\\"|t"]{display:none}')
  })
})
