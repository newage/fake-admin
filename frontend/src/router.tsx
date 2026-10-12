import type { QueryClient } from '@tanstack/react-query'
import { createRouter, type RouterHistory } from '@tanstack/react-router'
import { appRoute } from '@/routes/app'
import { indexRoute } from '@/routes'
import { loginRoute } from '@/routes/login'
import { rootRoute } from '@/routes/root'
import { sensorsRoute } from '@/routes/sensors'

const routeTree = rootRoute.addChildren([indexRoute, loginRoute, appRoute.addChildren([sensorsRoute])])

export function createAppRouter({ history, queryClient }: { history?: RouterHistory; queryClient: QueryClient }) {
  return createRouter({ routeTree, history, context: { queryClient }, defaultPreload: 'intent' })
}

declare module '@tanstack/react-router' {
  interface Register {
    router: ReturnType<typeof createAppRouter>
  }
}
