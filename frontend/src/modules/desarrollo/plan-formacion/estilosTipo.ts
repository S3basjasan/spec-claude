import type { TipoAccion } from './api'

/** Colores por tipo de acción (design/DESIGN.md §2): Presencial, Virtual, E-learning y OJT reutilizan tokens existentes. */
export const ESTILO_TIPO: Record<TipoAccion, string> = {
  Presencial: 'bg-soft text-accent',
  Virtual: 'bg-infosoft text-info',
  ELearning: 'bg-warnsoft text-warn',
  OJT: 'bg-neutralsoft text-neutral',
  Codesarrollo: 'bg-violetsoft text-violet',
  Mentoring: 'bg-rosesoft text-rose',
}
