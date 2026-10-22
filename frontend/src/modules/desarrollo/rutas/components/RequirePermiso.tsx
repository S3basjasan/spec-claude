import type { ReactNode } from 'react'
import type { ClavePermiso } from '../api'
import { usePermisosRutas } from '../hooks/useRutas'
import { tienePermiso } from '../permisos'

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
  const { data: permisos } = usePermisosRutas()

  return tienePermiso(permisos, clave) ? <>{children}</> : <>{fallback}</>
}
