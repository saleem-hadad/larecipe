import type { BootPayload, NavItem } from '@larecipe/ui'
import MiniSearch, { type Options, type SearchOptions } from 'minisearch'

/**
 * Search for the palette. The default is the client index (ARCHITECTURE.md
 * 10.2): a fingerprinted JSON file loaded once into MiniSearch, then every
 * keystroke is answered in the browser. Sets on the server driver (or a
 * custom engine) query the endpoint instead. With neither, page titles from
 * the navigation still make the palette useful.
 */
export interface SearchHit {
  id: string
  kind: 'page' | 'section'
  title: string
  /** The page a section belongs to. */
  page: string | null
  url: string
  path: string
  snippet: string | null
  /** Document terms MiniSearch matched, for highlighting fuzzy matches. */
  terms: string[]
}

export interface Searcher {
  mode: 'client' | 'server' | 'navigation'
  search(query: string, signal?: AbortSignal): Promise<SearchHit[]>
}

type IndexPayload = {
  fields?: string[]
  storeFields?: string[]
  searchOptions?: SearchOptions
  idField?: string
  records?: Array<Record<string, unknown>>
  index?: unknown
  documentCount?: number
  [key: string]: unknown
}

const LIMIT = 24

const DEFAULT_SEARCH: SearchOptions = {
  prefix: true,
  fuzzy: 0.2,
  combineWith: 'AND',
  boost: { title: 3, heading: 2, headings: 2, page_title: 1.5, tags: 1.5 },
}

function string(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null
}

/** Display path for a URL: relative to the doc set's root, mono in the palette. */
export function displayPath(url: string, basePath: string): string {
  try {
    const parsed = new URL(url, window.location.origin)
    const base = basePath.replace(/\/$/, '')
    let path = parsed.pathname
    if (base !== '' && (path === base || path.startsWith(`${base}/`))) path = path.slice(base.length)
    path = path.replace(/^\//, '')
    return (path === '' ? '/' : path) + parsed.hash
  } catch {
    return url
  }
}

/** A search record or server hit, whatever its exact shape, as a palette row. */
export function toHit(record: Record<string, unknown>, basePath: string, terms: string[] = []): SearchHit | null {
  const url = string(record.url) ?? string(record.href) ?? string(record.link)
  if (!url) return null
  const headingList = Array.isArray(record.headings) ? record.headings.filter((value): value is string => typeof value === 'string') : []
  const heading = string(record.heading) ?? string(record.section) ?? headingList[headingList.length - 1] ?? null
  const title = string(record.title) ?? heading ?? url
  const pageTitle = string(record.page_title) ?? string(record.pageTitle) ?? string(record.page)
  const explicit = string(record.type) ?? string(record.kind)
  const isSection = explicit ? explicit === 'section' || explicit === 'heading' : heading !== null || url.includes('#')

  return {
    id: String(record.id ?? url),
    kind: isSection ? 'section' : 'page',
    title: isSection ? (heading ?? title) : title,
    page: isSection ? (pageTitle ?? (heading && heading !== title ? title : null)) : null,
    url,
    path: displayPath(url, basePath),
    snippet: string(record.snippet) ?? string(record.excerpt) ?? string(record.text)?.slice(0, 160) ?? null,
    terms,
  }
}

function navigationRecords(boot: BootPayload): Array<Record<string, unknown>> {
  const records: Array<Record<string, unknown>> = []
  const walk = (items: NavItem[], group: string | null) => {
    for (const item of items) {
      if (item.type === 'page') records.push({ id: item.path, title: item.title, url: item.url, type: 'page', group })
      else if (item.type === 'group') walk(item.items, item.label)
    }
  }
  boot.navigation.forEach((tab) => walk(tab.items, tab.label))
  return records
}

function fromMiniSearch(engine: MiniSearch, query: string, basePath: string): SearchHit[] {
  // Every word must match; when nothing does, fall back to any word matching.
  let results = engine.search(query)
  if (results.length === 0 && /\s/.test(query.trim())) results = engine.search(query, { combineWith: 'OR' })
  return results
    .slice(0, LIMIT)
    .map((result) => toHit(result as unknown as Record<string, unknown>, basePath, result.terms))
    .filter((hit): hit is SearchHit => hit !== null)
}

async function clientSearcher(url: string, basePath: string): Promise<Searcher> {
  const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
  if (!response.ok) throw new Error(`The search index answered ${response.status}.`)
  const payload = (await response.json()) as IndexPayload
  const fields = payload.fields ?? ['title', 'heading', 'text']
  const options: Options = {
    fields,
    storeFields: payload.storeFields ?? ['title', 'heading', 'url', 'page_title', 'type', 'path'],
    idField: payload.idField ?? 'id',
    searchOptions: { ...DEFAULT_SEARCH, ...(payload.searchOptions ?? {}) },
  }

  let engine: MiniSearch
  if (payload.index !== undefined && payload.documentCount !== undefined) {
    // An index serialized on the server in MiniSearch's own format.
    engine = MiniSearch.loadJS(payload as never, options)
  } else {
    engine = new MiniSearch(options)
    engine.addAll(payload.records ?? [])
  }

  return { mode: 'client', search: async (query) => fromMiniSearch(engine, query, basePath) }
}

function serverSearcher(endpoint: string, basePath: string): Searcher {
  return {
    mode: 'server',
    async search(query, signal) {
      const url = new URL(endpoint, window.location.href)
      url.searchParams.set('q', query)
      const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal })
      if (!response.ok) throw new Error(`Search answered ${response.status}.`)
      const body = (await response.json()) as unknown
      const list = Array.isArray(body) ? body : ((body as { hits?: unknown[]; results?: unknown[]; data?: unknown[] }).hits ?? (body as { results?: unknown[] }).results ?? (body as { data?: unknown[] }).data ?? [])
      return list
        .slice(0, LIMIT)
        .map((record) => toHit(record as Record<string, unknown>, basePath))
        .filter((hit): hit is SearchHit => hit !== null)
    },
  }
}

