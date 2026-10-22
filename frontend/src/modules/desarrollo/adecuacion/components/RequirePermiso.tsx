import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import type { ClavePermiso } from '../api'
import { usePermisosAdecuacion } from '../hooks/useAdecuacion'
import { tienePermiso } from '../permisos'
import { adecuacionText } from '../text'

interface RequirePermisoProps {
  clave: ClavePermiso
  children: ReactNode
  /** Qué mostrar si falta el permiso (por defecto, nada). */
  fallback?: ReactNode
}

/**
 * Muestra su contenido solo si el usuario tiene la clave de permiso. Es una guarda de presentación: la autoridad es el
 * backend, que rechaza la acción aunque se intente por otra vía.
 */
export function RequirePermiso({ clave, children, fallback = null }: RequirePermisoProps) {
  const { data: permisos, isPending } = usePermisosAdecuacion()

  if (isPending) return null

  return tienePermiso(permisos, clave) ? <>{children}</> : <>{fallback}</>
}

/** Pantalla de «sin acceso» para quien no tiene ningún permiso de adecuación. */
export function SinAccesoAdecuacion() {
  const text = adecuacionText.noPermission

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
