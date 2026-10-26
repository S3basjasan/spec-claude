import type { SituacionDeFormulario } from '../politica'
import { movimientosText } from '../text'

/**
 * La causa (sin configurar, sin solicitudes nuevas o falla al consultar) la explica `CondicionesAplicables`; aquí solo
 * se dice por qué el formulario no deja presentar.
 */
export function AvisoDeSituacionDePolitica({ situacion }: { situacion: SituacionDeFormulario }) {
  if (situacion === 'vigente' || situacion === 'cargando') return null

  return (
    <p role="status" className="text-sm text-muted">
      {movimientosText.novelty.blockedNotice}
    </p>
  )
}
