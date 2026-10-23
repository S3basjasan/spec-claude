import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconUser } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { DIMENSIONES_EN_ORDEN, type Dimension, type ObjetivoElegido } from '../api'
import { useColaboradoresDeAdecuacion, useOpcionesDeColaborador, usePrevisualizacion } from '../hooks/useAdecuacion'
import { useGenerarAdecuacion } from '../hooks/useMutacionesAdecuacion'
import { preparacionDeLaPantalla } from '../preparacion'
import { adecuacionText } from '../text'
import { brechasSinTiempo, experienciaComoNumero, tiemposParaEnviar, validarExperiencia } from '../validation'
import { AvisosDelDiagnostico } from './AvisosDelDiagnostico'
import { ConfirmarAdecuacion } from './ConfirmarAdecuacion'
import { HistorialDeColaborador } from './HistorialDeColaborador'
import { LeyendaDeBarras } from './LeyendaDeBarras'
import { PanelDeDimension } from './PanelDeDimension'
import { ResumenDeAdecuacion } from './ResumenDeAdecuacion'
import { SelectorDeColaboradorYObjetivo, type ObjetivoValor } from './SelectorDeColaboradorYObjetivo'
import { TablaDeBrechas } from './TablaDeBrechas'

const t = adecuacionText

interface Props {
  /** Mensaje de éxito para la página (se muestra arriba, con «Cerrar»). */
  onRegistrada: (mensaje: string) => void
}

function idDeUrl(valor: string | null): number | null {
  const n = Number(valor)

  return valor !== null && Number.isInteger(n) && n > 0 ? n : null
}

