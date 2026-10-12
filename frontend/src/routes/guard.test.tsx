import { describe, expect, it } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { json, mockFetch, renderApp, sensorsPage, tokenResponse } from '@/test/utils'

describe('захищені маршрути', () => {
  it('неавторизований на /sensors потрапляє на логін, а redirect зберігає запитаний URL', async () => {
    mockFetch(() => undefined)
    const { router } = renderApp('/sensors?page=3')

    expect(await screen.findByRole('button', { name: 'Увійти' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(router.state.location.search).toMatchObject({ redirect: '/sensors?page=3' })
  })

  it('корінь / без сесії веде на логін', async () => {
    mockFetch(() => undefined)
    renderApp('/')
    expect(await screen.findByRole('button', { name: 'Увійти' })).toBeInTheDocument()
  })

  it('з refresh у localStorage (після перезавантаження) сесія відновлюється без логіну', async () => {
    localStorage.setItem('fake-admin.refresh', 'R1')
    mockFetch((url) => {
      if (url.pathname === '/token') return tokenResponse('manager')
      if (url.pathname === '/api/sensors') return json(200, sensorsPage())
    })
    renderApp('/sensors')

    expect(await screen.findByText('SN-000001')).toBeInTheDocument()
  })

  it('недійсний refresh: сесію очищено, показано логін', async () => {
    localStorage.setItem('fake-admin.refresh', 'dead')
    mockFetch((url) => {
      if (url.pathname === '/token') return json(400, { error: 'invalid_grant' })
    })
    renderApp('/sensors')

    expect(await screen.findByRole('button', { name: 'Увійти' })).toBeInTheDocument()
    await waitFor(() => expect(localStorage.getItem('fake-admin.refresh')).toBeNull())
  })
})
