import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useCurrentUser } from '../../../auth/login'
import { planText } from '../text'

interface RequireRoleProps {
  /** Vista del Administrador (capacidad `admin`). */
  admin: ReactNode
  /** Vista del Colaborador (rol `user`). */
  colaborador: ReactNode
}

/**
 * Guarda de presentación: elige la vista por el rol de la sesión. Cada persona tiene un solo rol, así que no hay
 * forma de alternar entre vistas. El backend sigue siendo la autoridad.
 */
export function RequireRole({ admin, colaborador }: RequireRoleProps) {
  const { data: usuario, isPending } = useCurrentUser()

  if (isPending) return <div role="status" aria-label={planText.miPlan.loading} />

  if (usuario?.role === 'admin') return <>{admin}</>
  if (usuario?.role === 'user') return <>{colaborador}</>

  const text = planText.noPermission
  return (
    <div role="alert" className="mx-auto flex max-w-md flex-col items-center gap-3 py-12 text-center">
      <h1 className="text-xl font-bold">{text.title}</h1>
      <p className="text-sm text-muted">{text.description}</p>
      <Link to="/" className="font-semibold text-accent underline">
        {text.back}
      </Link>
    </div>
  )
}
