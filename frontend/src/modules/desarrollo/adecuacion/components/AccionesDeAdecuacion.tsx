import { Link } from 'react-router-dom'
import { Button } from '../../../../shared/ui/Button'
import { adecuacionText } from '../text'

const t = adecuacionText.confirmacion

interface AccionDePlan {
  habilitado: boolean
  motivo: string
}

interface Props {
  /** La adecuación tiene al menos una brecha. */
  conBrechas: boolean
  /** Motivo ya calculado por el servidor, si el detalle lo trae (acciones del detalle). */
  motivo?: string
  /** Adecuación ya guardada: habilita los botones hacia el asistente del plan de desarrollo (feature 021). */
  plan?: {
    colaboradorId: number
    puestoObjetivoId: number
    crear: AccionDePlan
    incluir: AccionDePlan
  }
}

const claseEnlace = (primario: boolean) =>
  `inline-flex min-h-11 items-center justify-center rounded-lg px-5 text-[15px] font-semibold transition-colors ${primario ? 'bg-accent text-onaccent hover:bg-accent-hover' : 'border border-border bg-surface text-text hover:bg-soft'}`

/**
 * «Crear plan de desarrollo» (Carrera) e «Incluir en Programa de Talentos» (Talento). Con la adecuación guardada y brechas,
 * llevan al asistente del PDI con colaborador, tipo y puesto objetivo preseleccionados; en la vista previa, sin brechas o con
 * un plan abierto del tipo quedan deshabilitados con el motivo en texto (no solo en un `title`).
 */
export function AccionesDeAdecuacion({ conBrechas, motivo, plan }: Props) {
  const texto = plan === undefined ? (conBrechas ? t.motivoSinPdi : t.motivoSinBrechas) : (motivo ?? '')
  const enlace = (tipo: 'Carrera' | 'Talento') =>
    `/pdi/nuevo?colaborador=${plan?.colaboradorId ?? ''}&tipo=${tipo}&puesto_objetivo=${plan?.puestoObjetivoId ?? ''}`

  const crear = plan?.crear
  const incluir = plan?.incluir
  const motivos = [crear, incluir].filter((a): a is AccionDePlan => a !== undefined && !a.habilitado && a.motivo !== '').map((a) => a.motivo)

  return (
    <div className="flex flex-col gap-1.5">
      <div className="flex flex-wrap gap-3">
        {crear?.habilitado ? (
          <Link to={enlace('Carrera')} className={claseEnlace(true)}>
            {t.crearPlan}
          </Link>
        ) : (
          <Button variant="primary" type="button" disabled aria-describedby="acciones-motivo">
            {t.crearPlan}
          </Button>
        )}
        {incluir?.habilitado ? (
          <Link to={enlace('Talento')} className={claseEnlace(false)}>
            {t.incluirPrograma}
          </Link>
        ) : (
          <Button variant="secondary" type="button" disabled aria-describedby="acciones-motivo">
            {t.incluirPrograma}
          </Button>
        )}
      </div>
      {(plan === undefined || motivos.length > 0 || texto !== '') && (
        <p id="acciones-motivo" className="m-0 text-[13px] text-muted">
          {plan === undefined ? texto : (motivos[0] ?? texto)}
        </p>
      )}
    </div>
  )
}
