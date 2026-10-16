import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Stepper } from '../../../../shared/ui/Stepper'
import type { AvisoCruce, Plan } from '../api'
import { costoEstimado, datosDesdePlan, participantesUnicos, planBorrador, type DatosAsistente } from '../asistente'
import { useGuardarPlan } from '../hooks/useGuardarPlan'
import { planText } from '../text'
import { validarPaso1, type ErroresFormulario } from '../validation'
import { Paso1Datos } from './Paso1Datos'
import { Paso2Acciones } from './Paso2Acciones'
import { Paso3Participantes } from './Paso3Participantes'

const t = planText.asistente
const CAMPOS_PASO1 = ['nombre', 'anio', 'descripcion']

interface AsistentePlanProps {
  /** Id del plan si se edita un Borrador existente. */
  planId?: number
  inicial: DatosAsistente
  /** Competencias ya asociadas que hoy no están activas, para poder mostrarlas. */
  nombresExtra: Record<number, string>
  avisosIniciales?: AvisoCruce[]
  /** Se llama al terminar el último paso con el plan guardado. */
  onTerminar: (plan: Plan) => void
}

/**
 * Asistente de 3 pasos (Datos generales, Acciones formativas, Participantes y aprobador). En cualquier paso se puede
 * guardar como Borrador; enviar a revisión es una acción aparte desde la tabla de planes.
 */
export function AsistentePlan({ planId, inicial, nombresExtra, avisosIniciales = [], onTerminar }: AsistentePlanProps) {
  const [datos, setDatos] = useState<DatosAsistente>(inicial)
  const [paso, setPaso] = useState(0)
  const [maximo, setMaximo] = useState(planId === undefined ? 0 : 2)
  const [idPlan, setIdPlan] = useState(planId)
  const [errores, setErrores] = useState<ErroresFormulario>({})
  const [avisos, setAvisos] = useState<AvisoCruce[]>(avisosIniciales)
  const [errorServidor, setErrorServidor] = useState<string[]>([])
  const [guardado, setGuardado] = useState(false)
  const guardarPlan = useGuardarPlan()

  const cambiarDatos = (cambios: Partial<DatosAsistente>) => {
    setDatos((d) => ({ ...d, ...cambios }))
    setGuardado(false)
  }

  const irA = (indice: number) => {
    setPaso(indice)
    setMaximo((m) => Math.max(m, indice))
  }

  const siguiente = () => {
    const erroresPaso1 = validarPaso1(datos)
    setErrores(erroresPaso1)
    if (Object.keys(erroresPaso1).length > 0) {
      setPaso(0)
      return
    }
    irA(paso + 1)
  }

  const guardar = async (terminar: boolean) => {
    const erroresPaso1 = validarPaso1(datos)
    setErrores(erroresPaso1)
    setErrorServidor([])
    if (Object.keys(erroresPaso1).length > 0) {
      setPaso(0)
      return
    }

    try {
      const plan = await guardarPlan.mutateAsync({ id: idPlan, cuerpo: planBorrador(datos) })
      setIdPlan(plan.id)
      setDatos(datosDesdePlan(plan))
      setAvisos(plan.avisos_cruce)
      setGuardado(true)
      if (terminar) onTerminar(plan)
    } catch (error) {
      const api = toApiError(error)
      const delPaso1: ErroresFormulario = {}
      const otros: string[] = []
      for (const [campo, mensajes] of Object.entries(api.fieldErrors)) {
        if (CAMPOS_PASO1.includes(campo)) delPaso1[campo] = mensajes[0]
        else otros.push(...mensajes)
      }
      if (Object.keys(delPaso1).length > 0) {
        setErrores(delPaso1)
        setPaso(0)
      }
      setErrorServidor(otros.length > 0 ? Array.from(new Set(otros)) : Object.keys(delPaso1).length > 0 ? [] : [api.message ?? t.errorGuardar])
    }
  }

  const ocupado = guardarPlan.isPending
  const anio = Number(datos.anio)
  const ultimo = paso === 2

  return (
    <div className="flex flex-col gap-6">
      <Stepper label={t.stepperLabel} pasos={t.pasos} actual={paso} maximo={maximo} onSelect={irA} />

      {errorServidor.length > 0 && (
        <Alert>
          <ul className="list-inside list-disc">
            {errorServidor.map((mensaje) => (
              <li key={mensaje}>{mensaje}</li>
            ))}
          </ul>
        </Alert>
      )}
      {guardado && !errorServidor.length && (
        <div role="status" className="rounded-lg bg-soft px-4 py-2.5 text-[15px] font-semibold text-accent">
          {t.guardado}
        </div>
      )}

      <div className="rounded-xl border border-border bg-surface p-5">
        {paso === 0 && <Paso1Datos valores={datos} errores={errores} onChange={cambiarDatos} />}
        {paso === 1 && <Paso2Acciones anio={Number.isFinite(anio) ? anio : new Date().getFullYear()} acciones={datos.acciones} nombresExtra={nombresExtra} onChange={(acciones) => cambiarDatos({ acciones })} />}
        {paso === 2 && (
          <div className="flex flex-col gap-6">
            <Paso3Participantes
              acciones={datos.acciones}
              aprobadorId={datos.aprobador_id}
              avisosServidor={avisos}
              onChangeAcciones={(acciones) => cambiarDatos({ acciones })}
              onChangeAprobador={(aprobador_id) => cambiarDatos({ aprobador_id })}
            />
            <dl className="grid gap-3 rounded-xl bg-bg p-4 sm:grid-cols-4">
              {[
                [t.resumen.plan, datos.nombre || planText.sinDato],
                [t.resumen.acciones, String(datos.acciones.length)],
                [t.resumen.costo, planText.dinero(costoEstimado(datos))],
                [t.resumen.participantes, String(participantesUnicos(datos))],
              ].map(([etiqueta, valor]) => (
                <div key={etiqueta}>
                  <dt className="text-[13px] text-muted">{etiqueta}</dt>
                  <dd className="text-base font-bold">{valor}</dd>
                </div>
              ))}
            </dl>
            <p className="text-[13px] text-muted">{t.nota}</p>
          </div>
        )}
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>{paso > 0 && <Button variant="secondary" onClick={() => irA(paso - 1)} disabled={ocupado}>{t.atras}</Button>}</div>
        <div className="flex flex-wrap gap-3">
          <Button variant="secondary" loading={ocupado} onClick={() => void guardar(false)}>
            {ocupado ? t.guardando : t.guardarBorrador}
          </Button>
          {ultimo ? (
            <Button loading={ocupado} onClick={() => void guardar(true)}>
              {idPlan === undefined ? t.crear : t.guardar}
            </Button>
          ) : (
            <Button onClick={siguiente} disabled={ocupado}>
              {t.siguiente}
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}
