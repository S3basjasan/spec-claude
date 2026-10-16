import { SelectField } from '../../../../shared/ui/SelectField'
import type { AvisoCruce } from '../api'
import type { AccionAsistente } from '../asistente'
import { avisosPorAccion } from '../avisosCruce'
import { useOpcionesPlan } from '../hooks/useOpcionesPlan'
import { planText } from '../text'
import { SelectorParticipantes } from './SelectorParticipantes'

const t = planText.asistente.paso3

interface Paso3ParticipantesProps {
  acciones: AccionAsistente[]
  aprobadorId: number | null
  /** Avisos de cruce con otros planes devueltos por el servidor al guardar el borrador. */
  avisosServidor: AvisoCruce[]
  onChangeAcciones: (acciones: AccionAsistente[]) => void
  onChangeAprobador: (aprobadorId: number | null) => void
}

/** Paso 3 del asistente: participantes por acción (con capacidad usada y avisos de cruce) y aprobador del plan. */
export function Paso3Participantes({ acciones, aprobadorId, avisosServidor, onChangeAcciones, onChangeAprobador }: Paso3ParticipantesProps) {
  const opciones = useOpcionesPlan()
  const avisos = avisosPorAccion(acciones, avisosServidor)

  return (
    <div className="flex flex-col gap-6">
      <section aria-labelledby="paso3-titulo" className="flex flex-col gap-4">
        <h2 id="paso3-titulo" className="text-lg font-bold">
          {t.title}
        </h2>
        {acciones.length === 0 ? (
          <p className="rounded-xl border border-dashed border-border bg-surface px-4 py-8 text-center text-muted">{t.vacio}</p>
        ) : (
          acciones.map((accion) => (
            <article key={accion.clave} aria-label={accion.datos.nombre} className="rounded-xl border border-border bg-surface p-4">
              <h3 className="mb-3 text-base font-bold">{accion.datos.nombre}</h3>
              <SelectorParticipantes
                idBase={accion.clave}
                capacidad={accion.datos.capacidad_maxima}
                seleccionados={accion.participantes}
                avisos={avisos[accion.clave]}
                onChange={(participantes) => onChangeAcciones(acciones.map((a) => (a.clave === accion.clave ? { ...a, participantes } : a)))}
              />
            </article>
          ))
        )}
      </section>

      <div className="flex flex-col gap-1.5">
        <SelectField
          id="plan-aprobador"
          label={t.aprobador}
          aria-describedby="plan-aprobador-hint"
          value={aprobadorId === null ? '' : String(aprobadorId)}
          onChange={(e) => onChangeAprobador(e.target.value === '' ? null : Number(e.target.value))}
          options={[{ value: '', label: t.aprobadorPlaceholder }, ...(opciones.data?.aprobadores ?? []).map((a) => ({ value: String(a.id), label: a.nombre }))]}
        />
        <p id="plan-aprobador-hint" className="text-[13px] text-muted">
          {t.aprobadorHint}
        </p>
      </div>
    </div>
  )
}
