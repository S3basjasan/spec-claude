import type { EstadoResultado, Preparacion, TipoCambio } from './api'

/** Colores semánticos del design system (design/DESIGN.md §2). El color nunca es la única señal: siempre va con el nombre. */
export const estilosPreparacion: Record<Preparacion, string> = {
  Lista: 'bg-soft text-accent',
  EnDesarrollo: 'bg-infosoft text-info',
  NoLista: 'bg-dangersoft text-danger',
  Incompleta: 'bg-neutralsoft text-neutral',
}

export const estilosEstadoResultado: Record<EstadoResultado, string> = {
  ConBrecha: 'bg-dangersoft text-danger',
  Cumple: 'bg-soft text-accent',
  Supera: 'bg-infosoft text-info',
  SinEvaluacion: 'bg-neutralsoft text-neutral',
}

export const estilosTipoCambio: Record<TipoCambio, string> = {
  Promocion: 'bg-soft text-accent',
  PuestoDirecto: 'bg-violetsoft text-violet',
  MismoPuesto: 'bg-neutralsoft text-neutral',
}

/** Clases de la píldora de los tableros (12px, 600). */
export const pildora = 'inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-xs font-semibold'
