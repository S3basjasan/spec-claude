import { Link } from 'react-router-dom'
import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { ESTADOS_ACCION, TIPOS_VINCULABLES, type Accion, type EstadoAccion } from '../api'
import { rangoDeFechas } from '../fecha'
import { pdiText } from '../text'
import { BadgeDeEstadoDeAccion } from './Etiquetas'

const t = pdiText.detalle

interface FilaDeAccionProps {
  accion: Accion
  /** El administrador puede registrar el avance (plan Acordado, acción sin vínculo). */
  puedeRegistrarAvance: boolean
  /** El administrador puede vincular o desvincular (plan no Cerrado). */
  puedeVincular: boolean
  /** Ocupado con una operación sobre esta acción. */
  busy?: boolean
  onAvance?: (estado: EstadoAccion) => void
  onVincular?: () => void
  onDesvincular?: () => void
}

/** Fila de una acción en el detalle: estado en solo lectura, selector de avance o estado derivado del Plan de Formación. */
export function FilaDeAccion({ accion: a, puedeRegistrarAvance, puedeVincular, busy = false, onAvance, onVincular, onDesvincular }: FilaDeAccionProps) {
  const vinculable = TIPOS_VINCULABLES.includes(a.tipo)
  const editable = puedeRegistrarAvance && a.vinculo === null

  return (
    <li className="flex flex-col gap-2 rounded-lg border border-border p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="m-0 font-semibold">
          {pdiText.tiposAccion[a.tipo]}: {a.descripcion}
        </p>
        {editable ? (
          <SelectField
            id={`accion-${a.id}-estado`}
            label={t.estadoAccion}
            hideLabel
            options={ESTADOS_ACCION.map((e) => ({ value: e, label: pdiText.estadosAccion[e] }))}
            value={a.estado}
            disabled={busy}
            onChange={(e) => onAvance?.(e.target.value as EstadoAccion)}
          />
        ) : (
          <BadgeDeEstadoDeAccion valor={a.estado} />
        )}
      </div>
      <p className="m-0 text-[13px] text-muted">
        {rangoDeFechas(a.fecha_inicio, a.fecha_fin)}
        {a.persona_cargo !== null && ` · ${t.persona}: ${a.persona_cargo}`}
      </p>
      {a.vinculo !== null && (
        <div className="flex flex-wrap items-center gap-2 text-[13px]">
          <span className="rounded-full bg-violetsoft px-2.5 py-[3px] font-semibold text-violet">{pdiText.conVinculo}</span>
          <span>{[a.vinculo.plan_codigo, a.vinculo.accion_nombre].filter(Boolean).join(' · ') || `#${a.vinculo.plan_accion_id}`}</span>
          {a.estado_derivado && <span className="text-muted">({t.derivado})</span>}
          {a.vinculo.requiere_revision && <span className="font-semibold text-warning">{pdiText.requiereRevision}</span>}
          {a.vinculo.actividad_id !== null && (
            <Link to="/actividades" className="font-semibold text-accent underline">
              {t.verActividad}
            </Link>
          )}
        </div>
      )}
      {puedeVincular && (
        <div className="flex flex-wrap gap-2">
          {a.vinculo === null && vinculable && (
            <Button variant="secondary" onClick={onVincular} disabled={busy}>
              {pdiText.asistente.paso2.vincular}
            </Button>
          )}
          {a.vinculo !== null && (
            <Button variant="secondary" onClick={onDesvincular} disabled={busy}>
              {pdiText.asistente.paso2.desvincular}
            </Button>
          )}
        </div>
      )}
    </li>
  )
}
