import { describe, expect, it } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { json, mockFetch, renderApp, sensorsPage, tokenResponse } from '@/test/utils'

function api(loginResponse: () => Response) {
  return mockFetch((url) => {
    if (url.pathname === '/token') return loginResponse()
    if (url.pathname === '/api/sensors') return json(200, sensorsPage())
  })
}

describe('сторінка логіну', () => {
  it('невалідний email: запит не надсилається, поле показує помилку', async () => {
    const fetchMock = api(() => tokenResponse('manager'))
    renderApp('/login')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'не-email')
    await user.type(screen.getByLabelText('Пароль'), 'secret-pass')
    await user.click(screen.getByRole('button', { name: 'Увійти' }))

    expect(await screen.findByText('Вкажіть коректний email')).toBeInTheDocument()
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('порожні поля: помилки валідації, без запиту', async () => {
    const fetchMock = api(() => tokenResponse('manager'))
    renderApp('/login')

    await userEvent.click(await screen.findByRole('button', { name: 'Увійти' }))

    expect(await screen.findByText('Вкажіть email')).toBeInTheDocument()
    expect(screen.getByText('Вкажіть пароль')).toBeInTheDocument()
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('відхилені дані: загальне повідомлення без розкриття, що саме невірно', async () => {
    api(() => json(400, { error: 'invalid_grant' }))
    renderApp('/login')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'a@example.com')
    await user.type(screen.getByLabelText('Пароль'), 'wrong-pass')
    await user.click(screen.getByRole('button', { name: 'Увійти' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Невірний email або пароль')
    expect(localStorage.length).toBe(0)
  })

  it('успішний вхід веде на список датчиків', async () => {
    api(() => tokenResponse('manager'))
    const { router } = renderApp('/login')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'm@example.com')
    await user.type(screen.getByLabelText('Пароль'), 'secret-pass')
    await user.click(screen.getByRole('button', { name: 'Увійти' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/sensors'))
    expect(await screen.findByText('SN-000001')).toBeInTheDocument()
  })

  it('після входу повертає на сторінку, яку відкривали до входу', async () => {
    api(() => tokenResponse('manager'))
    const { router } = renderApp('/sensors?page=1&name=CO2')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'm@example.com')
    await user.type(screen.getByLabelText('Пароль'), 'secret-pass')
    await user.click(screen.getByRole('button', { name: 'Увійти' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/sensors'))
    expect(router.state.location.search).toMatchObject({ name: 'CO2' })
  })

  it('ігнорує зовнішній redirect (лише внутрішні шляхи)', async () => {
    api(() => tokenResponse('manager'))
    const { router } = renderApp('/login?redirect=https://evil.example/x')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'm@example.com')
    await user.type(screen.getByLabelText('Пароль'), 'secret-pass')
    await user.click(screen.getByRole('button', { name: 'Увійти' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/sensors'))
  })
})
