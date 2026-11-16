import { config } from './config'

/** A failed request: the REST error's message, plus its code and data when the server sent them. */
export class ApiError extends Error {
  code: string
  data: Record<string, unknown>
  constructor(message: string, code = '', data: Record<string, unknown> = {}) {
    super(message)
    this.name = 'ApiError'
    this.code = code
    this.data = data
  }
}

// fetch() against /wp-json/listenly/v1/ with the REST nonce.
export async function api<T>(path: string, init: { method?: string; body?: unknown } = {}): Promise<T> {
  const { restUrl, nonce } = config()
  const res = await fetch(restUrl + path, {
    method: init.method ?? 'GET',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
    body: init.body === undefined ? undefined : JSON.stringify(init.body),
    credentials: 'same-origin',
  })
  const data = await res.json().catch(() => null)
  if (!res.ok) {
    const extra = data && typeof data.data === 'object' && data.data !== null ? (data.data as Record<string, unknown>) : {}
    throw new ApiError((data && data.message) || `Request failed (${res.status})`, (data && typeof data.code === 'string' && data.code) || '', extra)
  }
  return data as T
}
