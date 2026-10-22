import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import type { Categoria } from '../api'
import { useVistaAncha } from '../hooks/useVistaAncha'
import { categoriasReconocimientoText } from '../text'
import { AdvertenciaCompetencia } from './AdvertenciaCompetencia'
import { FichaDeIcono } from './IconoCategoria'

const text = categoriasReconocimientoText.table

interface CategoriasTablaProps {
  categorias: Categoria[]
  /** El Administrador ve además Editar y Activar / Desactivar; nunca hay Eliminar. */
  esAdmin: boolean
  onVer: (categoria: Categoria) => void
  onEditar: (categoria: Categoria) => void
  onCambiarEstado: (categoria: Categoria) => void
}

function Competencia({ categoria }: { categoria: Categoria }) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <span>{categoria.competencia?.nombre ?? '—'}</span>
      {!categoria.competencia_activa && <AdvertenciaCompetencia />}
    </div>
  )
}

function Acciones({ categoria, esAdmin, onVer, onEditar, onCambiarEstado, enLinea = false }: CategoriasTablaProps & { categoria: Categoria; enLinea?: boolean }) {
  const activa = categoria.estado === 'Activo'

  return (
    <div className={`flex justify-end gap-2 ${enLinea ? 'flex-nowrap whitespace-nowrap' : 'flex-wrap'}`}>
      <Button variant="secondary" className="min-h-10 px-3 text-sm" aria-label={text.viewOf(categoria.nombre)} onClick={() => onVer(categoria)}>
        {text.view}
      </Button>
      {esAdmin && (
        <>
          <Button variant="secondary" className="min-h-10 px-3 text-sm" aria-label={text.editOf(categoria.nombre)} onClick={() => onEditar(categoria)}>
            {text.edit}
          </Button>
          <Button
            variant="secondary"
            className="min-h-10 px-3 text-sm"
            aria-label={activa ? text.deactivateOf(categoria.nombre) : text.activateOf(categoria.nombre)}
            onClick={() => onCambiarEstado(categoria)}
          >
            {activa ? text.deactivate : text.activate}
          </Button>
        </>
      )}
    </div>
  )
}

/** Código, ícono, nombre, competencia, puntuación, estado y acciones; tarjetas por debajo de 1181 px. */
export function CategoriasTabla(props: CategoriasTablaProps) {
  const ancha = useVistaAncha()
  const { categorias } = props

  if (!ancha) {
    return (
      <ul aria-label={text.label} className="m-0 flex list-none flex-col gap-3 p-0">
        {categorias.map((categoria) => (
          <li key={categoria.id} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
            <div className="flex items-start gap-3">
              <FichaDeIcono id={categoria.icono} activo={categoria.estado === 'Activo'} />
              <div className="min-w-0 flex-1">
                <div className="font-semibold [overflow-wrap:anywhere]">{categoria.nombre}</div>
                <div className="text-sm text-muted">{categoria.codigo}</div>
              </div>
              <Badge variant={categoria.estado === 'Activo' ? 'default' : 'neutral'}>{categoriasReconocimientoText.estados[categoria.estado]}</Badge>
            </div>
            <dl className="m-0 grid grid-cols-2 gap-3 text-sm">
              <div className="col-span-2">
                <dt className="text-xs font-semibold text-muted">{text.columns.competencia}</dt>
                <dd className="m-0 mt-0.5">
                  <Competencia categoria={categoria} />
                </dd>
              </div>
              <div>
                <dt className="text-xs font-semibold text-muted">{text.columns.points}</dt>
                <dd className="m-0 mt-0.5">{categoria.puntuacion_base}</dd>
              </div>
            </dl>
            <Acciones {...props} categoria={categoria} />
          </li>
        ))}
      </ul>
    )
  }

  return (
    <div role="region" aria-label={text.label} tabIndex={0} className="relative overflow-x-auto rounded-xl border border-border bg-surface">
      <table className="w-full border-collapse text-left text-[15px]">
        <caption className="sr-only">{text.label}</caption>
        <thead>
          <tr className="border-b border-border text-sm text-muted">
            <th scope="col" className="px-3 py-3.5 font-semibold">
              {text.columns.code}
            </th>
            <th scope="col" className="px-2 py-3.5 font-semibold">
              {text.columns.icon}
            </th>
            <th scope="col" className="px-3 py-3.5 font-semibold">
              {text.columns.name}
            </th>
            <th scope="col" className="px-3 py-3.5 font-semibold">
              {text.columns.competencia}
            </th>
            <th scope="col" className="px-3 py-3.5 text-right font-semibold">
              {text.columns.points}
            </th>
            <th scope="col" className="px-3 py-3.5 font-semibold">
              {text.columns.status}
            </th>
            <th scope="col" className="px-3 py-3.5 font-semibold">
              <span className="sr-only">{text.columns.actions}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {categorias.map((categoria) => (
            <tr key={categoria.id} className="border-b border-border last:border-b-0 hover:bg-soft">
              <td className="whitespace-nowrap px-3 py-3 text-muted">{categoria.codigo}</td>
              <td className="px-2 py-3">
                <FichaDeIcono id={categoria.icono} activo={categoria.estado === 'Activo'} />
              </td>
              <td className="px-3 py-3 font-semibold [overflow-wrap:anywhere]">{categoria.nombre}</td>
              <td className="px-3 py-3">
                <Competencia categoria={categoria} />
              </td>
              <td className="px-3 py-3 text-right tabular-nums">{categoria.puntuacion_base}</td>
              <td className="px-3 py-3">
                <Badge variant={categoria.estado === 'Activo' ? 'default' : 'neutral'}>{categoriasReconocimientoText.estados[categoria.estado]}</Badge>
              </td>
              <td className="px-3 py-3">
                <Acciones {...props} categoria={categoria} enLinea />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
