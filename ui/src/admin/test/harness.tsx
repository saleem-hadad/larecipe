import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { vi } from 'vitest'
import frontmatter from '../../../../resources/schemas/frontmatter.schema.json'
import manifest from '../../../../resources/schemas/larecipe.schema.json'
import navigation from '../../../../resources/schemas/navigation.schema.json'
import { AdminProvider } from '../context'
import type { Boot, JsonSchema, Revision } from '../types'

/** The admin boot payload the server sends for the "kb" database set (BuildBootPayloadAction). */
export function bootFixture(overrides: Partial<Boot> = {}): Boot {
  return {
    set: { key: 'kb', name: 'Knowledge base', description: null, driver: 'database', review: 'required', versioned: false, multilingual: false },
    versions: [{ id: 'current', label: 'Current', status: 'current', default: true }],
    locales: [{ code: 'en', label: 'English', dir: 'ltr', default: true }],
    user: { name: 'Ada Reviewer' },
    permissions: { manage: true, edit: true, review: true },
    urls: { base: '/kb/_admin', api: '/kb/_admin/api', reader: 'http://localhost/kb' },
    components: [
      { name: 'Callout', description: 'A highlighted note, tip or warning.', props: { type: { type: 'string', default: 'note', enum: ['note', 'tip', 'warning'] }, title: { type: 'string' } }, island: false, raw: false, origin: 'builtin' },
      { name: 'Steps', description: 'Numbered steps.', props: {}, island: false, raw: false, origin: 'builtin' },
    ],
    schemas: { frontmatter: frontmatter as JsonSchema, manifest: manifest as JsonSchema, navigation: navigation as JsonSchema },
    version: '3.0.0',
    ...overrides,
  }
}

export function revisionFixture(overrides: Partial<Revision> = {}): Revision {
  return {
    id: '5',
    set: 'kb',
    subject_type: 'page',
    subject: 'current/en/billing/refunds',
    ref: { set: 'kb', version: 'current', locale: 'en', path: 'billing/refunds' },
    ref_before: null,
    operation: 'write',
    status: 'draft',
    author: { actor: 'agent', name: 'claude-code', ref: null, at: '2026-10-17T10:00:00+00:00', approved_by: null },
    channel: 'remote_agent',
    content_hash: 'sha256:aaaaaaaaaa',
    based_on_hash: 'sha256:bbbbbbbbbb',
    replaces: '2',
    summary: 'Say how long refunds take',
    migration: null,
    reviewed_by: null,
    reviewed_at: null,
    review_note: null,
    published_at: null,
    created_at: '2026-10-17T10:00:00+00:00',
    ticket: '0005',
    is_conflict: false,
    title: 'Refunds',
    ...overrides,
  }
}

type Handler = (request: { method: string; url: URL; body: unknown }) => { status?: number; json: unknown }

/**
 * Answer the admin API from a table of handlers ("GET review", "POST review/5/publish")
 * and record every call, so a test can say what the screen asked the server.
 */
export function mockApi(handlers: Record<string, Handler | unknown>) {
  const calls: { method: string; path: string; body: unknown }[] = []

  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = new URL(String(input), window.location.origin)
    const method = (init?.method ?? 'GET').toUpperCase()
    const path = url.pathname.replace('/kb/_admin/api/', '')
    const body = typeof init?.body === 'string' ? JSON.parse(init.body) : (init?.body ?? null)
    calls.push({ method, path, body })

    const handler = handlers[`${method} ${path}`]
    if (handler === undefined) {
      return new Response(JSON.stringify({ message: `No handler for ${method} ${path}` }), { status: 404, headers: { 'Content-Type': 'application/json' } })
    }

    const result = typeof handler === 'function' ? (handler as Handler)({ method, url, body }) : { json: handler }
    return new Response(JSON.stringify(result.json), { status: result.status ?? 200, headers: { 'Content-Type': 'application/json' } })
  })

  vi.stubGlobal('fetch', fetchMock)

  return { calls, fetchMock }
}

/** Render a screen inside the admin's context, as the app would. */
export function renderAdmin(ui: ReactElement, options: { boot?: Boot; search?: string; navigate?: ReturnType<typeof vi.fn> } = {}) {
  const boot = options.boot ?? bootFixture()
  const navigate = options.navigate ?? vi.fn()
  const notices = { push: vi.fn() }

  const utils = render(
    <AdminProvider boot={boot} search={options.search ?? ''} navigate={navigate} href={(route) => `${boot.urls.base}/${route.name}`} notices={notices}>
      {ui}
    </AdminProvider>,
  )

  return { ...utils, navigate, notices }
}
