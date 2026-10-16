import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import type { Categoria } from '../api'
import { categoriasText } from '../text'

const text = categoriasText.table

interface CategoriasTablaProps {
  categorias: Categoria[]
  onEditar: (categoria: Categoria) => void
  onCambiarEstado: (categoria: Categoria) => void
}

/** Nombre y estado ordenados por nombre. Las acciones son del Administrador; nunca hay Eliminar. */
export function CategoriasTabla({ categorias, onEditar, onCambiarEstado }: CategoriasTablaProps) {
  return (
    <div role="region" aria-label={text.label} tabIndex={0} className="relative overflow-x-auto rounded-xl border border-border bg-surface">
      <table className="w-full min-w-120 border-collapse text-left text-[15px]">
        <caption className="sr-only">{text.label}</caption>
        <thead>
          <tr className="border-b border-border text-sm text-muted">
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.name}
            </th>
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.status}
            </th>
            <th scope="col" className="px-4 py-3 font-semibold">
              <span className="sr-only">{text.columns.actions}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {categorias.map((categoria) => {
            const activa = categoria.estado === 'Activa'
            return (
              <tr key={categoria.id} className="border-b border-border last:border-b-0">
                <td className="px-4 py-3 font-semibold">{categoria.nombre}</td>
                <td className="px-4 py-3">
                  <Badge variant={activa ? 'default' : 'neutral'}>{categoriasText.estados[categoria.estado]}</Badge>
                </td>
                <td className="px-4 py-3">
                  <div className="flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" aria-label={text.editOf(categoria.nombre)} onClick={() => onEditar(categoria)}>
                      {text.edit}
                    </Button>
                    <Button
                      variant="secondary"
                      aria-label={activa ? text.deactivateOf(categoria.nombre) : text.reactivateOf(categoria.nombre)}
                      onClick={() => onCambiarEstado(categoria)}
                    >
                      {activa ? text.deactivate : text.reactivate}
                    </Button>
                  </div>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
