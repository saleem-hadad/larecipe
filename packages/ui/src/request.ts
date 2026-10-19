export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  headers?: Record<string, string>
  signal?: AbortSignal
}

export type RequestResult<T = unknown> =
  | { ok: true; status: number; data: T }
  | { ok: false; status: number; data: unknown; errors: Record<string, string[]> | null; message: string | null }

function xsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1] ?? '') : null
}

/**
 * Call the host app's own Laravel routes from a custom component: same origin
 * only, with the session cookie and Laravel's XSRF token, JSON in and out.
 * Validation errors (422) come back as { ok: false, errors }.
 */
export async function request<T = unknown>(path: string, options: RequestOptions = {}): Promise<RequestResult<T>> {
  const url = new URL(path, window.location.origin)
  if (url.origin !== window.location.origin) {
    throw new Error(`request() only calls this site's own routes; refusing ${url.origin}.`)
  }

  const headers: Record<string, string> = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...options.headers }
  const token = xsrfToken()
  if (token) headers['X-XSRF-TOKEN'] = token
  if (options.body !== undefined) headers['Content-Type'] = 'application/json'

  const response = await fetch(url, {
    method: options.method ?? (options.body !== undefined ? 'POST' : 'GET'),
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    credentials: 'same-origin',
    signal: options.signal,
  })

  const data = response.headers.get('Content-Type')?.includes('json') ? await response.json().catch(() => null) : await response.text()

  if (response.ok) {
    return { ok: true, status: response.status, data: data as T }
  }

  const body = (data ?? {}) as { errors?: Record<string, string[]>; message?: string }
  return { ok: false, status: response.status, data, errors: body.errors ?? null, message: body.message ?? null }
}
