import { describe, expect, it } from 'vitest'
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { LANGUAGE_STORAGE_KEY } from '@/lib/i18n/detect'
import { json, mockFetch, renderApp, sensorsPage, tokenResponse } from '@/test/utils'

function signedIn() {
  localStorage.setItem('fake-admin.refresh', 'R1')
  return mockFetch((url) => {
    if (url.pathname === '/token') return tokenResponse('manager')
    if (url.pathname === '/api/sensors') return json(200, sensorsPage())
  })
}

describe('перемикач мови', () => {
  it('є на сторінці логіну; вибір змінює мову, <html lang> і localStorage', async () => {
    mockFetch(() => undefined)
    renderApp('/login')
    const user = userEvent.setup()

    await user.selectOptions(await screen.findByRole('combobox', { name: 'Мова' }), 'pl')

    expect(await screen.findByRole('button', { name: 'Zaloguj' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Język' })).toHaveValue('pl')
    expect(document.documentElement.lang).toBe('pl')
    expect(localStorage.getItem(LANGUAGE_STORAGE_KEY)).toBe('pl')
  })

  it('є в AppLayout; навігація й вихід перекладаються', async () => {
    signedIn()
    renderApp('/sensors')
    const user = userEvent.setup()

    await user.selectOptions(await screen.findByRole('combobox', { name: 'Мова' }), 'pl')

    const nav = screen.getByRole('navigation', { name: 'Nawigacja główna' })
    expect(within(nav).getByRole('link', { name: 'Czujniki' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('button', { name: 'Wyloguj' })).toBeInTheDocument()
  })
})

describe('переклад екранів', () => {
  it('помилка входу показується польською після перемикання, введені значення лишаються', async () => {
    mockFetch((url) => (url.pathname === '/token' ? json(400, { error: 'invalid_grant' }) : undefined))
    renderApp('/login')
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Email'), 'a@example.com')
    await user.type(screen.getByLabelText('Пароль'), 'wrong-pass')
    await user.click(screen.getByRole('button', { name: 'Увійти' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Невірний email або пароль')

    await user.selectOptions(screen.getByRole('combobox', { name: 'Мова' }), 'pl')

    expect(screen.getByRole('alert')).toHaveTextContent('Nieprawidłowy email lub hasło')
    expect(screen.getByLabelText('Email')).toHaveValue('a@example.com')
    expect(screen.getByLabelText('Hasło')).toHaveValue('wrong-pass')
  })

  it('помилки валідації перекладаються без повторної перевірки форми', async () => {
    mockFetch(() => undefined)
    renderApp('/login')
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Увійти' }))
    expect(await screen.findByText('Вкажіть email')).toBeInTheDocument()

    await user.selectOptions(screen.getByRole('combobox', { name: 'Мова' }), 'pl')

    expect(screen.getByText('Podaj email')).toBeInTheDocument()
    expect(screen.getByText('Podaj hasło')).toBeInTheDocument()
  })

  it('датчики: дата за pl-PL, фільтри з URL не скидаються при перемиканні', async () => {
    signedIn()
    const { router } = renderApp('/sensors?page=1&name=CO2')
    const user = userEvent.setup()
    const dateIn = (locale: string) =>
      new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'short' }).format(new Date('2025-07-03T18:54:05Z'))

    expect(await screen.findByText(dateIn('uk-UA'))).toBeInTheDocument()
    await user.selectOptions(screen.getByRole('combobox', { name: 'Мова' }), 'pl')

    expect(screen.getByText(dateIn('pl-PL'))).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: /Utworzono/ })).toBeInTheDocument()
    expect(screen.getByLabelText('Nazwa')).toHaveValue('CO2')
    expect(router.state.location.search).toMatchObject({ name: 'CO2' })
  })

  it('польська множина в підказці фільтра', async () => {
    signedIn()
    renderApp('/sensors')
    const user = userEvent.setup()

    await user.selectOptions(await screen.findByRole('combobox', { name: 'Мова' }), 'pl')
    await user.type(screen.getByLabelText('Nazwa'), 'ab')

    expect(await screen.findByText('Minimum 3 znaki')).toBeInTheDocument()
  })
})
