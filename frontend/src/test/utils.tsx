import { render } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider, createMemoryHistory } from '@tanstack/react-router'
import { vi } from 'vitest'
import { createAppRouter } from '@/router'

export type Handler = (url: URL, init: RequestInit) => Response | Promise<Response> | undefined

export function json(status: number, body: unknown) {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

/** Mocks fetch by path; unhandled requests return 404. Returns the mock for asserting calls. */
export function mockFetch(handler: Handler) {
  const fn = vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
    const url = new URL(String(input), 'http://localhost')
    return (await handler(url, init)) ?? json(404, { error: 'not_found' })
  })
  vi.stubGlobal('fetch', fn)
  return fn
}

export function fakeJwt(role: string) {
  return `h.${btoa(JSON.stringify({ scopes: [`role:${role}`] }))}.s`
}

export function tokenResponse(role: string, refresh = 'R-next') {
  return json(200, { token_type: 'Bearer', expires_in: 300, access_token: fakeJwt(role), refresh_token: refresh })
}

export function renderApp(path: string) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const history = createMemoryHistory({ initialEntries: [path] })
  const router = createAppRouter({ history, queryClient })
  const utils = render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
  return { router, queryClient, ...utils }
}

export function sensorsPage(overrides: Partial<{ items: unknown[]; total: number; page: number; page_size: number }> = {}) {
  const item = {
    id: 1,
    code: 'SN-000001',
    name: 'CO2 sensor 1',
    type: 'co2',
    unit: 'ppm',
    device_id: 'DEV-1',
    location: 'Server room',
    status: 'active',
    created_at: '2025-07-03T18:54:05Z',
    last_seen_at: '2026-10-11T11:09:05Z',
  }
  return { items: [item], total: 1, page: 1, page_size: 25, ...overrides }
}
