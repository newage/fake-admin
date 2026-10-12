import { useTranslation } from 'react-i18next'
import { LANGUAGES, isLanguage } from '@/lib/i18n/languages'
import { setLanguage } from '@/lib/i18n'

export function LanguageSwitcher() {
  const { t, i18n } = useTranslation()
  return (
    <select
      aria-label={t('language.label')}
      className="h-9 rounded-md border bg-card px-2 text-sm text-foreground"
      value={i18n.resolvedLanguage}
      onChange={(e) => {
        if (isLanguage(e.target.value)) void setLanguage(e.target.value)
      }}
    >
      {LANGUAGES.map(({ code, name }) => (
        <option key={code} value={code}>
          {name}
        </option>
      ))}
    </select>
  )
}
