import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useLocation, useNavigate } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { TextField } from '../../../../shared/ui/TextField'
import { useLogin } from '../hooks/useLogin'
import { loginText } from '../text'
import { loginSchema, type LoginValues } from '../validation'

type FormProblem = 'credentials' | 'network' | 'unexpected' | null

export function LoginPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const login = useLogin()
  const [problem, setProblem] = useState<FormProblem>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  })

  const from = (location.state as { from?: string } | null)?.from ?? '/'

  const onSubmit = (values: LoginValues) => {
    if (login.isPending) return
    setProblem(null)
    login.mutate(values, {
      onSuccess: () => navigate(from, { replace: true }),
      onError: (error) => {
        const apiError = toApiError(error)
        if (apiError.isNetworkError) return setProblem('network')
        if (apiError.status === 401) return setProblem('credentials')
        if (apiError.status === 422) {
          const fields = Object.entries(apiError.fieldErrors)
          fields.forEach(([field, messages]) => {
            if (field === 'email' || field === 'password') {
              setError(field, { message: messages[0] })
            }
          })
          if (fields.length > 0) return
        }
        setProblem('unexpected')
      },
    })
  }

  const submit = handleSubmit(onSubmit)

  return (
    <div className="flex min-h-screen flex-col bg-bg text-text">
      <main className="flex flex-1 items-center justify-center px-4 py-12">
        <div className="w-full max-w-105">
          <div className="mb-7 flex items-center justify-center gap-3">
            <span className="flex size-10 items-center justify-center rounded-[10px] bg-accent text-lg font-bold text-onaccent" aria-hidden="true">
              H
            </span>
            <span className="text-[22px] font-bold tracking-tight">{loginText.brand}</span>
          </div>

          <div className="rounded-[14px] border border-border bg-surface p-6 shadow-[0_8px_24px_rgba(18,23,29,0.06)] sm:p-8">
            <form onSubmit={submit} noValidate className="flex flex-col gap-5">
              <div>
                <h1 className="text-2xl font-bold tracking-tight">{loginText.title}</h1>
                <p className="mt-1.5 text-[15px] text-muted">{loginText.subtitle}</p>
              </div>

              {problem === 'credentials' && <Alert>{loginText.invalidCredentials}</Alert>}
              {problem === 'unexpected' && <Alert>{loginText.unexpectedError}</Alert>}
              {problem === 'network' && (
                <ErrorState
                  title={loginText.networkErrorTitle}
                  description={loginText.networkErrorDescription}
                  retryLabel={loginText.retry}
                  onRetry={() => void submit()}
                />
              )}

              <TextField
                id="email"
                type="email"
                autoComplete="username"
                placeholder={loginText.emailPlaceholder}
                label={loginText.emailLabel}
                error={errors.email?.message}
                {...register('email')}
              />
              <TextField
                id="password"
                type="password"
                autoComplete="current-password"
                label={loginText.passwordLabel}
                error={errors.password?.message}
                {...register('password')}
              />

              <Button type="submit" loading={login.isPending} className="h-12 text-base">
                {login.isPending ? loginText.submitting : loginText.submit}
              </Button>
            </form>
          </div>

          <p className="mt-5 text-center text-sm text-muted">{loginText.noAccount}</p>
        </div>
      </main>

      <footer className="flex flex-wrap justify-between gap-x-6 gap-y-2 px-6 py-4 text-[13px] text-muted">
        <span>{loginText.footer}</span>
        <span className="flex gap-5">
          <a href="#ayuda" className="hover:underline">{loginText.help}</a>
          <a href="#privacidad" className="hover:underline">{loginText.privacy}</a>
          <a href="#terminos" className="hover:underline">{loginText.terms}</a>
        </span>
      </footer>
    </div>
  )
}
