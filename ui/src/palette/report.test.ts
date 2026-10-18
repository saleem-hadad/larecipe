import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { BATCH_SIZE, createSearchReporter, SETTLE_MS, type SearchReport } from './report'

describe('reporting palette searches', () => {
  let sent: SearchReport[][]
  const send = (_url: string, searches: SearchReport[]) => sent.push(searches)

  beforeEach(() => {
    sent = []
    vi.useFakeTimers()
  })

  afterEach(() => vi.useRealTimers())

  it('counts a query only once it settles, and sends on flush', () => {
    const reporter = createSearchReporter('/docs/_search/report', send)
    reporter.record('web', 4)
    reporter.record('webho', 2)
    reporter.record('webhooks', 2)
    vi.advanceTimersByTime(SETTLE_MS)
    reporter.flush()

    expect(sent).toEqual([[{ query: 'webhooks', results: 2 }]])
  })

  it('counts the query on screen when the palette closes before it settles', () => {
    const reporter = createSearchReporter('/docs/_search/report', send)
    reporter.record('billing exports', 0)
    reporter.flush()

    expect(sent).toEqual([[{ query: 'billing exports', results: 0 }]])
  })

  it('sends a full batch on its own, and treats a repeated query as one search', () => {
    const reporter = createSearchReporter('/docs/_search/report', send)
    reporter.record('install', 3)
    vi.advanceTimersByTime(SETTLE_MS)
    reporter.record('Install', 3)
    vi.advanceTimersByTime(SETTLE_MS)
    for (let i = 1; i < BATCH_SIZE; i++) {
      reporter.record(`query ${i}`, i)
      vi.advanceTimersByTime(SETTLE_MS)
    }

    expect(sent).toHaveLength(1)
    expect(sent[0]).toHaveLength(BATCH_SIZE)
    expect(sent[0]?.[0]).toEqual({ query: 'install', results: 3 })
  })

  it('sends nothing without a report URL or for an empty query', () => {
    const silent = createSearchReporter(null, send)
    silent.record('webhooks', 1)
    silent.flush()

    const reporter = createSearchReporter('/docs/_search/report', send)
    reporter.record('   ', 0)
    reporter.flush()

    expect(sent).toEqual([])
  })
})
