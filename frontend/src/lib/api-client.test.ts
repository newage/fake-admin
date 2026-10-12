import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiFetch, ApiError, login, logout } from './api-client'
import { clearSession, getAccessToken, getRefreshToken, setTokens } from './session'

function jwt(role: string) {
  const payload = btoa(JSON.stringify({ scopes: [`role:${role}`] }))
  return `h.${payload}.s`
}

function json(status: number, body: unknown) {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const tokens = (access: string, refresh: string) => ({
  token_type: 'Bearer',
  expires_in: 300,
  access_token: access,
  refresh_token: refresh,
})

describe('api-client', () => {
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
    clearSession()
  })

  it('додає Bearer і повертає JSON', async () => {
    setTokens({ access: 'A1', refresh: 'R1' })
    fetchMock.mockResolvedValueOnce(json(200, { ok: 1 }))

    await expect(apiFetch('/api/x')).resolves.toEqual({ ok: 1 })

    const headers = new Headers(fetchMock.mock.calls[0][1]?.headers)
    expect(headers.get('Authorization')).toBe('Bearer A1')
  })

  it('на 401 один раз оновлює токени й повторює запит', async () => {
    setTokens({ access: 'old', refresh: 'R1' })
    fetchMock
      .mockResolvedValueOnce(json(401, { error: 'unauthorized' }))
      .mockResolvedValueOnce(json(200, tokens('new', 'R2')))
      .mockResolvedValueOnce(json(200, { ok: 1 }))

    await expect(apiFetch('/api/x')).resolves.toEqual({ ok: 1 })

    expect(fetchMock).toHaveBeenCalledTimes(3)
    expect(getRefreshToken()).toBe('R2')
    expect(getAccessToken()).toBe('new')
    const retryHeaders = new Headers(fetchMock.mock.calls[2][1]?.headers)
    expect(retryHeaders.get('Authorization')).toBe('Bearer new')
  })

  it('паралельні 401 дають один refresh', async () => {
    setTokens({ access: 'old', refresh: 'R1' })
    let refreshCalls = 0
    fetchMock.mockImplementation(async (input, init) => {
      const url = String(input)
      if (url === '/token') {
        refreshCalls++
        await new Promise((r) => setTimeout(r, 10))
        return json(200, tokens('new', 'R2'))
      }
      const auth = new Headers(init?.headers).get('Authorization')
      return auth === 'Bearer new' ? json(200, { ok: url }) : json(401, { error: 'unauthorized' })
    })

    const results = await Promise.all([apiFetch('/api/a'), apiFetch('/api/b'), apiFetch('/api/c')])

    expect(refreshCalls).toBe(1)
    expect(results).toEqual([{ ok: '/api/a' }, { ok: '/api/b' }, { ok: '/api/c' }])
  })

  it('запит, що отримав 401 зі старим токеном після refresh, не оновлює вдруге', async () => {
    setTokens({ access: 'new', refresh: 'R2' })
    // The request went out with the old token, but the session was already refreshed by another request.
    fetchMock.mockImplementation(async (input, init) => {
      if (String(input) === '/token') throw new Error('refresh не мав викликатись')
      const auth = new Headers(init?.headers).get('Authorization')
      return auth === 'Bearer new' ? json(200, { ok: 1 }) : json(401, {})
    })
    setTokens({ access: 'stale', refresh: 'R2' })
    const p = apiFetch('/api/a')
    setTokens({ access: 'new', refresh: 'R2' })

    await expect(p).resolves.toEqual({ ok: 1 })
  })

  it('невдалий refresh очищає сесію і кидає ApiError 401', async () => {
    setTokens({ access: 'old', refresh: 'R1' })
    const onExpired = vi.fn()
    const unsubscribe = (await import('./session')).onSessionExpired(onExpired)
    fetchMock
      .mockResolvedValueOnce(json(401, {}))
      .mockResolvedValueOnce(json(400, { error: 'invalid_grant' }))

    await expect(apiFetch('/api/x')).rejects.toMatchObject({ status: 401 })

    expect(getRefreshToken()).toBeNull()
    expect(getAccessToken()).toBeNull()
    expect(onExpired).toHaveBeenCalledTimes(1)
    unsubscribe()
  })

  it('без access, але з refresh спершу оновлює токени (після перезавантаження)', async () => {
    localStorage.setItem('fake-admin.refresh', 'R1')
    fetchMock
      .mockResolvedValueOnce(json(200, tokens('A', 'R2')))
      .mockResolvedValueOnce(json(200, { ok: 1 }))

    await expect(apiFetch('/api/x')).resolves.toEqual({ ok: 1 })
    expect(String(fetchMock.mock.calls[0][0])).toBe('/token')
  })

  it('не-401 помилки повертаються як ApiError з тілом', async () => {
    setTokens({ access: 'A', refresh: 'R' })
    fetchMock.mockResolvedValueOnce(json(403, { error: 'forbidden' }))

    const err = await apiFetch('/api/x').catch((e) => e)
    expect(err).toBeInstanceOf(ApiError)
    expect(err).toMatchObject({ status: 403, body: { error: 'forbidden' } })
  })

  it('login зберігає токени: refresh у localStorage, access у памʼяті, роль з claims', async () => {
    fetchMock.mockResolvedValueOnce(json(200, tokens(jwt('manager'), 'R1')))

    await login('m@example.com', 'secret-pass')

    const body = new URLSearchParams(String(fetchMock.mock.calls[0][1]?.body))
    expect(body.get('grant_type')).toBe('password')
    expect(body.get('client_id')).toBe('fake-admin-spa')
    expect(body.get('username')).toBe('m@example.com')
    expect(localStorage.getItem('fake-admin.refresh')).toBe('R1')
    expect(JSON.stringify(localStorage)).not.toContain(jwt('manager'))
    expect((await import('./session')).getRole()).toBe('manager')
  })

  it('login кидає ApiError при відмові сервера', async () => {
    fetchMock.mockResolvedValueOnce(json(400, { error: 'invalid_grant' }))
    await expect(login('a@b.co', 'x')).rejects.toMatchObject({ status: 400 })
    expect(getRefreshToken()).toBeNull()
  })

  it('logout відкликає refresh на сервері й очищає токени', async () => {
    setTokens({ access: 'A', refresh: 'R1' })
    fetchMock.mockResolvedValueOnce(new Response('', { status: 200 }))

    await logout()

    expect(String(fetchMock.mock.calls[0][0])).toBe('/token/revoke')
    expect(new URLSearchParams(String(fetchMock.mock.calls[0][1]?.body)).get('token')).toBe('R1')
    expect(getRefreshToken()).toBeNull()
    expect(getAccessToken()).toBeNull()
  })

  it('logout очищає токени, навіть якщо відкликання не вдалося', async () => {
    setTokens({ access: 'A', refresh: 'R1' })
    fetchMock.mockRejectedValueOnce(new TypeError('network'))

    await logout()

    expect(getRefreshToken()).toBeNull()
  })

  it('refresh без токена не залишає «липкого» відхиленого промису', async () => {
    // Refresh is gone (logout in another tab) but access is still in memory: 401 → refresh without a token.
    setTokens({ access: 'old', refresh: 'R0' })
    localStorage.removeItem('fake-admin.refresh')
    fetchMock.mockResolvedValueOnce(json(401, {}))
    await expect(apiFetch('/api/x')).rejects.toMatchObject({ status: 401 })

    setTokens({ access: 'old', refresh: 'R1' })
    fetchMock
      .mockResolvedValueOnce(json(401, {}))
      .mockResolvedValueOnce(json(200, tokens('new', 'R2')))
      .mockResolvedValueOnce(json(200, { ok: 1 }))

    await expect(apiFetch('/api/x')).resolves.toEqual({ ok: 1 })
  })

  it('5xx від /token при refresh не скидає сесію', async () => {
    setTokens({ access: 'old', refresh: 'R1' })
    fetchMock.mockResolvedValueOnce(json(401, {})).mockResolvedValueOnce(json(502, 'bad gateway'))

    await expect(apiFetch('/api/x')).rejects.toMatchObject({ status: 502 })
    expect(getRefreshToken()).toBe('R1')
  })

  it('повторний 401 після успішного refresh завершує сесію', async () => {
    setTokens({ access: 'old', refresh: 'R1' })
    fetchMock
      .mockResolvedValueOnce(json(401, {}))
      .mockResolvedValueOnce(json(200, tokens('new', 'R2')))
      .mockResolvedValueOnce(json(401, {}))

    await expect(apiFetch('/api/x')).rejects.toMatchObject({ status: 401 })
    expect(getRefreshToken()).toBeNull()
  })
})
