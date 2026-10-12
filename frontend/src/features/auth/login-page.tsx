import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { ApiError, login } from '@/lib/api-client'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LanguageSwitcher } from '@/components/language-switcher'

const schema = z.object({
  email: z.string().trim().min(1, 'validation.email_required').email('validation.email_invalid'),
  password: z.string().min(1, 'validation.password_required'),
})

type Values = z.infer<typeof schema>

// Returns a translation key: the text is resolved at render, so switching language keeps the error.
function errorKey(e: unknown) {
  if (e instanceof ApiError) {
    const error = (e.body as { error?: string } | null)?.error
    // The only exceptional message: the superadmin's first password (docs/api.md).
    if (error === 'invalid_request') return 'loginError.password_short'
    if (e.status === 400 || e.status === 401) return 'loginError.invalid_credentials'
  }
  return 'loginError.generic'
}

export function LoginPage({ onSuccess }: { onSuccess: () => void }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [formError, setFormError] = useState<string | null>(null)
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Values>({ resolver: zodResolver(schema) })

  const submit = handleSubmit(async ({ email, password }) => {
    setFormError(null)
    try {
      await login(email, password)
      queryClient.clear()
      onSuccess()
    } catch (e) {
      setFormError(errorKey(e))
    }
  })

  return (
    <main className="relative grid min-h-screen place-items-center p-6">
      <div className="absolute right-6 top-6">
        <LanguageSwitcher />
      </div>
      <Card className="w-full max-w-sm p-8">
        <h1 className="mb-1 text-2xl font-semibold">{t('login.title')}</h1>
        <p className="mb-8 text-sm text-muted-foreground">{t('login.subtitle')}</p>
        <form onSubmit={submit} noValidate className="flex flex-col gap-5">
          <div className="flex flex-col gap-2">
            <Label htmlFor="email">{t('login.email')}</Label>
            <Input
              id="email"
              type="email"
              autoComplete="username"
              aria-invalid={!!errors.email}
              {...register('email')}
            />
            {errors.email && <p className="text-sm text-destructive">{t(errors.email.message ?? '')}</p>}
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="password">{t('login.password')}</Label>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              aria-invalid={!!errors.password}
              {...register('password')}
            />
            {errors.password && <p className="text-sm text-destructive">{t(errors.password.message ?? '')}</p>}
          </div>
          {formError && (
            <p role="alert" className="rounded-md bg-destructive-soft px-4 py-3 text-sm text-destructive">
              {t(formError)}
            </p>
          )}
          <Button type="submit" disabled={isSubmitting}>
            {t('login.submit')}
          </Button>
        </form>
      </Card>
    </main>
  )
}
