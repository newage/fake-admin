import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQueryClient } from '@tanstack/react-query'
import { ApiError, login } from '@/lib/api-client'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const schema = z.object({
  email: z.string().trim().min(1, 'Вкажіть email').email('Вкажіть коректний email'),
  password: z.string().min(1, 'Вкажіть пароль'),
})

type Values = z.infer<typeof schema>

function errorMessage(e: unknown) {
  if (e instanceof ApiError) {
    const error = (e.body as { error?: string } | null)?.error
    // The only exceptional message: the superadmin's first password (docs/api.md).
    if (error === 'invalid_request') return 'Пароль має містити щонайменше 8 символів'
    if (e.status === 400 || e.status === 401) return 'Невірний email або пароль'
  }
  return 'Не вдалося увійти. Спробуйте пізніше'
}

export function LoginPage({ onSuccess }: { onSuccess: () => void }) {
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
      setFormError(errorMessage(e))
    }
  })

  return (
    <main className="grid min-h-screen place-items-center p-6">
      <Card className="w-full max-w-sm p-8">
        <h1 className="mb-1 text-2xl font-semibold">Вхід</h1>
        <p className="mb-8 text-sm text-muted-foreground">Адмінка даних датчиків</p>
        <form onSubmit={submit} noValidate className="flex flex-col gap-5">
          <div className="flex flex-col gap-2">
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              type="email"
              autoComplete="username"
              aria-invalid={!!errors.email}
              {...register('email')}
            />
            {errors.email && <p className="text-sm text-destructive">{errors.email.message}</p>}
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="password">Пароль</Label>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              aria-invalid={!!errors.password}
              {...register('password')}
            />
            {errors.password && <p className="text-sm text-destructive">{errors.password.message}</p>}
          </div>
          {formError && (
            <p role="alert" className="rounded-md bg-destructive-soft px-4 py-3 text-sm text-destructive">
              {formError}
            </p>
          )}
          <Button type="submit" disabled={isSubmitting}>
            Увійти
          </Button>
        </form>
      </Card>
    </main>
  )
}
