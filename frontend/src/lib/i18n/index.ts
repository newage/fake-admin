import i18n from 'i18next'
import { useMemo } from 'react'
import { initReactI18next, useTranslation } from 'react-i18next'
import uk from './locales/uk.json'
import pl from './locales/pl.json'
import { detectLanguage, storeLanguage } from './detect'
import { DEFAULT_LANGUAGE, LANGUAGE_CODES, intlLocale, type LanguageCode } from './languages'

const resources = { uk: { translation: uk }, pl: { translation: pl } } satisfies Record<LanguageCode, unknown>

function syncDocumentLang(lng: string) {
  document.documentElement.lang = lng
}

/** Synchronous init (resources are bundled), so the first render is already in the right language. */
export function initI18n(lng: LanguageCode = detectLanguage()) {
  if (i18n.isInitialized) return i18n
  void i18n.use(initReactI18next).init({
    resources,
    lng,
    fallbackLng: DEFAULT_LANGUAGE,
    supportedLngs: LANGUAGE_CODES,
    interpolation: { escapeValue: false },
    initAsync: false,
  })
  i18n.on('languageChanged', syncDocumentLang)
  syncDocumentLang(i18n.language)
  return i18n
}

/** User's explicit choice: persists it and switches the UI without a reload. */
export function setLanguage(code: LanguageCode) {
  storeLanguage(code)
  return i18n.changeLanguage(code)
}

/** Date formatter bound to the current language's Intl locale. */
export function useDateFormatter() {
  const { i18n: instance } = useTranslation()
  return useMemo(() => {
    const fmt = new Intl.DateTimeFormat(intlLocale(instance.language), { dateStyle: 'short', timeStyle: 'short' })
    return (iso: string | null) => (iso ? fmt.format(new Date(iso)) : '—')
  }, [instance.language])
}

export { i18n }
