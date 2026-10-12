import { useSyncExternalStore } from 'react'
import { Link, Outlet, useNavigate } from '@tanstack/react-router'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Activity, LogOut, type LucideIcon } from 'lucide-react'
import { logout } from '@/lib/api-client'
import { getRole, subscribeSession, type Role } from '@/lib/session'
import { Button } from '@/components/ui/button'
import { LanguageSwitcher } from '@/components/language-switcher'
import { cn } from '@/lib/utils'

interface NavItem {
  to: '/sensors'
  labelKey: string
  icon: LucideIcon
  // Only for showing the item; the source of truth is the server's 403.
  roles: readonly Role[]
}

const NAV: NavItem[] = [{ to: '/sensors', labelKey: 'nav.sensors', icon: Activity, roles: ['admin', 'manager'] }]

function useRole() {
  return useSyncExternalStore(subscribeSession, getRole)
}

export function AppLayout() {
  const { t } = useTranslation()
  const role = useRole()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const items = NAV.filter((item) => role !== null && item.roles.includes(role))

  async function onLogout() {
    await logout()
    queryClient.clear()
    await navigate({ to: '/login', search: {} })
  }

  return (
    <div className="flex min-h-screen">
      <aside className="flex w-60 shrink-0 flex-col bg-sidebar px-4 py-6 text-sidebar-foreground">
        <div className="mb-8 flex items-center gap-3 px-2">
          <div className="grid size-9 place-items-center rounded-md bg-primary text-primary-foreground">
            <Activity className="size-5" aria-hidden />
          </div>
          <span className="text-sm font-semibold leading-tight">Fake Admin</span>
        </div>

        <p className="mb-2 px-3 text-xs uppercase tracking-wide text-muted-foreground">{t('nav.section')}</p>
        <nav aria-label={t('nav.main')} className="flex flex-col gap-1">
          {items.map(({ to, labelKey, icon: Icon }) => (
            <Link
              key={to}
              to={to}
              activeProps={{ className: 'bg-sidebar-pill border-border shadow-card', 'aria-current': 'page' }}
              className={cn(
                'flex h-11 items-center gap-3 rounded-full border border-transparent px-4 text-sm',
                'hover:bg-sidebar-pill/60',
              )}
            >
              <Icon className="size-5" aria-hidden />
              {t(labelKey)}
            </Link>
          ))}
        </nav>

        <div className="mt-auto border-t pt-4">
          <p className="mb-2 px-3 text-sm text-muted-foreground">{role ?? ''}</p>
          <Button variant="ghost" className="w-full justify-start rounded-full px-4" onClick={onLogout}>
            <LogOut className="size-5" aria-hidden />
            {t('nav.logout')}
          </Button>
        </div>
      </aside>

      <main className="min-w-0 flex-1 p-[var(--space-page)]">
        <div className="mb-4 flex justify-end">
          <LanguageSwitcher />
        </div>
        <Outlet />
      </main>
    </div>
  )
}
