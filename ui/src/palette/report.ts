/**
 * Searches the palette answers in the browser never reach the server, so they
 * are reported in small batches and become SearchPerformed events there, the
 * same events server searches emit. Only a settled query counts: one the
 * reader kept typing past is not a search. Nothing is sent where there is no
 * report URL (the server driver logs its own searches; a static site has no
 * server).
 */

export interface SearchReport {
  query: string
  results: number
}

type Send = (url: string, searches: SearchReport[]) => void

/** How long a query must stay unchanged before it counts as a search. */
export const SETTLE_MS = 1500

/** Send once this many searches are waiting. */
export const BATCH_SIZE = 10

function xsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1] ?? '') : null
}

/** POST with keepalive, so a batch flushed while the page closes still arrives. */
export const sendReport: Send = (url, searches) => {
  const headers: Record<string, string> = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  const token = xsrfToken()
  if (token) headers['X-XSRF-TOKEN'] = token

  void fetch(url, { method: 'POST', headers, body: JSON.stringify({ searches }), credentials: 'same-origin', keepalive: true }).catch(() => {})
}

export interface SearchReporter {
  /** The reader's current query and how many results it has. */
  record(query: string, results: number): void
  /** Count the settled query now and send whatever is waiting (palette closed, page hidden). */
  flush(): void
}

export function createSearchReporter(url: string | null | undefined, send: Send = sendReport): SearchReporter {
  let pending: SearchReport | null = null
  let timer: ReturnType<typeof setTimeout> | null = null
  let last = ''
  const queue: SearchReport[] = []

  const settle = () => {
    if (timer !== null) clearTimeout(timer)
    timer = null
    if (pending === null) return
    const search = pending
    pending = null
    // The same query twice in a row (closing and reopening the palette) is one search.
    if (search.query.toLowerCase() === last) return
    last = search.query.toLowerCase()
    queue.push(search)
    if (queue.length >= BATCH_SIZE) sendQueue()
  }

  const sendQueue = () => {
    if (!url || queue.length === 0) return
    send(url, queue.splice(0, queue.length))
  }

  return {
    record(query, results) {
      if (!url) return
      const trimmed = query.trim()
      if (timer !== null) clearTimeout(timer)
      pending = trimmed === '' ? null : { query: trimmed.slice(0, 200), results }
      timer = pending === null ? null : setTimeout(settle, SETTLE_MS)
    },
    flush() {
      settle()
      sendQueue()
    },
  }
}
