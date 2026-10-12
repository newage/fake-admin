import '@testing-library/jest-dom/vitest'
import { afterEach } from 'vitest'
import { clearSession } from '@/lib/session'
import { cleanup } from '@testing-library/react'

afterEach(() => {
  cleanup()
  clearSession()
  localStorage.clear()
})
