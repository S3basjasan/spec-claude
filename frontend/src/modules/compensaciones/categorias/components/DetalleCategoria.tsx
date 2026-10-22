import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Categoria } from '../api'
import { useCategoria } from '../hooks/useCategorias'
import { iconoDe } from '../iconos'
import { categoriasReconocimientoText } from '../text'
import { AdvertenciaCompetencia } from './AdvertenciaCompetencia'
import { FichaDeIcono } from './IconoCategoria'

const text = categoriasReconocimientoText.detail

interface DetalleCategoriaProps {
  id: number
  esAdmin: boolean
  onCerrar: () => void
  /** Solo el Administrador: pasa al formulario de edición con la categoría ya cargada. */
  onEditar: (categoria: Categoria) => void
}

function formatearFecha(iso: string | undefined): string {
  if (!iso) return text.noUser
  const fecha = new Date(iso)
  if (Number.isNaN(fecha.getTime())) return text.noUser
  return new Intl.DateTimeFormat('es', { dateStyle: 'medium', timeStyle: 'short' }).format(fecha)
}

function usuario(id: number | null | undefined): string {
  return id === null || id === undefined ? text.noUser : text.unknownUser(id)
}

function Dato({ etiqueta, children }: { etiqueta: string; children: React.ReactNode }) {
  return (
    <div>
      <dt className="text-[13px] font-semibold text-muted">{etiqueta}</dt>
      <dd className="m-0 mt-1 text-[15px]">{children}</dd>
    </div>
  )
}

/** «Ver»: todos los datos de la categoría y sus indicadores en orden; el Administrador ve además la auditoría. */
export function DetalleCategoria({ id, esAdmin, onCerrar, onEditar }: DetalleCategoriaProps) {
  const consulta = useCategoria(id)
  const cerrar = (
    <Button variant="secondary" onClick={onCerrar}>
      {text.close}
    </Button>
  )

  if (consulta.isPending) {
    return (
      <Modal open title={categoriasReconocimientoText.title} closeLabel={text.close} onClose={onCerrar} footer={cerrar}>
        <div role="status" aria-label={text.loading} className="flex flex-col gap-3">
          <Skeleton className="h-6" />
          <Skeleton className="h-16" />
          <Skeleton className="h-10" />
        </div>
      </Modal>
    )
  }

  if (consulta.isError) {
    const noExiste = toApiError(consulta.error).status === 404
    return (
      <Modal open title={noExiste ? text.notFoundTitle : categoriasReconocimientoText.title} closeLabel={text.close} onClose={onCerrar} footer={cerrar}>
        <Alert>{noExiste ? text.notFoundDescription : text.errorDescription}</Alert>
      </Modal>
    )
  }

  const categoria = consulta.data
  const activa = categoria.estado === 'Activo'

  return (
    <Modal
      open
      size="lg"
      title={categoria.nombre}
      closeLabel={text.close}
      onClose={onCerrar}
      footer={
        <>
          {cerrar}
          {esAdmin && <Button onClick={() => onEditar(categoria)}>{text.edit}</Button>}
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center gap-3">
          <FichaDeIcono id={categoria.icono} activo={activa} lado={48} size={24} />
          <span className="text-sm text-muted">{categoria.codigo}</span>
          <Badge variant={activa ? 'default' : 'neutral'}>{categoriasReconocimientoText.estados[categoria.estado]}</Badge>
        </div>

        {!activa && !esAdmin && <p className="text-sm text-muted">{text.historical}</p>}

        <dl className="m-0 flex flex-col gap-4">
          <Dato etiqueta={text.description}>
            <span className="[overflow-wrap:anywhere]">{categoria.descripcion}</span>
          </Dato>
          <Dato etiqueta={text.competencia}>
            <div className="flex flex-wrap items-center gap-2">
              <span>{categoria.competencia?.nombre ?? '—'}</span>
              {!categoria.competencia_activa && <AdvertenciaCompetencia />}
            </div>
            {!categoria.competencia_activa && (
              <p className="mt-1 text-[13px] text-muted">{esAdmin ? text.competenciaAyudaAdmin : text.competenciaAyudaUsuario}</p>
            )}
          </Dato>
          <div className="grid grid-cols-2 gap-4">
            <Dato etiqueta={text.points}>{categoria.puntuacion_base}</Dato>
            <Dato etiqueta={text.icon}>{iconoDe(categoria.icono)?.nombre ?? categoria.icono}</Dato>
          </div>
          <Dato etiqueta={text.indicators}>
            <ul className="m-0 flex list-disc flex-col gap-1 pl-5">
              {(categoria.indicadores ?? []).map((indicador) => (
                <li key={indicador.orden} className="[overflow-wrap:anywhere]">
                  {indicador.texto}
                </li>
              ))}
            </ul>
            <p className="mt-2 text-[13px] text-muted">{text.indicatorsNote}</p>
          </Dato>
        </dl>

        {esAdmin && (
          <section aria-labelledby="detalle-auditoria" className="rounded-lg border border-border p-4">
            <h3 id="detalle-auditoria" className="mb-2 text-sm font-bold">
              {text.audit} <span className="font-medium text-muted">{text.auditReadOnly}</span>
            </h3>
            <dl className="m-0 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
              <Dato etiqueta={text.createdBy}>{usuario(categoria.created_by)}</Dato>
              <Dato etiqueta={text.createdAt}>{formatearFecha(categoria.created_at)}</Dato>
              <Dato etiqueta={text.updatedBy}>{usuario(categoria.updated_by)}</Dato>
              <Dato etiqueta={text.updatedAt}>{formatearFecha(categoria.updated_at)}</Dato>
            </dl>
          </section>
        )}
      </div>
    </Modal>
  )
}
