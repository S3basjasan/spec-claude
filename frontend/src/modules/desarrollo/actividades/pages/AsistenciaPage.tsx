import { useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconUsers } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { ActividadDetalle, AsistenciaRegistrada } from '../api'
import { BadgeAsistenciaPendiente } from '../components/BadgeAsistencia'
import { BadgeEstadoActividad } from '../components/BadgeEstadoActividad'
import { ConfirmarCambioDialog } from '../components/ConfirmarCambioDialog'
import { EtiquetaTipo } from '../components/EtiquetaTipo'
import { HistorialActividad } from '../components/HistorialActividad'
import { ResumenAsistencia } from '../components/ResumenAsistencia'
import { TablaAsistencia } from '../components/TablaAsistencia'
import { useActividad } from '../hooks/useActividades'
import { useRegistrarAsistencia } from '../hooks/useMutacionesActividad'
import {
  borradorDesde,
  cambiarCalificacion,
  cambiarObservaciones,
  cambiosSobreExistentes,
  elegirAsistencia,
  errorDeCalificacion,
  errorDeObservaciones,
  hayCambios,
  marcarTodosPresentes,
  registrosParaGuardar,
  resumenDelBorrador,
  type FilaBorrador,
} from '../registros'
import { actividadesText } from '../text'

const t = actividadesText.registro

/** Registro de asistencia (Administrador): inscritos, asistencia, calificación 1–5 y observaciones internas. */
export function AsistenciaPage() {
  const id = Number(useParams().id)
  const detalle = useActividad(Number.isInteger(id) && id > 0 ? id : null, true)
  // El aviso vive aquí porque el borrador se reconstruye (y su estado se reinicia) al cambiar la versión tras guardar.
  const [aviso, setAviso] = useState('')
  // La mutación también vive aquí: sus callbacks siguen vivos aunque el borrador se reconstruya al guardar.
  const guardar = useRegistrarAsistencia(Number.isInteger(id) && id > 0 ? id : 0)

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <Link to="/actividades" className="w-fit text-sm font-semibold text-accent underline">
        {t.volver}
      </Link>
      {detalle.isPending ? (
        <div role="status" aria-label={actividadesText.loading} className="flex flex-col gap-2">
          {[0, 1, 2, 3].map((fila) => (
            <Skeleton key={fila} className="h-12" />
          ))}
        </div>
      ) : detalle.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={actividadesText.error.title} description={actividadesText.error.description} retryLabel={actividadesText.error.retry} onRetry={() => void detalle.refetch()} />
        </div>
      ) : (
        // Al guardar cambia la versión: el borrador se reconstruye desde lo guardado.
        <Registro key={detalle.data.version} actividad={detalle.data} guardar={guardar} aviso={aviso} onAviso={setAviso} onRecargar={() => void detalle.refetch()} />
      )}
    </div>
  )
}

interface RegistroProps {
  actividad: ActividadDetalle
  guardar: ReturnType<typeof useRegistrarAsistencia>
  aviso: string
  onAviso: (aviso: string) => void
  onRecargar: () => void
}

