import { describe, expect, it } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { json, mockFetch, renderApp, sensorsPage, tokenResponse } from '@/test/utils'

function signedIn(sensors: (url: URL) => Response) {
  localStorage.setItem('fake-admin.refresh', 'R1')
  return mockFetch((url) => {
    if (url.pathname === '/token') return tokenResponse('manager')
    if (url.pathname === '/api/sensors') return sensors(url)
  })
}

const sensorCalls = (fetchMock: ReturnType<typeof signedIn>) =>
  fetchMock.mock.calls.map(([u]) => new URL(String(u), 'http://localhost')).filter((u) => u.pathname === '/api/sensors')

describe('екран датчиків', () => {
  it('відновлює стан із URL: параметри потрапляють у запит до API', async () => {
    const fetchMock = signedIn(() => json(200, sensorsPage({ total: 120, page: 3, page_size: 10 })))
    renderApp('/sensors?page=3&page_size=10&sort=name&order=asc&name=CO2&created_from=2026-01-01')

    expect(await screen.findByText('Сторінка 3 з 12')).toBeInTheDocument()
    const q = sensorCalls(fetchMock)[0].searchParams
    expect(Object.fromEntries(q)).toEqual({
      page: '3',
      page_size: '10',
      sort: 'name',
      order: 'asc',
      name: 'CO2',
      created_from: '2026-01-01',
    })
    expect(screen.getByLabelText('Назва')).toHaveValue('CO2')
  })

  it('некоректні параметри URL не ламають екран: запит іде з дефолтами', async () => {
    const fetchMock = signedIn(() => json(200, sensorsPage()))
    renderApp('/sensors?page=-1&sort=secret_enc&name=ab')

    expect(await screen.findByText('SN-000001')).toBeInTheDocument()
    expect(Object.fromEntries(sensorCalls(fetchMock)[0].searchParams)).toEqual({
      page: '1',
      page_size: '25',
      sort: 'created_at',
      order: 'desc',
    })
  })

  it('порожній результат: повідомлення і скидання фільтрів', async () => {
    signedIn((url) =>
      json(200, url.searchParams.has('name') ? sensorsPage({ items: [], total: 0 }) : sensorsPage()),
    )
    const { router } = renderApp('/sensors?name=zzz')
    const user = userEvent.setup()

    expect(await screen.findByText('Нічого не знайдено')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Скинути фільтри' }))

    expect(await screen.findByText('SN-000001')).toBeInTheDocument()
    expect(router.state.location.search).not.toHaveProperty('name')
  })

  it('помилка завантаження: повідомлення і повтор', async () => {
    let calls = 0
    signedIn(() => (++calls === 1 ? json(500, { error: 'boom' }) : json(200, sensorsPage())))
    renderApp('/sensors')
    const user = userEvent.setup()

    expect(await screen.findByText('Не вдалося завантажити датчики')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Повторити' }))

    expect(await screen.findByText('SN-000001')).toBeInTheDocument()
  })

  it('зміна фільтра скидає сторінку на першу', async () => {
    signedIn(() => json(200, sensorsPage({ total: 500, page: 4 })))
    const { router } = renderApp('/sensors?page=4')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Код'), 'SN-00')

    await waitFor(() => expect(router.state.location.search).toMatchObject({ code: 'SN-00', page: 1 }))
  })

  it('фільтр коротший за 3 символи не потрапляє в URL', async () => {
    const fetchMock = signedIn(() => json(200, sensorsPage()))
    const { router } = renderApp('/sensors')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Назва'), 'ab')
    await new Promise((r) => setTimeout(r, 600))

    expect(screen.getByText('Мінімум 3 символи')).toBeInTheDocument()
    expect(router.state.location.search).not.toHaveProperty('name')
    expect(sensorCalls(fetchMock)).toHaveLength(1)
  })

  it('сортування за колонкою пише порядок в URL і скидає сторінку', async () => {
    signedIn(() => json(200, sensorsPage({ total: 500, page: 2 })))
    const { router } = renderApp('/sensors?page=2')
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Назва/ }))

    await waitFor(() => expect(router.state.location.search).toMatchObject({ sort: 'name', order: 'asc', page: 1 }))
  })

  it('сторінка поза діапазоном: пропонує повернутись на першу', async () => {
    signedIn(() => json(200, sensorsPage({ items: [], total: 30, page: 9 })))
    const { router } = renderApp('/sensors?page=9')
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'На першу сторінку' }))

    await waitFor(() => expect(router.state.location.search).toMatchObject({ page: 1 }))
  })

  it('пагінація: «Далі» збільшує сторінку, «Назад» на першій вимкнено', async () => {
    signedIn(() => json(200, sensorsPage({ total: 60 })))
    const { router } = renderApp('/sensors')
    const user = userEvent.setup()

    expect(await screen.findByRole('button', { name: 'Назад' })).toBeDisabled()
    await user.click(screen.getByRole('button', { name: 'Далі' }))

    await waitFor(() => expect(router.state.location.search).toMatchObject({ page: 2 }))
  })
})
