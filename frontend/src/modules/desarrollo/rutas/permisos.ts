import type { ClavePermiso } from './api'

/** Claves de permiso de rutas. La visibilidad de botones y secciones se decide con ellas, nunca con el nombre del rol. */
export const PERMISOS = {
  ver: 'desarrollo.rutas.ver',
  gestionar: 'desarrollo.rutas.gestionar',
  inscribir: 'desarrollo.rutas.inscribir',
  inscripciones: 'desarrollo.rutas.inscripciones',
  historial: 'desarrollo.rutas.historial',
  verPropio: 'desarrollo.rutas.ver_propio',
} as const satisfies Record<string, ClavePermiso>

export function tienePermiso(permisos: readonly ClavePermiso[] | undefined, clave: ClavePermiso): boolean {
  return permisos?.includes(clave) ?? false
}
