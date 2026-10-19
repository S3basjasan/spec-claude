/** Colores semánticos del design system (design/DESIGN.md §2) de los reportes. */

/** Variación respecto del período anterior: mejora, deterioro o sin cambio. El texto acompaña siempre al color. */
export const estiloVariacion = {
  positiva: 'text-accent',
  negativa: 'text-danger',
  neutra: 'text-muted',
}

export function tonoDeVariacion(diferencia: number | null): keyof typeof estiloVariacion {
  if (diferencia === null || diferencia === 0) return 'neutra'
  return diferencia > 0 ? 'positiva' : 'negativa'
}

/** Badge «Con evaluación» / «Sin evaluación». */
export const estiloEvaluacion = { con: 'bg-soft text-accent', sin: 'bg-neutralsoft text-neutral' }

/** Badge de alerta de reingreso. */
export const estiloAlertaReingreso = 'bg-dangersoft text-danger'

/** Estado calculado de una actividad. */
export const estiloEstado: Record<string, string> = {
  Programada: 'bg-infosoft text-info',
  EnCurso: 'bg-soft text-accent',
  Finalizada: 'bg-neutralsoft text-neutral',
  Cancelada: 'bg-dangersoft text-danger',
}

/** Colores de los siete tipos de formación en los gráficos (variables del design system). */
export const colorDeTipo: Record<string, string> = {
  Presencial: 'var(--accent)',
  Virtual: 'var(--info)',
  ELearning: 'var(--violet)',
  OJT: 'var(--warning)',
  Codesarrollo: 'var(--rose)',
  Mentoring: 'var(--danger)',
  Microlearning: 'var(--neutral)',
}
