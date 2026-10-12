import { afterEach, describe, expect, it, vi } from 'vitest'
import uk from './locales/uk.json'
import pl from './locales/pl.json'
import { LANGUAGES } from './languages'
import { LANGUAGE_STORAGE_KEY, detectLanguage, matchBrowserLanguage, storeLanguage } from './detect'
import { i18n, setLanguage } from './index'

function keys(obj: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(obj).flatMap(([k, v]) =>
    v && typeof v === 'object' ? keys(v as Record<string, unknown>, `${prefix}${k}.`) : [`${prefix}${k}`],
  )
}

function browserLanguages(languages: string[]) {
  vi.spyOn(navigator, 'languages', 'get').mockReturnValue(languages)
}

afterEach(() => {
  vi.restoreAllMocks()
})

describe('реєстр мов і ресурси', () => {
  it('набори ключів у всіх мовах однакові', () => {
    expect(keys(pl).sort()).toEqual(keys(uk).sort())
  })

  it('реєстр містить uk і pl з Intl-локалями', () => {
    expect(LANGUAGES.map((l) => [l.code, l.locale])).toEqual([
      ['uk', 'uk-UA'],
      ['pl', 'pl-PL'],
    ])
  })
})

describe('визначення мови', () => {
  it('pl-PL у браузері → pl', () => {
    browserLanguages(['pl-PL', 'en'])
    expect(detectLanguage()).toBe('pl')
  })

  it('жодна мова не підтримується → uk', () => {
    browserLanguages(['de-DE', 'en'])
    expect(detectLanguage()).toBe('uk')
  })

  it('збіг за основною частиною береться з першої підтримуваної', () => {
    expect(matchBrowserLanguage(['de-DE', 'PL-pl', 'uk'])).toBe('pl')
  })

  it('збережений вибір важливіший за мову браузера', () => {
    browserLanguages(['uk-UA'])
    storeLanguage('pl')
    expect(detectLanguage()).toBe('pl')
  })

  it('зіпсоване значення ігнорується', () => {
    browserLanguages(['pl-PL'])
    localStorage.setItem(LANGUAGE_STORAGE_KEY, 'xx')
    expect(detectLanguage()).toBe('pl')
  })

  it('недоступне сховище не ламає ні читання, ні запис', () => {
    browserLanguages(['pl-PL'])
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('denied')
    })
    expect(detectLanguage()).toBe('pl')
    expect(() => storeLanguage('uk')).not.toThrow()
  })
})

describe('i18n', () => {
  afterEach(async () => {
    await i18n.changeLanguage('uk')
  })

  it('перемикання на pl ставить <html lang="pl"> і записує вибір', async () => {
    await setLanguage('pl')
    expect(document.documentElement.lang).toBe('pl')
    expect(localStorage.getItem(LANGUAGE_STORAGE_KEY)).toBe('pl')
    expect(i18n.t('nav.sensors')).toBe('Czujniki')
  })

  it('польські форми множини: 1, 2–4, 5+', async () => {
    await i18n.changeLanguage('pl')
    expect([1, 2, 5].map((count) => i18n.t('sensors.filters.min', { count }))).toEqual([
      'Minimum 1 znak',
      'Minimum 2 znaki',
      'Minimum 5 znaków',
    ])
  })
})
