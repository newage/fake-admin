import { createRoute, useNavigate } from '@tanstack/react-router'
import { z } from 'zod'
import { LoginPage } from '@/features/auth/login-page'
import { rootRoute } from './root'

// Internal paths only: protection against open redirect.
const internalPath = z
  .string()
  .refine((p) => p.startsWith('/') && !p.startsWith('//') && !p.includes('\\'))
  .optional()
  .catch(undefined)

export const loginRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '/login',
  // Always return the key (even undefined): the router merges raw params with the result,
  // so an omitted key would leave the unsafe value from the URL.
  validateSearch: (raw: Record<string, unknown>): { redirect?: string | undefined } => ({
    redirect: internalPath.parse(raw.redirect),
  }),
  component: function Login() {
    const { redirect } = loginRoute.useSearch()
    const navigate = useNavigate()
    return (
      <LoginPage
        onSuccess={() => void (redirect ? navigate({ href: redirect, replace: true }) : navigate({ to: '/sensors', replace: true }))}
      />
    )
  },
})
