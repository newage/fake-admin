import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from '@tanstack/react-router'
import { createAppRouter } from '@/router'
import { onSessionExpired } from '@/lib/session'
import { initI18n } from '@/lib/i18n'
import '@/styles/index.css'

initI18n()

const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } } })
const router = createAppRouter({ queryClient })

// Failed refresh mid-session: clear the cache and redirect to login, preserving the page.
onSessionExpired(() => {
  queryClient.clear()
  void router.navigate({ to: '/login', search: { redirect: router.state.location.href } })
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  </StrictMode>,
)