/** Pestaña «Diagnóstico»: Selección → Diagnóstico → Confirmación, en una sola pantalla como el tablero. */
export function PestanaDiagnostico({ onRegistrada }: Props) {
  const [params] = useSearchParams()
  // Enlace desde el PDI (R13): ?colaborador=<id>&puesto=<id> preselecciona colaborador y puesto objetivo.
  const [colaboradorId, setColaboradorId] = useState<number | null>(() => idDeUrl(params.get('colaborador')))
  const [objetivo, setObjetivo] = useState<ObjetivoValor>(() => {
    const puesto = idDeUrl(params.get('puesto'))

    return puesto !== null && idDeUrl(params.get('colaborador')) !== null ? `p:${puesto}` : ''
  })
  const [experiencia, setExperiencia] = useState('')
  const [tiempos, setTiempos] = useState<Record<string, string>>({})
  const [observaciones, setObservaciones] = useState('')
  const [erroresTiempos, setErroresTiempos] = useState<Record<string, string>>({})
  const [errorObservaciones, setErrorObservaciones] = useState<string | undefined>()
  const [errorGeneral, setErrorGeneral] = useState<string | undefined>()
  const [conflicto, setConflicto] = useState(false)

  const colaboradores = useColaboradoresDeAdecuacion('')
  const opciones = useOpcionesDeColaborador(colaboradorId)
  const generar = useGenerarAdecuacion()

  const inscripciones = (opciones.data?.inscripciones ?? []).filter((i) => i.nivel_objetivo !== null)
  const objetivoEfectivo: ObjetivoValor = objetivo !== '' ? objetivo : inscripciones.length === 1 ? `i:${inscripciones[0].inscripcion_id}` : ''
  const sinObjetivoPorDefecto = colaboradorId !== null && opciones.isSuccess && inscripciones.length === 0 && objetivo === ''
  const nombreColaborador = colaboradores.data?.colaboradores.find((c) => c.id === colaboradorId)?.nombre ?? null
  const errorExperiencia = validarExperiencia(experiencia)

  const elegido = useMemo<ObjetivoElegido | null>(() => {
    if (colaboradorId === null || objetivoEfectivo === '' || errorExperiencia !== undefined) return null
    if (objetivoEfectivo.startsWith('i:')) return { colaborador_id: colaboradorId, inscripcion_id: Number(objetivoEfectivo.slice(2)) }
    const anios = experienciaComoNumero(experiencia)

    return {
      colaborador_id: colaboradorId,
      puesto_objetivo_id: Number(objetivoEfectivo.slice(2)),
      ...(anios === undefined ? {} : { experiencia_requerida_anios: anios }),
    }
  }, [colaboradorId, objetivoEfectivo, experiencia, errorExperiencia])

  const diagnostico = usePrevisualizacion(elegido)

  const limpiarCaptura = () => {
    setTiempos({})
    setObservaciones('')
    setErroresTiempos({})
    setErrorObservaciones(undefined)
    setErrorGeneral(undefined)
    setConflicto(false)
  }

  const alElegirColaborador = (id: number | null) => {
    setColaboradorId(id)
    setObjetivo('')
    setExperiencia('')
    limpiarCaptura()
  }

  const alElegirObjetivo = (valor: ObjetivoValor) => {
    setObjetivo(valor)
    limpiarCaptura()
  }

  const d = diagnostico.data
  const preparacion = d === undefined ? null : preparacionDeLaPantalla(d.resultados, tiempos, d.umbral_meses)
  const faltanTiempos = d !== undefined && brechasSinTiempo(d.resultados, tiempos).length > 0

  const registrar = () => {
    if (d === undefined || elegido === null) return
    setErroresTiempos({})
    setErrorObservaciones(undefined)
    setErrorGeneral(undefined)
    generar.mutate(
      {
        ...elegido,
        puesto_objetivo_esperado_id: d.puesto_objetivo.id,
        tiempos_cierre: tiemposParaEnviar(d.resultados, tiempos),
        ...(observaciones.trim() === '' ? {} : { observaciones: observaciones.trim() }),
      },
      {
        onSuccess: (adecuacion) => {
          limpiarCaptura()
          onRegistrada(t.confirmacion.registrada(adecuacion.codigo))
        },
        onError: (error) => {
          const e = toApiError(error)
          if (e.status === 409) {
            setConflicto(true)

            return
          }
          const delServidor: Record<string, string> = {}
          for (const [campo, mensajes] of Object.entries(e.fieldErrors)) {
            if (campo.startsWith('tiempos_cierre.') && mensajes[0] !== undefined) delServidor[campo.slice('tiempos_cierre.'.length)] = mensajes[0]
          }
          setErroresTiempos(delServidor)
          setErrorObservaciones(e.fieldErrors['observaciones']?.[0])
          setErrorGeneral(Object.keys(delServidor).length > 0 || e.fieldErrors['observaciones'] !== undefined ? undefined : (e.message ?? t.errores.guardar))
        },
      },
    )
  }

  const porDimension = (dimension: Dimension) => d?.resultados.filter((r) => r.dimension === dimension) ?? []

  return (
    <div className="flex flex-col gap-5">
      <SelectorDeColaboradorYObjetivo
        colaboradores={colaboradores.data?.colaboradores ?? []}
        colaboradorId={colaboradorId}
        onColaborador={alElegirColaborador}
        opciones={opciones.data}
        objetivo={objetivoEfectivo}
        onObjetivo={alElegirObjetivo}
        experiencia={experiencia}
        onExperiencia={(texto) => {
          setExperiencia(texto)
          limpiarCaptura()
        }}
        errorExperiencia={errorExperiencia}
        sinObjetivoPorDefecto={sinObjetivoPorDefecto}
        nombreColaborador={nombreColaborador}
      />

      {colaboradores.isError && (
        <ErrorState title={t.errores.cargar.title} description={t.errores.cargar.description} retryLabel={t.reintentar} onRetry={() => void colaboradores.refetch()} />
      )}

      {elegido === null && !sinObjetivoPorDefecto && !colaboradores.isError && (
        <EmptyState icon={<IconUser />} title={t.seleccion.elegirColaborador} description="" />
      )}

      {elegido !== null && diagnostico.isPending && (
        <div role="status" aria-label={t.loading} className="flex flex-col gap-3">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      )}

      {elegido !== null && diagnostico.isError && <ErrorDelDiagnostico error={diagnostico.error} onReintentar={() => void diagnostico.refetch()} />}

      {d !== undefined && (
        <div className="flex flex-col gap-5">
          {conflicto && (
            <Alert>
              <div className="flex flex-wrap items-center gap-3">
                <span>{t.errores.objetivoCambio}</span>
                <Button
                  variant="secondary"
                  type="button"
                  onClick={() => {
                    setConflicto(false)
                    void opciones.refetch()
                    void diagnostico.refetch()
                  }}
                >
                  {t.errores.recargar}
                </Button>
              </div>
            </Alert>
          )}

          <ResumenDeAdecuacion diagnostico={d} preparacion={preparacion} />
          <LeyendaDeBarras />

          <div className="grid items-start gap-4 [grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr))]">
            {DIMENSIONES_EN_ORDEN.map((dimension) => (
              <PanelDeDimension
                key={dimension}
                dimension={dimension}
                resultados={porDimension(dimension)}
                cumplimientoActual={d.cumplimiento_por_dimension_actual[dimension]}
                cumplimientoObjetivo={d.cumplimiento_por_dimension[dimension]}
                motivoSinFilas={dimension === 'Conocimiento' ? 'desactivada' : dimension === 'Experiencia' ? 'noEvaluada' : null}
              />
            ))}
          </div>

          <AvisosDelDiagnostico avisos={d.avisos} fuentesNoDisponibles={d.fuentes_no_disponibles} />

          <TablaDeBrechas resultados={d.resultados} tiempos={tiempos} erroresDelServidor={erroresTiempos} onTiempo={(clave, texto) => setTiempos((actual) => ({ ...actual, [clave]: texto }))} />

          <ConfirmarAdecuacion
            diagnostico={d}
            preparacion={preparacion}
            faltanTiempos={faltanTiempos}
            observaciones={observaciones}
            onObservaciones={setObservaciones}
            guardando={generar.isPending}
            errorGeneral={errorGeneral}
            errorObservaciones={errorObservaciones}
            onRegistrar={registrar}
          />

          <HistorialDeColaborador colaborador={d.colaborador.nombre} items={opciones.data?.historial ?? []} />
        </div>
      )}
    </div>
  )
}

function ErrorDelDiagnostico({ error, onReintentar }: { error: unknown; onReintentar: () => void }) {
  const e = toApiError(error)

  // Los errores de negocio (422) se explican con su mensaje; los demás se pueden reintentar.
  if (e.status === 422 && e.message !== undefined) return <Alert>{e.message}</Alert>

  return <ErrorState title={t.errores.cargar.title} description={t.errores.cargar.description} retryLabel={t.reintentar} onRetry={onReintentar} />
}
