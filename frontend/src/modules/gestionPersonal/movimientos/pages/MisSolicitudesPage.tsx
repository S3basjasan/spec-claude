import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { EstadoActuacion, MiSolicitud, ParametrosConservados } from '../api'
import { EstadoDeActuacion } from '../components/EstadoDeActuacion'
import { problemaDeActuacion } from '../errores'
import { useRetirarMiNovedad } from '../hooks/useAccionesDeActuacion'
import { useMisSolicitudes } from '../hooks/useActuaciones'
import { useClaveDeOperacion } from '../hooks/useClaveDeOperacion'
import { movimientosText } from '../text'

const text = movimientosText.mine

type Novedad = Extract<MiSolicitud, { categoria: 'Novedad' }>

function requisitos(parametros: ParametrosConservados): string[] {
  const lista: string[] = []
  if (parametros.justificacion_obligatoria) lista.push(text.requirementJustification)
  if (parametros.documento_obligatorio) lista.push(text.requirementDocument)
  if (typeof parametros.anticipacion_dias === 'number') lista.push(text.requirementAdvance(parametros.anticipacion_dias))
  return lista
}

function Fila({ solicitud, onRetirar }: { solicitud: MiSolicitud; onRetirar: (n: Novedad) => void }) {
  if (solicitud.categoria === 'Movimiento') {
    return (
      <tr className="border-t border-border">
        <td className="px-4 py-3 font-semibold">{movimientosText.tipos[solicitud.tipo]}</td>
        <td className="px-4 py-3">{solicitud.fecha_efectiva ?? movimientosText.none}</td>
        <td className="px-4 py-3">
          <EstadoDeActuacion estado={solicitud.estado as EstadoActuacion} />
        </td>
        <td className="px-4 py-3 text-sm text-muted">{text.movementNote}</td>
      </tr>
    )
  }

  const periodo = solicitud.periodo.inicio === solicitud.periodo.fin ? (solicitud.periodo.inicio ?? '') : `${solicitud.periodo.inicio} → ${solicitud.periodo.fin}`
  const lista = requisitos(solicitud.politica.requisitos)

  return (
    <tr className="border-t border-border align-top">
      <td className="px-4 py-3">
        <span className="font-semibold">{movimientosText.tipos[solicitud.tipo]}</span>
        {solicitud.motivo && <span className="block text-[13px] text-muted">{solicitud.motivo}</span>}
      </td>
      <td className="px-4 py-3">{periodo}</td>
      <td className="px-4 py-3">
        <EstadoDeActuacion estado={solicitud.estado} />
        <span className="mt-1 block text-[13px] text-muted">{text.policyKept(solicitud.politica.numero)}</span>
        {lista.length > 0 && <span className="block text-[13px] text-muted">{lista.join(' · ')}</span>}
      </td>
      <td className="px-4 py-3 text-sm">
        {solicitud.resultado ? (
          <>
            <span className="font-semibold">{text.result[solicitud.resultado.sentido] ?? solicitud.resultado.sentido}</span>
            {solicitud.resultado.justificacion && (
              <span className="block text-muted">
                {text.resultReason}: {solicitud.resultado.justificacion}
              </span>
            )}
          </>
        ) : (
          movimientosText.none
        )}
        {solicitud.retirable && (
          <Button variant="secondary" className="mt-2" onClick={() => onRetirar(solicitud)}>
            {text.withdraw}
          </Button>
        )}
      </td>
    </tr>
  )
}

/** `/mis-solicitudes`: el Colaborador consulta lo propio (novedades y movimientos comunicables). */
export function MisSolicitudesPage() {
  const consulta = useMisSolicitudes()
  const retirar = useRetirarMiNovedad()
  const claveDe = useClaveDeOperacion()
  const aviso = (useLocation().state as { aviso?: string } | null)?.aviso
  const [porRetirar, setPorRetirar] = useState<Novedad | null>(null)
  const [error, setError] = useState<string | undefined>()
  const [exito, setExito] = useState<string | undefined>(aviso)

  const confirmarRetiro = () => {
    if (!porRetirar) return
    setError(undefined)
    retirar.mutate(
      { id: porRetirar.id, version: porRetirar.version, clave: claveDe(`retiro-${porRetirar.id}-${porRetirar.version}`) },
      {
        onSuccess: () => {
          setPorRetirar(null)
          setExito(text.withdrawn)
        },
        onError: (e) => setError(problemaDeActuacion(e).mensaje),
      },
    )
  }

  let contenido
  if (consulta.isPending) {
    contenido = (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  } else if (consulta.isError) {
    const sinFicha = problemaDeActuacion(consulta.error).codigo === 'SIN_FICHA_ASOCIADA'
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        {sinFicha ? (
          <EmptyState icon={<IconFileText size={24} />} title={text.noFichaTitle} description={text.noFichaDescription} />
        ) : (
          <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={movimientosText.retry} onRetry={() => void consulta.refetch()} />
        )}
      </div>
    )
  } else if (consulta.data.length === 0) {
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        <EmptyState icon={<IconFileText size={24} />} title={text.emptyTitle} description={text.emptyDescription} />
      </div>
    )
  } else {
    contenido = (
      <div className="overflow-x-auto rounded-xl border border-border bg-surface">
        <table aria-label={text.tableLabel} className="w-full min-w-[640px] border-collapse text-left text-sm">
          <thead className="bg-soft text-[13px] text-muted">
            <tr>
              <th scope="col" className="px-4 py-3 font-semibold">{text.columns.type}</th>
              <th scope="col" className="px-4 py-3 font-semibold">{text.columns.period}</th>
              <th scope="col" className="px-4 py-3 font-semibold">{text.columns.status}</th>
              <th scope="col" className="px-4 py-3 font-semibold">{text.columns.result}</th>
            </tr>
          </thead>
          <tbody>
            {consulta.data.map((solicitud) => (
              <Fila key={`${solicitud.categoria}-${solicitud.id}`} solicitud={solicitud} onRetirar={(n) => { setError(undefined); setPorRetirar(n) }} />
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{text.title}</h1>
          <p className="text-muted">{text.subtitle}</p>
        </div>
        <Link to="/mis-solicitudes/nueva" className="inline-flex min-h-11 items-center rounded-lg bg-accent px-5 text-[15px] font-semibold text-onaccent hover:bg-accent-hover">
          {text.newRequest}
        </Link>
      </header>

      {exito && <Alert tone="neutral">{exito}</Alert>}
      {contenido}

      <ConfirmDialog
        open={porRetirar !== null}
        title={text.withdrawTitle}
        description={text.withdrawDescription}
        confirmLabel={text.withdrawConfirm}
        cancelLabel={movimientosText.cancel}
        onConfirm={confirmarRetiro}
        onCancel={() => setPorRetirar(null)}
        busy={retirar.isPending}
        error={error}
      />
    </div>
  )
}
