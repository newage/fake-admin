import {
  clearSession,
  getAccessToken,
  getRefreshToken,
  notifySessionExpired,
  setTokens,
} from './session'

const CLIENT_ID = 'fake-admin-spa'

export class ApiError extends Error {
  status: number
  body: unknown

  constructor(status: number, body: unknown) {
    super(`API error ${status}`)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

interface TokenResponse {
  access_token: string
  refresh_token: string
}

async function readBody(res: Response): Promise<unknown> {
  const text = await res.text()
  if (!text) return null
  try {
    return JSON.parse(text)
  } catch {
    return text
  }
}

async function tokenRequest(params: Record<string, string>): Promise<TokenResponse> {
  const res = await fetch('/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ client_id: CLIENT_ID, ...params }),
  })
  const body = await readBody(res)
  if (!res.ok) throw new ApiError(res.status, body)
  return body as TokenResponse
}

export async function login(email: string, password: string) {
  const t = await tokenRequest({ grant_type: 'password', username: email, password })
  setTokens({ access: t.access_token, refresh: t.refresh_token })
}

// Shared promise: parallel requests do not use the one-time refresh twice.
let refreshing: Promise<void> | null = null

/** Refreshes the token pair. Failure clears the session and signals its loss. */
export function refreshSession(): Promise<void> {
  if (refreshing) return refreshing
  const run = async () => {
    const refresh = getRefreshToken()
    try {
      if (!refresh) throw new ApiError(401, null)
      const t = await tokenRequest({ grant_type: 'refresh_token', refresh_token: refresh })
      setTokens({ access: t.access_token, refresh: t.refresh_token })
    } catch (e) {
      // Reset the session only when the token is rejected (400/401); 5xx and network errors are transient.
      if (e instanceof ApiError && (e.status === 400 || e.status === 401)) {
        expireSession()
        throw new ApiError(401, null)
      }
      throw e
    }
  }
  // Attach finally to the promise rather than inside run: a synchronous failure must not leave it in `refreshing`.
  const p: Promise<void> = run().finally(() => {
    if (refreshing === p) refreshing = null
  })
  refreshing = p
  return p
}

function expireSession() {
  clearSession()
  notifySessionExpired()
}

/** Ensures an access token is present (e.g. after a page reload). */
export async function ensureAccessToken() {
  if (!getAccessToken() && getRefreshToken()) await refreshSession()
}

export async function logout() {
  const refresh = getRefreshToken()
  try {
    if (refresh) {
      await fetch('/token/revoke', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: refresh }),
      })
    }
  } catch {
    // Revocation is best-effort: local tokens are deleted regardless.
  } finally {
    clearSession()
  }
}

function send(path: string, init: RequestInit, token: string | null) {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)
  return fetch(path, { ...init, headers })
}

export async function apiFetch<T = unknown>(path: string, init: RequestInit = {}): Promise<T> {
  if (!getAccessToken() && getRefreshToken()) await refreshSession()

  const used = getAccessToken()
  let res = await send(path, init, used)

  if (res.status === 401) {
    // A parallel request may have already refreshed the token: then just retry with the new one.
    if (getAccessToken() === used) await refreshSession()
    res = await send(path, init, getAccessToken())
    // The fresh token was rejected too (e.g. session revoked): send back to login.
    if (res.status === 401) {
      expireSession()
      throw new ApiError(401, await readBody(res))
    }
  }

  const body = await readBody(res)
  if (!res.ok) throw new ApiError(res.status, body)
  return body as T
}
