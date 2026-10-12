import { Fragment, useState } from 'react'
import { Link } from 'react-router-dom'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import { IconPencil } from '../../../../shared/ui/icons'
import type { Competencia } from '../api'
import { competenciasText } from '../text'
import { GradosPanel } from './GradosPanel'

interface CompetenciasTableProps {
  items: Competencia[]
  esAdmin: boolean
  /** Activa (sin confirmación) o pide desactivar la competencia indicada; la página decide el resto. */
  onCambiarEstado: (competencia: Competencia) => void
  /** Id de la competencia cuyo cambio de estado está en curso (bloquea su botón). */
  cambiandoId?: number | null
}

const iconButton =
  'flex size-11 items-center justify-center rounded-lg border border-transparent text-text hover:bg-soft'

export function CompetenciasTable({ items, esAdmin, onCambiarEstado, cambiandoId = null }: CompetenciasTableProps) {
  const [expandidas, setExpandidas] = useState<ReadonlySet<number>>(new Set())
  const text = competenciasText.list

  const alternar = (id: number) =>
    setExpandidas((actuales) => {
      const siguientes = new Set(actuales)
      if (siguientes.has(id)) siguientes.delete(id)
      else siguientes.add(id)
      return siguientes
    })

  return (
    <div className="relative overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={text.tableLabel} className="w-full min-w-200 border-collapse text-left text-[15px]">
        <thead>
          <tr className="text-[13px] font-semibold text-muted">
            <th scope="col" className="px-5 py-3.5">{text.columns.code}</th>
            <th scope="col" className="px-5 py-3.5">{text.columns.name}</th>
            <th scope="col" className="px-5 py-3.5">{text.columns.description}</th>
            <th scope="col" className="px-5 py-3.5">{text.columns.type}</th>
            <th scope="col" className="px-5 py-3.5">{text.columns.status}</th>
            <th scope="col" className="px-5 py-3.5 text-right">
              <span className="sr-only">{text.columns.actions}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {items.map((competencia) => {
            const abierta = expandidas.has(competencia.id)
            const activa = competencia.estado === 'Activo'
            const panelId = `grados-${competencia.id}`
            return (
              <Fragment key={competencia.id}>
                <tr className="border-t border-border hover:bg-soft">
                  <td className="whitespace-nowrap px-5 py-3.5 align-top text-muted">{competencia.codigo}</td>
                  <th scope="row" className="px-5 py-3.5 align-top font-semibold">
                    {competencia.nombre}
                  </th>
                  <td className="max-w-md px-5 py-3.5 align-top text-muted">
                    <p className="line-clamp-2">{competencia.descripcion}</p>
                  </td>
                  <td className="px-5 py-3.5 align-top">
                    <Badge>{competenciasText.types[competencia.tipo]}</Badge>
                  </td>
                  <td className="px-5 py-3.5 align-top">
                    <Badge variant={activa ? 'default' : 'neutral'}>{competenciasText.statuses[competencia.estado]}</Badge>
                  </td>
                  <td className="px-5 py-2 align-top">
                    <div className="flex items-center justify-end gap-1">
                      <Button
                        variant="secondary"
                        aria-expanded={abierta}
                        aria-controls={abierta ? panelId : undefined}
                        onClick={() => alternar(competencia.id)}
                        className="min-h-9 px-3 text-sm"
                      >
                        {abierta ? text.hideGrades : text.showGrades}
                        <span className="sr-only"> — {competencia.nombre}</span>
                      </Button>
                      {esAdmin && (
                        <>
                          <Button
                            variant="secondary"
                            aria-label={activa ? text.deactivateLabel(competencia.nombre) : text.activateLabel(competencia.nombre)}
                            loading={cambiandoId === competencia.id}
                            onClick={() => onCambiarEstado(competencia)}
                            className="min-h-9 px-3 text-sm"
                          >
                            {activa ? text.deactivate : text.activate}
                          </Button>
                          <Link to={`/competencias/${competencia.id}/editar`} aria-label={text.edit(competencia.nombre)} className={iconButton}>
                            <IconPencil size={18} />
                          </Link>
                          
                        </>
                      )}
                    </div>
                  </td>
                </tr>
                {abierta && (
                  <tr className="bg-bg">
                    <td colSpan={6} className="px-5 py-4">
                      <GradosPanel competenciaId={competencia.id} nombre={competencia.nombre} panelId={panelId} />
                    </td>
                  </tr>
                )}
              </Fragment>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
