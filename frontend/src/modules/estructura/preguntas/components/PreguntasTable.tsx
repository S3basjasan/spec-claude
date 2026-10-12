import { Fragment, useState } from 'react'
import { Link } from 'react-router-dom'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import { IconPencil } from '../../../../shared/ui/icons'
import type { RegistroResumen } from '../api'
import { preguntasText } from '../text'
import { PreguntasPanel } from './PreguntasPanel'

interface PreguntasTableProps {
  items: RegistroResumen[]
  esAdmin: boolean
}

const iconButton = 'flex size-11 items-center justify-center rounded-lg border border-transparent text-text hover:bg-soft'

export function PreguntasTable({ items, esAdmin }: PreguntasTableProps) {
  const [expandidas, setExpandidas] = useState<ReadonlySet<number>>(new Set())
  const text = preguntasText.list

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
          {items.map((registro) => {
            const id = registro.competencia_id
            const abierta = expandidas.has(id)
            const panelId = `preguntas-${id}`
            return (
              <Fragment key={id}>
                <tr className="border-t border-border hover:bg-soft">
                  <td className="whitespace-nowrap px-5 py-3.5 align-top text-muted">{registro.codigo}</td>
                  <th scope="row" className="px-5 py-3.5 align-top font-semibold">
                    {registro.nombre}
                  </th>
                  <td className="max-w-md px-5 py-3.5 align-top text-muted">
                    <p className="line-clamp-2">{registro.descripcion}</p>
                  </td>
                  <td className="px-5 py-3.5 align-top">
                    <Badge>{preguntasText.types[registro.tipo]}</Badge>
                  </td>
                  <td className="px-5 py-3.5 align-top">
                    <Badge variant={registro.estado === 'Activo' ? 'default' : 'neutral'}>
                      {preguntasText.statuses[registro.estado]}
                    </Badge>
                  </td>
                  <td className="px-5 py-2 align-top">
                    <div className="flex items-center justify-end gap-1">
                      <Button
                        variant="secondary"
                        aria-expanded={abierta}
                        aria-controls={abierta ? panelId : undefined}
                        onClick={() => alternar(id)}
                        className="min-h-9 px-3 text-sm"
                      >
                        {abierta ? text.hide : text.show}
                        <span className="sr-only"> — {registro.nombre}</span>
                      </Button>
                      {esAdmin && (
                        <Link to={`/preguntas/${id}/editar`} aria-label={text.edit(registro.nombre)} className={iconButton}>
                          <IconPencil size={18} />
                        </Link>
                      )}
                    </div>
                  </td>
                </tr>
                {abierta && (
                  <tr className="bg-bg">
                    <td colSpan={6} className="px-5 py-4">
                      <PreguntasPanel competenciaId={id} nombre={registro.nombre} panelId={panelId} />
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