function navigationSearcher(boot: BootPayload): Searcher {
  const engine = new MiniSearch({ fields: ['title', 'group'], storeFields: ['title', 'url', 'type'], searchOptions: { prefix: true, fuzzy: 0.2, boost: { title: 2 } } })
  engine.addAll(navigationRecords(boot))
  return { mode: 'navigation', search: async (query) => fromMiniSearch(engine, query, boot.set.base_path) }
}

let pending: { key: string; searcher: Promise<Searcher> } | null = null

/** One searcher per index URL, created once and shared (prefetch on idle, then reused). */
export function prepareSearch(boot: BootPayload): Promise<Searcher> {
  const indexUrl = boot.search?.index_url ?? null
  const endpoint = boot.search?.endpoint ?? null
  const driver = boot.search?.driver ?? boot.features.search
  const key = `${indexUrl ?? ''}|${endpoint ?? ''}|${driver}|${boot.version}|${boot.locale.code}`
  if (pending && pending.key === key) return pending.searcher

  const basePath = boot.set.base_path
  let searcher: Promise<Searcher>
  if (indexUrl && driver === 'client') {
    searcher = clientSearcher(indexUrl, basePath).catch(() => (endpoint ? serverSearcher(endpoint, basePath) : navigationSearcher(boot)))
  } else if (endpoint) {
    searcher = Promise.resolve(serverSearcher(endpoint, basePath))
  } else if (indexUrl) {
    searcher = clientSearcher(indexUrl, basePath).catch(() => navigationSearcher(boot))
  } else {
    searcher = Promise.resolve(navigationSearcher(boot))
  }

  pending = { key, searcher }
  return searcher
}

/** Reset the cached searcher (tests). */
export function resetSearch(): void {
  pending = null
}

/** Pages to offer before the reader types: the navigation, in order. */
export function suggestedPages(boot: BootPayload, limit = 6): SearchHit[] {
  return navigationRecords(boot)
    .filter((record) => record.url !== boot.page.path_url)
    .slice(0, limit)
    .map((record) => toHit(record, boot.set.base_path))
    .filter((hit): hit is SearchHit => hit !== null)
}

/**
 * Split text into plain and matched runs. Query words mark the characters the
 * reader typed (prefix matches); MiniSearch's matched terms cover fuzzy hits.
 */
export function highlightRuns(text: string, query: string, terms: string[] = []): Array<{ text: string; match: boolean }> {
  const words = query
    .toLowerCase()
    .split(/[\s/]+/)
    .filter((word) => word.length > 0)
  const lower = text.toLowerCase()
  const marks = new Array<boolean>(text.length).fill(false)

  const markAll = (needle: string, atWordStart: boolean) => {
    if (needle === '') return false
    let found = false
    let index = lower.indexOf(needle)
    while (index !== -1) {
      const boundary = index === 0 || /[^\p{L}\p{N}]/u.test(lower[index - 1] ?? ' ')
      if (!atWordStart || boundary) {
        for (let offset = 0; offset < needle.length; offset++) marks[index + offset] = true
        found = true
      }
      index = lower.indexOf(needle, index + 1)
    }
    return found
  }

  for (const word of words) {
    if (!markAll(word, true)) markAll(word, false)
  }
  if (!marks.some(Boolean)) {
    for (const term of terms) markAll(term.toLowerCase(), true)
  }

  const runs: Array<{ text: string; match: boolean }> = []
  for (let index = 0; index < text.length; index++) {
    const match = marks[index] ?? false
    const last = runs[runs.length - 1]
    if (last && last.match === match) last.text += text[index]
    else runs.push({ text: text[index] ?? '', match })
  }
  return runs
}
