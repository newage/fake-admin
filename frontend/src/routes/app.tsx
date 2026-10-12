import { createRoute, redirect } from '@tanstack/react-router'
import { AppLayout } from '@/components/app-layout'
import { ApiError, ensureAccessToken } from '@/lib/api-client'
import { hasSession } from '@/lib/session'
import { rootRoute } from './root'

/** Protected layout: without a session (or with an invalid refresh) redirects to login and remembers the requested page. */
export const appRoute = createRoute({
  getParentRoute: () => rootRoute,
  id: 'app',
  beforeLoad: async ({ location }) => {
    const toLogin = () => redirect({ to: '/login', search: { redirect: location.href } })
    if (!hasSession()) throw toLogin()
    try {
      await ensureAccessToken()
    } catch (e) {
      // A network error does not mean the session is lost: errorComponent shows it.
      if (e instanceof ApiError) throw toLogin()
      throw e
    }
  },
  component: AppLayout,
})
