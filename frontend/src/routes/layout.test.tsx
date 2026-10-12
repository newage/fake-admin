import { describe, expect, it } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { i18n } from '@/lib/i18n'
import { getAccessToken, getRefreshToken } from '@/lib/session'
import { json, mockFetch, renderApp, sensorsPage, tokenResponse } from '@/test/utils'

function signedIn(role: string, sensors: () => Response = () => json(200, sensorsPage())) {
  localStorage.setItem('fake-admin.refresh', 'R1')
  return mockFetch((url) => {
    if (url.pathname === '/token') return tokenResponse(role)
    if (url.pathname === '/token/revoke') return new Response('', { status: 200 })
    if (url.pathname === '/api/sensors') return sensors()
  })
}

describe('layout', () => {
  it('активний пункт «Датчики» позначено aria-current', async () => {
    signedIn('manager')
    renderApp('/sensors')

    const nav = await screen.findByRole('navigation')
    expect(within(nav).getByRole('link', { name: 'Датчики' })).toHaveAttribute('aria-current', 'page')
  })

  it('польською: навігація й вихід перекладені', async () => {
    signedIn('manager')
    localStorage.setItem('fake-admin.lang', 'pl')
    await i18n.changeLanguage('pl')
    renderApp('/sensors')

    const nav = await screen.findByRole('navigation', { name: 'Nawigacja główna' })
    expect(within(nav).getByRole('link', { name: 'Czujniki' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('button', { name: 'Wyloguj' })).toBeInTheDocument()
  })

  it('logout відкликає refresh, очищає токени і показує логін', async () => {
    const fetchMock = signedIn('manager')
    renderApp('/sensors')
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Вийти' }))

    expect(await screen.findByRole('button', { name: 'Увійти' })).toBeInTheDocument()
    const revoke = fetchMock.mock.calls.find(([u]) => String(u) === '/token/revoke')
    expect(new URLSearchParams(String(revoke?.[1]?.body)).get('token')).toBe('R-next')
    expect(getRefreshToken()).toBeNull()
    expect(getAccessToken()).toBeNull()
  })
})

describe('відображення за роллю', () => {
  it('manager бачить пункт «Датчики»', async () => {
    signedIn('manager')
    renderApp('/sensors')

    const nav = await screen.findByRole('navigation')
    expect(within(nav).getByRole('link', { name: 'Датчики' })).toBeInTheDocument()
  })

  it('user не бачить пункт «Датчики», а прямий перехід показує повідомлення про брак доступу', async () => {
    signedIn('user', () => json(403, { error: 'forbidden' }))
    renderApp('/sensors')

    expect(await screen.findByText('Недостатньо прав для перегляду цієї сторінки')).toBeInTheDocument()
    const nav = screen.getByRole('navigation')
    expect(within(nav).queryByRole('link', { name: 'Датчики' })).not.toBeInTheDocument()
  })

  it('403 від сервера показується як брак доступу навіть для ролі з пунктом меню', async () => {
    signedIn('manager', () => json(403, { error: 'forbidden' }))
    renderApp('/sensors')

    await waitFor(() =>
      expect(screen.getByText('Недостатньо прав для перегляду цієї сторінки')).toBeInTheDocument(),
    )
  })
})