function Registro({ actividad, guardar, aviso, onAviso, onRecargar }: RegistroProps) {
  const originales = useMemo(() => borradorDesde(actividad.participantes), [actividad.participantes])
  const [filas, setFilas] = useState<FilaBorrador[]>(originales)
  const [confirmando, setConfirmando] = useState(false)
  const [conflicto, setConflicto] = useState(false)
  const [errorGuardar, setErrorGuardar] = useState<string | undefined>()
  const [verHistorial, setVerHistorial] = useState(false)

  const deshabilitada = !actividad.puede_registrar_asistencia
  const modificado = hayCambios(filas, originales)
  const hayErrores = filas.some((f) => errorDeCalificacion(f, { calificacion: t.errorCalificacion }) || errorDeObservaciones(f, t.errorObservaciones))
  const resumen = resumenDelBorrador(filas)
  const cambiosPrevios = cambiosSobreExistentes(filas, originales, actividadesText.asistencia)

  // Aviso del navegador al cerrar o recargar con cambios sin guardar.
  useEffect(() => {
    if (!modificado) return
    const alSalir = (evento: BeforeUnloadEvent) => evento.preventDefault()
    window.addEventListener('beforeunload', alSalir)
    return () => window.removeEventListener('beforeunload', alSalir)
  }, [modificado])

  const actualizar = (id: number, cambio: (fila: FilaBorrador) => FilaBorrador) => {
    onAviso('')
    setFilas((actuales) => actuales.map((f) => (f.colaborador_id === id ? cambio(f) : f)))
  }

  const ejecutarGuardado = () => {
    setErrorGuardar(undefined)
    guardar.mutate(
      { version: actividad.version, registros: registrosParaGuardar(filas, originales) },
      {
        onSuccess: () => {
          setConfirmando(false)
          onAviso(t.guardado)
        },
        onError: (error) => {
          const e = toApiError(error)
          setConfirmando(false)
          if (e.status === 409) setConflicto(true)
          else setErrorGuardar(Object.values(e.fieldErrors)[0]?.[0] ?? e.message ?? t.errorGuardar)
        },
      },
    )
  }

  const alGuardar = () => {
    if (!modificado || hayErrores || guardar.isPending) return
    if (cambiosPrevios.length > 0) setConfirmando(true)
    else ejecutarGuardado()
  }

  return (
    <>
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex flex-col gap-2">
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{t.title(actividad.nombre)}</h1>
          <p className="text-base text-muted">{t.subtitle}</p>
          <div className="flex flex-wrap items-center gap-2 text-sm text-muted">
            <span className="font-semibold text-text">{actividad.codigo}</span>
            <EtiquetaTipo tipo={actividad.tipo} />
            <BadgeEstadoActividad estado={actividad.estado} />
            {actividad.asistencia_pendiente && <BadgeAsistenciaPendiente />}
            <span>{actividadesText.rango(actividad.fecha_inicio, actividad.fecha_fin)}</span>
          </div>
        </div>
        <Button variant="secondary" onClick={() => setVerHistorial(true)}>
          {t.verHistorial}
        </Button>
      </div>

      {deshabilitada && actividad.motivo_bloqueo && <Alert tone="neutral">{actividadesText.bloqueo[actividad.motivo_bloqueo]}</Alert>}
      {conflicto && (
        <Alert>
          <span className="flex flex-wrap items-center gap-3">
            {t.conflicto}
            <Button variant="secondary" onClick={onRecargar}>
              {t.recargar}
            </Button>
          </span>
        </Alert>
      )}
      {errorGuardar && <Alert>{errorGuardar}</Alert>}

      {filas.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconUsers size={24} />} title={t.sinInscritos.title} description={t.sinInscritos.description} />
        </div>
      ) : (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <ResumenAsistencia {...resumen} />
            <Button
              variant="secondary"
              disabled={deshabilitada || !filas.some((f) => f.asistencia === 'Pendiente')}
              onClick={() => {
                setFilas(marcarTodosPresentes(filas))
                onAviso(t.todosMarcados)
              }}
            >
              {t.marcarTodos}
            </Button>
          </div>

          <TablaAsistencia
            filas={filas}
            deshabilitada={deshabilitada}
            onElegir={(id, opcion: AsistenciaRegistrada) => actualizar(id, (f) => elegirAsistencia(f, opcion))}
            onCalificacion={(id, valor) => actualizar(id, (f) => cambiarCalificacion(f, valor))}
            onObservaciones={(id, texto) => actualizar(id, (f) => cambiarObservaciones(f, texto))}
          />

          <p className="text-sm text-muted">{t.nota}</p>
          <p className="text-sm text-muted">{t.notaObservaciones}</p>

          <div className="flex flex-wrap items-center justify-end gap-4">
            <p role="status" aria-live="polite" className="mr-auto text-sm text-muted">
              {aviso || (modificado ? t.cambiosSinGuardar : '')}
            </p>
            <Button loading={guardar.isPending} disabled={deshabilitada || !modificado || hayErrores} onClick={alGuardar}>
              {t.guardar}
            </Button>
          </div>
        </>
      )}

      {confirmando && <ConfirmarCambioDialog cambios={cambiosPrevios} guardando={guardar.isPending} onConfirmar={ejecutarGuardado} onCancelar={() => setConfirmando(false)} />}
      {verHistorial && <HistorialActividad actividadId={actividad.id} onCerrar={() => setVerHistorial(false)} />}
    </>
  )
}
