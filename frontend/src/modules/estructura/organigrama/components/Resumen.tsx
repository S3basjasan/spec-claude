import { organigramaText } from '../text'

interface ResumenProps {
  vigentes: number
  ocupadas: number
  vacantes: number
  /** Posiciones que coinciden con los filtros, o nulo si no hay filtros. */
  coinciden: number | null
}

/** Resumen en una región que anuncia sus cambios: sin filtros el desglose y con filtros cuántas coinciden. */
export function Resumen({ vigentes, ocupadas, vacantes, coinciden }: ResumenProps) {
  const text = organigramaText.summary

  return (
    <p role="status" aria-live="polite" className="text-sm text-muted">
      {coinciden === null ? text.plain(vigentes, ocupadas, vacantes) : text.filtered(coinciden, vigentes)}
    </p>
  )
}
