import type { ClavePermiso } from './api'

/** Claves de permiso de adecuación. La visibilidad de pestañas y botones se decide con ellas, nunca con el nombre del rol. */
export const PERMISOS = {
  generar: 'desarrollo.adecuacion.generar',
  ver: 'desarrollo.adecuacion.ver',
  comparar: 'desarrollo.adecuacion.comparar',
  mapa: 'desarrollo.adecuacion.mapa',
  parametros: 'desarrollo.adecuacion.parametros',
  verPropio: 'desarrollo.adecuacion.ver_propio',
} as const satisfies Record<string, ClavePermiso>

export function tienePermiso(permisos: readonly ClavePermiso[] | undefined, clave: ClavePermiso): boolean {
  return permisos?.includes(clave) ?? false
}

/** Claves de gestión: quien tiene alguna ve la página del administrador. */
export const CLAVES_DE_GESTION: readonly ClavePermiso[] = [
  PERMISOS.generar,
  PERMISOS.ver,
  PERMISOS.comparar,
  PERMISOS.mapa,
  PERMISOS.parametros,
]

export function tieneAlgunaDeGestion(permisos: readonly ClavePermiso[] | undefined): boolean {
  return CLAVES_DE_GESTION.some((clave) => tienePermiso(permisos, clave))
}
