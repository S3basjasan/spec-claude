import type { ClavePermiso } from './api'

/** Claves de permiso del PDI. La visibilidad de pestañas y botones se decide con ellas, nunca con el nombre del rol. */
export const PERMISOS = {
  gestionar: 'desarrollo.pdi.gestionar',
  acordar: 'desarrollo.pdi.acordar',
  seguimiento: 'desarrollo.pdi.seguimiento',
  vincular: 'desarrollo.pdi.vincular',
  cerrar: 'desarrollo.pdi.cerrar',
  gantt: 'desarrollo.pdi.gantt',
  ver: 'desarrollo.pdi.ver',
  verPropio: 'desarrollo.pdi.ver_propio',
  aceptarPropio: 'desarrollo.pdi.aceptar_propio',
} as const satisfies Record<string, ClavePermiso>

export function tienePermiso(permisos: readonly ClavePermiso[] | undefined, clave: ClavePermiso): boolean {
  return permisos?.includes(clave) ?? false
}

/** Claves de gestión: quien tiene alguna ve las pantallas del administrador. */
export const CLAVES_DE_GESTION: readonly ClavePermiso[] = [
  PERMISOS.gestionar,
  PERMISOS.acordar,
  PERMISOS.seguimiento,
  PERMISOS.vincular,
  PERMISOS.cerrar,
  PERMISOS.gantt,
  PERMISOS.ver,
]

export function tieneAlgunaDeGestion(permisos: readonly ClavePermiso[] | undefined): boolean {
  return CLAVES_DE_GESTION.some((clave) => tienePermiso(permisos, clave))
}
