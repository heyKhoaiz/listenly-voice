import { config } from './config'

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
  if (!res.ok) throw new Error((data && data.message) || `Request failed (${res.status})`)
  return data as T
}
