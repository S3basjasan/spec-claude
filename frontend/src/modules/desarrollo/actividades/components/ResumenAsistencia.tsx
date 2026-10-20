import { actividadesText } from '../text'

const t = actividadesText.registro.resumen

interface ResumenAsistenciaProps {
  presentes: number
  ausentes: number
  justificados: number
  sinRegistrar: number
}

/** Resumen de la asistencia en pantalla (chips); se anuncia al guardar o marcar a los presentes. */
export function ResumenAsistencia({ presentes, ausentes, justificados, sinRegistrar }: ResumenAsistenciaProps) {
  const chips: [string, number, string][] = [
    [t.presentes, presentes, 'bg-soft text-accent'],
    [t.ausentes, ausentes, 'bg-dangersoft text-danger'],
    [t.justificados, justificados, 'bg-warningsoft text-warning'],
    [t.sinRegistrar, sinRegistrar, 'bg-neutralsoft text-neutral'],
  ]

  return (
    <ul aria-label={actividadesText.registro.resumenLabel} className="m-0 flex list-none flex-wrap gap-2 p-0">
      {chips.map(([etiqueta, cantidad, estilo]) => (
        <li key={etiqueta} className={`rounded-full px-3 py-1 text-[13px] font-bold ${estilo}`}>
          {etiqueta}: {cantidad}
        </li>
      ))}
    </ul>
  )
}
