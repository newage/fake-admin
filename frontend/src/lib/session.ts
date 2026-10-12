// The only place tokens live: refresh in localStorage, access only in memory.
// Do not create other token caches (design.md, "Session").

const REFRESH_KEY = 'fake-admin.refresh'

export type Role = 'admin' | 'manager' | 'user' | (string & {})

let accessToken: string | null = null
const changeListeners = new Set<() => void>()
const expiredListeners = new Set<() => void>()

function storage(): Storage | null {
  try {
    return localStorage
  } catch {
    return null
  }
}

export function getAccessToken() {
  return accessToken
}

export function getRefreshToken() {
  return storage()?.getItem(REFRESH_KEY) ?? null
}

export function setTokens(tokens: { access: string; refresh: string }) {
  accessToken = tokens.access
  storage()?.setItem(REFRESH_KEY, tokens.refresh)
  changeListeners.forEach((l) => l())
}

export function clearSession() {
  accessToken = null
  storage()?.removeItem(REFRESH_KEY)
  changeListeners.forEach((l) => l())
}

/** A session is considered present while a refresh token exists: access can be obtained from it. */
export function hasSession() {
  return getRefreshToken() !== null
}

export function subscribeSession(listener: () => void) {
  changeListeners.add(listener)
  return () => {
    changeListeners.delete(listener)
  }
}

/** Called when the session is lost due to a failed refresh (not on manual logout). */
export function onSessionExpired(listener: () => void) {
  expiredListeners.add(listener)
  return () => {
    expiredListeners.delete(listener)
  }
}

export function notifySessionExpired() {
  expiredListeners.forEach((l) => l())
}

function decodeClaims(token: string): { scopes?: unknown } | null {
  const part = token.split('.')[1]
  if (!part) return null
  try {
    const b64 = part.replace(/-/g, '+').replace(/_/g, '/')
    return JSON.parse(atob(b64.padEnd(Math.ceil(b64.length / 4) * 4, '=')))
  } catch {
    return null
  }
}

/** Role from the `scopes` claim (`role:<name>`). For UI display only; permissions are decided by the server. */
export function getRole(): Role | null {
  if (!accessToken) return null
  const scopes = decodeClaims(accessToken)?.scopes
  if (!Array.isArray(scopes)) return null
  const scope = scopes.find((s): s is string => typeof s === 'string' && s.startsWith('role:'))
  return scope ? scope.slice('role:'.length) : null
}
