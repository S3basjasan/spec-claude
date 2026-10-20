import type { Asistencia, EstadoActividad, TipoAccion } from './api'

/** Colores semánticos del design system (design/DESIGN.md §2) para el estado calculado de una actividad. */
export const estilosEstado: Record<EstadoActividad, string> = {
  Programada: 'bg-infosoft text-info',
  EnCurso: 'bg-soft text-accent',
  Finalizada: 'bg-neutralsoft text-neutral',
  Cancelada: 'bg-dangersoft text-danger',
}

/** Asistencia del participante. */
export const estilosAsistencia: Record<Asistencia, string> = {
  Pendiente: 'bg-neutralsoft text-neutral',
  Presente: 'bg-soft text-accent',
  Ausente: 'bg-dangersoft text-danger',
  Justificado: 'bg-warningsoft text-warning',
}

/** Badge adicional «Asistencia pendiente». */
export const estiloAsistenciaPendiente = 'bg-warningsoft text-warning'

/** Etiqueta de tipo por modalidad (mismos tonos que los tableros). */
export const estilosTipo: Record<TipoAccion, string> = {
  Presencial: 'bg-soft text-accent',
  Virtual: 'bg-infosoft text-info',
  ELearning: 'bg-infosoft text-info',
  OJT: 'bg-neutralsoft text-neutral',
  Codesarrollo: 'bg-violetsoft text-violet',
  Mentoring: 'bg-dangersoft text-danger',
}
