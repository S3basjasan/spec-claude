import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { IconPencil } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { PosicionDetalle as Detalle, UsuarioReferido } from '../api'
import { usePosicion } from '../hooks/usePosiciones'
import { posicionesText } from '../text'

interface PosicionDetalleProps {
  posicionId: number
  codigo: string
  panelId: string
  esAdmin: boolean
  onEditar: (id: number) => void
  onSuprimir: (detalle: Detalle) => void
}

const seccion = 'rounded-[10px] border border-border bg-surface px-5 py-4'
const text = posicionesText.detail

function fecha(iso: string | null): string {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('es-EC', { day: 'numeric', month: 'short', year: 'numeric' })
}

const nombreDe = (usuario: UsuarioReferido | null) => usuario?.nombre ?? text.unknownUser

/** Información completa de una posición; se pide al servidor solo al desplegar la fila. */
export function PosicionDetalle({ posicionId, codigo, panelId, esAdmin, onEditar, onSuprimir }: PosicionDetalleProps) {
  const { data, isPending, isError, error, refetch } = usePosicion(posicionId)

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

  const suprimida = data.estado === 'Suprimida'
  const motivo =
    data.estado === 'Ocupada'
      ? text.cannotSuppressOccupied
      : data.subordinadas_vigentes > 0
        ? text.cannotSuppressSubordinates(data.subordinadas_vigentes)
        : null
  const motivoId = `${panelId}-motivo`

  return (
    <div
      id={panelId}
      role="region"
      aria-label={text.regionLabel(codigo)}
      className="flex flex-col gap-5 border-t border-border bg-bg p-4 sm:p-6"
    >
      {suprimida && <Alert tone="neutral">{text.suppressedNotice}</Alert>}

      <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,280px),1fr))] items-start gap-5">
        <section className={seccion}>
          <h3 className="mb-2.5 text-[15px] font-bold">{text.occupant}</h3>
          <p className="text-[15px]">{data.ocupante ? nombreDe(data.ocupante) : text.noOccupant}</p>
        </section>

        <section className={seccion}>
          <h3 className="mb-2.5 text-[15px] font-bold">{text.supervisor}</h3>
          {data.supervisor ? (
            <p className="text-[15px] leading-relaxed">
              {data.supervisor.codigo} · {data.supervisor.nombre}
              <br />
              <span className="text-muted">
                {data.supervisor.ocupante ? text.supervisorOccupant(nombreDe(data.supervisor.ocupante)) : text.noOccupant}
              </span>
            </p>
          ) : (
            <p className="text-[15px]">{text.noSupervisor}</p>
          )}
        </section>

        <section className={seccion}>
          <h3 className="mb-2.5 text-[15px] font-bold">{text.supervises}</h3>
          {data.subordinadas.length === 0 ? (
            <p className="text-[15px] text-muted">{text.noSubordinates}</p>
          ) : (
            <ul className="flex list-disc flex-col gap-1 pl-5 text-[15px] leading-relaxed">
              {data.subordinadas.map((subordinada) => (
                <li key={subordinada.id}>
                  {subordinada.codigo} · {subordinada.puesto} ·{' '}
                  <span className={subordinada.ocupante ? undefined : 'italic text-muted'}>
                    {subordinada.ocupante ? nombreDe(subordinada.ocupante) : text.noOccupant}
                  </span>{' '}
                  <span className="text-muted">({posicionesText.statuses[subordinada.estado]})</span>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className={seccion}>
          <h3 className="mb-2.5 text-[15px] font-bold">{text.position}</h3>
          <p className="text-[15px] leading-relaxed">
            {data.puesto.nombre}
            <br />
            <span className="text-muted">{data.puesto.descripcion}</span>
          </p>
        </section>

        <section className={seccion}>
          <h3 className="mb-2.5 text-[15px] font-bold">{text.unit}</h3>
          <p className="text-[15px] leading-relaxed">
            {data.unidad.nombre}
            <br />
            <span className="text-muted">{text.unitStatus(data.unidad.tipo.nombre, data.unidad.estado)}</span>
          </p>
        </section>

        {data.auditoria && (
          <section className={seccion}>
            <h3 className="mb-2.5 text-[15px] font-bold">{text.audit}</h3>
            <p className="text-sm leading-relaxed text-muted">
              {text.createdBy(nombreDe(data.auditoria.created_by), fecha(data.auditoria.created_at))}
              {data.auditoria.updated_at && (
                <>
                  <br />
                  {text.updatedBy(nombreDe(data.auditoria.updated_by), fecha(data.auditoria.updated_at))}
                </>
              )}
            </p>
          </section>
        )}
      </div>

      {esAdmin && !suprimida && (
        <div className="flex flex-wrap items-center justify-end gap-3">
          {motivo && (
            <span id={motivoId} className="text-[13px] text-muted">
              {motivo}
            </span>
          )}
          <Button variant="secondary" aria-label={text.editNamed(data.codigo)} onClick={() => onEditar(data.id)}>
            <IconPencil size={18} />
            {text.edit}
          </Button>
          <Button
            variant="secondary"
            aria-label={text.suppressNamed(data.codigo)}
            aria-describedby={motivo ? motivoId : undefined}
            disabled={motivo !== null}
            onClick={() => onSuprimir(data)}
          >
            {text.suppress}
          </Button>
        </div>
      )}
    </div>
  )
}
