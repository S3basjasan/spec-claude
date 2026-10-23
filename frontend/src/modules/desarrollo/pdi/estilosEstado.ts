import type { EstadoAccion, EstadoPdi, ResultadoPdi, TipoPdi } from './api'

/** Colores semánticos del design system (design/DESIGN.md §2). El color nunca es la única señal: siempre va con el nombre. */
export const estilosEstadoPdi: Record<EstadoPdi, string> = {
  Borrador: 'bg-neutralsoft text-neutral',
  Acordado: 'bg-infosoft text-info',
  Cerrado: 'bg-soft text-accent',
}

export const estilosTipoPdi: Record<TipoPdi, string> = {
  Mejora: 'bg-soft text-accent',
  Carrera: 'bg-infosoft text-info',
  Talento: 'bg-violetsoft text-violet',
}

export const estilosEstadoAccion: Record<EstadoAccion, string> = {
  Pendiente: 'bg-neutralsoft text-neutral',
  EnCurso: 'bg-infosoft text-info',
  Cumplida: 'bg-soft text-accent',
  Cancelada: 'bg-dangersoft text-danger',
}

export const estilosResultado: Record<ResultadoPdi, string> = {
  BrechaCerrada: 'bg-soft text-accent',
  Parcial: 'bg-warningsoft text-warning',
  NoCerrada: 'bg-dangersoft text-danger',
}

/** Colores de las franjas del Gantt por estado de la acción. */
export const estilosBarraGantt: Record<EstadoAccion, string> = {
  Pendiente: 'bg-muted/50',
  EnCurso: 'bg-info',
  Cumplida: 'bg-accent',
  Cancelada: 'bg-danger/60',
}

/** Clases de la píldora de los tableros (12px, 600). */
export const pildora = 'inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-xs font-semibold'
