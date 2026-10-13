import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { IconPencil } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { EstadoPuesto, RequisitoDetalle, TipoRequisito } from '../api'
import { useCambiarEstadoPuesto } from '../hooks/useCambiarEstadoPuesto'
import { usePuesto } from '../hooks/usePuestos'
import { puestosText } from '../text'

interface PuestoDetalleProps {
  puestoId: number
  nombre: string
  panelId: string
  esAdmin: boolean
}

const TIPOS_REQUISITO: TipoRequisito[] = ['Formacion', 'Experiencia', 'Capacitacion']

const seccion = 'rounded-[10px] border border-border bg-surface px-5 py-4'

function agruparRequisitos(requisitos: RequisitoDetalle[]) {
  return TIPOS_REQUISITO.map((tipo) => ({ tipo, items: requisitos.filter((requisito) => requisito.tipo === tipo) })).filter(
    (grupo) => grupo.items.length > 0,
  )
}

/** Información completa de un puesto; se pide al servidor solo al desplegar la fila. */
export function PuestoDetalle({ puestoId, nombre, panelId, esAdmin }: PuestoDetalleProps) {
  const { data, isPending, isError, error, refetch } = usePuesto(puestoId)
  const cambiarEstado = useCambiarEstadoPuesto()
  const [confirmando, setConfirmando] = useState(false)
  const [errorEstado, setErrorEstado] = useState<string | undefined>()
  const text = puestosText.detail

  /** Mensaje para quien administra cuando el servidor rechaza el cambio (p. ej. posiciones en uso). */
  const mensajeDeError = (error: unknown, porDefecto: string): string => {
    const api = toApiError(error)
    if (api.isNetworkError) return puestosText.statusDialog.networkError
    if (api.status === 422) return api.fieldErrors.estado?.[0] ?? api.message ?? porDefecto
    return porDefecto
  }

  const cambiar = (estado: EstadoPuesto, porDefecto: string) => {
    if (cambiarEstado.isPending) return
    setErrorEstado(undefined)
    cambiarEstado.mutate(
      { id: puestoId, estado },
      {
        onSuccess: () => setConfirmando(false),
        onError: (fallo) => setErrorEstado(mensajeDeError(fallo, porDefecto)),
      },
    )
  }

  if (isPending) {
    return (
      <div id={panelId} role="status" aria-label={text.loading} className="flex flex-col gap-3 border-t border-border bg-bg p-6">
        <Skeleton className="h-4 w-2/3" />
        <Skeleton className="h-4 w-1/2" />
        <Skeleton className="h-4 w-3/5" />
      </div>
    )
  }

  if (isError) {
    const noExiste = toApiError(error).status === 404
    return (
      <div id={panelId} role="alert" className="flex flex-wrap items-center gap-3 border-t border-border bg-bg p-6 text-sm text-danger">
        <span>{noExiste ? text.notFound : text.errorTitle}</span>
        {!noExiste && (
          <Button variant="secondary" onClick={() => void refetch()}>
            {text.retry}
          </Button>
        )}
      </div>
    )
  }

  return (
    <div
      id={panelId}
      role="region"
      aria-label={text.regionLabel(nombre)}
      className="flex flex-col gap-5 border-t border-border bg-bg p-4 sm:p-6"
    >
      {data.estado === 'Inactivo' && <Alert tone="neutral">{puestosText.inactiveNotice}</Alert>}

      <section className={seccion}>
        <h3 className="mb-2.5 text-[15px] font-bold">{text.mission}</h3>
        <p className="text-[15px] leading-relaxed">{data.mision}</p>
      </section>

      <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,320px),1fr))] items-start gap-5">
        <section className={seccion}>
          <h3 className="mb-2.5 text-[15px] font-bold">{text.functions}</h3>
          <ol className="flex list-decimal flex-col gap-1.5 pl-5 text-[15px] leading-relaxed">
            {data.funciones.map((funcion) => (
              <li key={funcion.orden}>
                <span>{funcion.descripcion}</span>{' '}
                <Badge variant={funcion.tipo === 'Esencial' ? 'default' : 'neutral'}>{puestosText.functionTypes[funcion.tipo]}</Badge>
              </li>
            ))}
          </ol>
        </section>

        <section className={seccion}>
          <h3 className="mb-2.5 text-[15px] font-bold">{text.requirements}</h3>
          <div className="flex flex-col gap-3">
            {agruparRequisitos(data.requisitos).map((grupo) => (
              <div key={grupo.tipo}>
                <h4 className="mb-1 text-sm font-semibold text-muted">{puestosText.requirementTypes[grupo.tipo]}</h4>
                <ul className="flex list-disc flex-col gap-1 pl-5 text-[15px] leading-relaxed">
                  {grupo.items.map((requisito, indice) => (
                    <li key={indice}>{requisito.descripcion}</li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </section>
      </div>

      <section>
        <h3 className="mb-2.5 text-[15px] font-bold">{text.competencies}</h3>
        <div className="overflow-x-auto rounded-[10px] border border-border bg-surface px-4">
          <table className="w-full min-w-110 border-collapse text-[15px]">
            <thead>
              <tr className="text-left text-[13px] font-semibold text-muted">
                <th scope="col" className="py-2.5 pr-3">{text.competency}</th>
                <th scope="col" className="px-3 py-2.5">{text.type}</th>
                <th scope="col" className="py-2.5 pl-3 text-right">{text.requiredGrade}</th>
              </tr>
            </thead>
            <tbody>
              {data.competencias.map((competencia) => (
                <tr key={competencia.competencia_id} className="border-t border-border">
                  <th scope="row" className="py-3 pr-3 text-left font-semibold">
                    {competencia.nombre}
                    {competencia.estado === 'Inactivo' && (
                      <>
                        {' '}
                        <Badge variant="neutral">{text.inactiveCompetency}</Badge>
                      </>
                    )}
                  </th>
                  <td className="px-3 py-3 text-muted">{puestosText.competencyTypes[competencia.tipo]}</td>
                  <td className="py-3 pl-3 text-right">
                    <span
                      aria-label={text.gradeLabel(competencia.grado_requerido)}
                      className="inline-flex h-7 min-w-8 items-center justify-center rounded-md bg-accent px-2 text-sm font-bold text-onaccent"
                    >
                      {competencia.grado_requerido}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {esAdmin && (
        <>
          {errorEstado && !confirmando && <Alert>{errorEstado}</Alert>}
          <div className="flex flex-wrap justify-end gap-3">
            <Link
              to={`/puestos/${puestoId}/editar`}
              aria-label={text.edit(data.nombre)}
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-5 text-[15px] font-semibold hover:bg-soft"
            >
              <IconPencil size={18} />
              {text.editLabel}
            </Link>
            {data.estado === 'Activo' ? (
              <Button
                variant="secondary"
                aria-label={text.deactivateNamed(data.nombre)}
                onClick={() => {
                  setErrorEstado(undefined)
                  setConfirmando(true)
                }}
              >
                {text.deactivate}
              </Button>
            ) : (
              <Button
                variant="secondary"
                aria-label={text.activateNamed(data.nombre)}
                loading={cambiarEstado.isPending}
                onClick={() => cambiar('Activo', puestosText.statusDialog.activateError)}
              >
                {text.activate}
              </Button>
            )}
          </div>
          <ConfirmDialog
            open={confirmando}
            title={puestosText.statusDialog.title(data.nombre)}
            description={puestosText.statusDialog.description}
            confirmLabel={puestosText.statusDialog.confirm}
            cancelLabel={puestosText.statusDialog.cancel}
            busy={cambiarEstado.isPending}
            error={errorEstado}
            onConfirm={() => cambiar('Inactivo', puestosText.statusDialog.unexpectedError)}
            onCancel={() => {
              setConfirmando(false)
              setErrorEstado(undefined)
            }}
          />
        </>
      )}
    </div>
  )
}
