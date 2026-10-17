import { request, type RequestResult } from '@larecipe/ui'
import type {
  Activity,
  AdminPage,
  Asset,
  DoctorReport,
  Frontmatter,
  MigrationExport,
  NavJson,
  Overview,
  Preview,
  ReviewQueue,
  Revision,
  RevisionDetail,
  StaleReport,
  TreeResponse,
  ValidationReport,
  WriteResult,
} from './types'

export interface Scope {
  version: string
  locale: string
}

export type SaveMode = 'publish' | 'draft'

/** What the API said, in one shape: the data, or a sentence a person can act on. */
export type Result<T> = { ok: true; status: number; data: T } | { ok: false; status: number; message: string; data: unknown; errors: Record<string, string[]> | null }

function settle<T>(result: RequestResult<T>): Result<T> {
  if (result.ok) {
    return result
  }

  const data = result.data as { message?: string } | null
  const firstError = result.errors ? Object.values(result.errors)[0]?.[0] : null

  return {
    ok: false,
    status: result.status,
    data: result.data,
    errors: result.errors,
    message: firstError ?? data?.message ?? result.message ?? `The server answered ${result.status}.`,
  }
}

/**
 * The admin JSON API (src/Domains/Admin), on the web guard: request() sends
 * the session cookie and Laravel's XSRF token. Reads take the version and
 * locale being edited; writes return the write result, whose outcome says
 * whether the change went live, waits as a draft, or was refused and why.
 */
export function createApi(base: string) {
  const url = (path: string, query: Record<string, string | undefined> = {}) => {
    const search = new URLSearchParams(Object.entries(query).filter((entry): entry is [string, string] => entry[1] !== undefined && entry[1] !== ''))
    const suffix = search.toString()
    return `${base}/${path}${suffix ? `?${suffix}` : ''}`
  }

  const get = async <T>(path: string, query: Record<string, string | undefined> = {}) => settle(await request<T>(url(path, query)))
  const send = async <T>(method: 'POST' | 'PUT' | 'DELETE', path: string, body: unknown) => settle(await request<T>(url(path), { method, body }))

  return {
    overview: (scope: Scope) => get<Overview>('overview', { ...scope }),
    readiness: () => get<DoctorReport>('readiness'),
    stale: (scope: Scope) => get<StaleReport>('stale', { ...scope }),

    tree: (scope: Scope) => get<TreeResponse>('tree', { ...scope }),
    saveNavigation: (scope: Scope, change: { tree?: NavJson; patch?: Record<string, unknown>[]; based_on?: string; summary?: string }) => send<WriteResult>('PUT', 'navigation', { ...scope, ...change }),

    page: (scope: Scope, path: string) => get<{ page: AdminPage; pending: Revision[] }>('page', { ...scope, path }),
    createPage: (scope: Scope, page: { path: string; frontmatter: Frontmatter; body: string; mode?: SaveMode; summary?: string }) => send<WriteResult>('POST', 'page', { ...scope, ...page }),
    savePage: (scope: Scope, page: { path: string; frontmatter: Frontmatter; body: string; based_on?: string; mode?: SaveMode; summary?: string }) => send<WriteResult>('PUT', 'page', { ...scope, ...page }),
    movePage: (scope: Scope, path: string, to: string, basedOn?: string) => send<WriteResult>('POST', 'page/move', { ...scope, path, to, based_on: basedOn }),
    deletePage: (scope: Scope, path: string, basedOn?: string) => send<WriteResult>('DELETE', 'page', { ...scope, path, based_on: basedOn }),

    preview: (scope: Scope, page: { path?: string; frontmatter: Frontmatter; body: string }, signal?: AbortSignal) =>
      request<Preview>(url('preview'), { method: 'POST', body: { ...scope, ...page }, signal }).then(settle),
    validate: (scope: Scope, page: { path?: string; frontmatter: Frontmatter; body: string }, signal?: AbortSignal) =>
      request<ValidationReport>(url('validate'), { method: 'POST', body: { ...scope, ...page }, signal }).then(settle),

    manifest: () => get<{ manifest: Record<string, unknown>; exists: boolean; content_hash: string }>('manifest'),
    saveManifest: (manifest: Record<string, unknown>, basedOn?: string, summary?: string) => send<WriteResult>('PUT', 'manifest', { manifest, based_on: basedOn, summary }),

    review: () => get<ReviewQueue>('review'),
    reviewItem: (id: string) => get<RevisionDetail>(`review/${id}`),
    publish: (id: string) => send<WriteResult>('POST', `review/${id}/publish`, {}),
    reject: (id: string, reason?: string) => send<{ outcome: 'rejected'; revision: Revision }>('POST', `review/${id}/reject`, { reason }),
    replace: (id: string, change: { frontmatter?: Frontmatter; body?: string; tree?: NavJson; manifest?: Record<string, unknown>; summary?: string }) =>
      send<WriteResult>('POST', `review/${id}/edit`, change),

    history: (scope: Scope, subject: { type: 'page' | 'navigation' | 'manifest'; path?: string }) => get<{ subject: string; revisions: Revision[] }>('revisions', { ...scope, ...subject }),
    revision: (id: string) => get<RevisionDetail>(`revisions/${id}`),
    revert: (id: string) => send<WriteResult>('POST', `revisions/${id}/revert`, {}),

    activity: (before?: string) => get<Activity>('activity', { before }),
    exportMigration: (revisions: string[], name?: string, summary?: string) => send<MigrationExport>('POST', 'export', { revisions, name, summary }),

    assets: (scope: Scope) => get<{ version: string; assets: Asset[] }>('assets', { ...scope }),
    uploadAsset: async (scope: Scope, file: File, directory?: string): Promise<Result<{ asset: Asset }>> => {
      const form = new FormData()
      form.append('file', file)
      form.append('version', scope.version)
      form.append('locale', scope.locale)
      if (directory) form.append('directory', directory)

      const token = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1]
      const response = await fetch(url('assets'), {
        method: 'POST',
        body: form,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}) },
      })
      const data = await response.json().catch(() => null)

      return response.ok
        ? { ok: true, status: response.status, data }
        : { ok: false, status: response.status, data, errors: data?.errors ?? null, message: (data?.errors ? (Object.values(data.errors)[0] as string[])[0] : data?.message) ?? `The upload failed (${response.status}).` }
    },
    deleteAsset: (scope: Scope, path: string, force = false) => send<{ deleted: string }>('DELETE', 'assets', { ...scope, path, force }),
  }
}

export type Api = ReturnType<typeof createApi>
