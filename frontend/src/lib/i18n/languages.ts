/** Single registry of UI languages: add a language = add a locales/<code>.json file and an entry here. */
export const LANGUAGES = [
  { code: 'uk', locale: 'uk-UA', name: 'Українська' },
  { code: 'pl', locale: 'pl-PL', name: 'Polski' },
] as const

export type LanguageCode = (typeof LANGUAGES)[number]['code']

export const DEFAULT_LANGUAGE: LanguageCode = 'uk'

export const LANGUAGE_CODES = LANGUAGES.map((l) => l.code) as LanguageCode[]

export function isLanguage(value: unknown): value is LanguageCode {
  return typeof value === 'string' && (LANGUAGE_CODES as string[]).includes(value)
}

/** Intl locale (dates, numbers) for a language code; unknown codes fall back to the default language. */
export function intlLocale(code: string): string {
  return (LANGUAGES.find((l) => l.code === code) ?? LANGUAGES.find((l) => l.code === DEFAULT_LANGUAGE)!).locale
}
