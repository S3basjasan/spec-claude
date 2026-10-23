import { useCurrentUser } from '../../../auth/login'

/** Identificador del usuario autenticado (el responsable por defecto de un plan nuevo), o nulo mientras carga. */
export function useUsuarioActual(): number | null {
  return useCurrentUser().data?.id ?? null
}
