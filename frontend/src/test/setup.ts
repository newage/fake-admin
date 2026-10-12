import '@testing-library/jest-dom/vitest'
import { afterEach } from 'vitest'
import { i18n, initI18n } from '@/lib/i18n'
import { clearSession } from '@/lib/session'
import { cleanup } from '@testing-library/react'

// Existing tests assert Ukrainian strings: always start (and restart) from `uk`.
initI18n('uk')

afterEach(async () => {
  cleanup()
  clearSession()
  localStorage.clear()
  await i18n.changeLanguage('uk')
})
