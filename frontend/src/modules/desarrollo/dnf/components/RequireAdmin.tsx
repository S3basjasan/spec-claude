import { Link, Outlet } from 'react-router-dom'
import { useCurrentUser } from '../../../auth/login'
import { dnfText } from '../text'

/** Guarda de presentación: solo el Administrador accede. El backend sigue siendo la autoridad. */
export function RequireAdmin() {
  const { data: usuario } = useCurrentUser()

  if (usuario?.role !== 'admin') {
    const text = dnfText.noPermission
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

  return <Outlet />
}
