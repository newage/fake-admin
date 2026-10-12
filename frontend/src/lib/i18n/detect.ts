import { DEFAULT_LANGUAGE, isLanguage, type LanguageCode } from './languages'

export const LANGUAGE_STORAGE_KEY = 'fake-admin.lang'

export function readStoredLanguage(): LanguageCode | null {
  try {
    const value = localStorage.getItem(LANGUAGE_STORAGE_KEY)
    return isLanguage(value) ? value : null
  } catch {
    return null
  }
}

export function storeLanguage(code: LanguageCode): void {
  try {
    localStorage.setItem(LANGUAGE_STORAGE_KEY, code)
  } catch {
    // Storage unavailable: the choice just won't survive a reload.
  }
}

/** First browser language whose primary subtag is supported (`pl-PL` → `pl`). */
export function matchBrowserLanguage(tags: readonly string[]): LanguageCode | null {
  for (const tag of tags) {
    const primary = tag.split('-')[0]?.toLowerCase()
    if (isLanguage(primary)) return primary
  }
  return null
}

function browserLanguages(): readonly string[] {
  if (typeof navigator === 'undefined') return []
  return navigator.languages?.length ? navigator.languages : navigator.language ? [navigator.language] : []
}

/** Stored choice → browser languages → default. */
export function detectLanguage(): LanguageCode {
  return readStoredLanguage() ?? matchBrowserLanguage(browserLanguages()) ?? DEFAULT_LANGUAGE
}
