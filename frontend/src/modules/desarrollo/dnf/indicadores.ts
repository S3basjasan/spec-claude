import type { Brecha } from './api'
import type { Kpi } from './components/KpiDiagnostico'
import { dnfText } from './text'

const text = dnfText.admin.kpi

/** Indicadores del diagnóstico de un colaborador (competencias evaluadas, con brecha, urgentes y prioridad más alta). */
export function kpisDe(brechas: Brecha[]): Kpi[] {
  const conBrecha = brechas.filter((b) => b.estado === 'ConBrecha')
  const urgentes = conBrecha.filter((b) => b.prioridad === 'Critica' || b.prioridad === 'Alta').length
  const prioridades = conBrecha.map((b) => b.prioridad)
  const masAlta = (['Critica', 'Alta', 'Media', 'Baja'] as const).find((p) => prioridades.includes(p)) ?? null

  return [
    { label: text.evaluadas, valor: String(brechas.length) },
    { label: text.conBrecha, valor: String(conBrecha.length) },
    { label: text.urgentes, valor: String(urgentes), nota: text.urgentesNota(urgentes), urgente: urgentes > 0 },
    { label: text.prioridadMasAlta, valor: masAlta ? dnfText.prioridad[masAlta] : dnfText.sinPrioridad },
  ]
}
