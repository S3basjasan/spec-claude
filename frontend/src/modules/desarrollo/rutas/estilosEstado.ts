import type { EstadoInscripcion, EstadoRuta, TipoRuta } from './api'

/** Colores semánticos del design system (design/DESIGN.md §2). El color nunca es la única señal: siempre va con el nombre. */
export const estilosEstadoRuta: Record<EstadoRuta, string> = {
  Borrador: 'bg-neutralsoft text-neutral',
  Vigente: 'bg-soft text-accent',
  Archivada: 'bg-warnsoft text-warn',
}

export const estilosEstadoInscripcion: Record<EstadoInscripcion, string> = {
  Activa: 'bg-soft text-accent',
  Finalizada: 'bg-neutralsoft text-neutral',
  Retirada: 'bg-dangersoft text-danger',
}

/** Etiqueta de tipo de ruta (mismos tonos que RutasAdmin.dc.html). */
export const estilosTipoRuta: Record<TipoRuta, string> = {
  Especialista: 'bg-soft text-accent',
  Gerencial: 'bg-infosoft text-info',
  Interareas: 'bg-violetsoft text-violet',
  JovenesProfesionales: 'bg-warnsoft text-warn',
}

/** Clases de la píldora de los tableros (12px, 600). */
export const pildora = 'inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-xs font-semibold'
