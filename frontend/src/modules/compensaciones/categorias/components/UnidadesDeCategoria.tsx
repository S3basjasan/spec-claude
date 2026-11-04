import { Badge } from '../../../../shared/ui/Badge'
import type { Categoria } from '../api'
import { categoriasReconocimientoText } from '../text'

const text = categoriasReconocimientoText.units

/** Texto de las unidades de una categoría: «Todas las unidades», los nombres o el aviso de que no tiene unidades. */
function textoDeUnidades(categoria: Categoria): string {
  const unidades = categoria.unidades ?? []
  if (categoria.aplica_a_todas) return text.all
  if (unidades.length === 0) return text.none
  return unidades.map((unidad) => (unidad.activa ? unidad.nombre : `${unidad.nombre} ${text.inactiveSuffix}`)).join(', ')
}

/** Unidades de la categoría con el aviso del Gestor cuando ninguna está activa; nada si el lector no las recibe. */
export function UnidadesDeCategoria({ categoria }: { categoria: Categoria }) {
  if (categoria.unidades === undefined) return null

  const sinUnidades = categoria.unidades.length === 0
  const aviso = categoria.sin_unidades_activas && !sinUnidades ? text.noActive : null

  return (
    <div className="flex flex-wrap items-center gap-2">
      <span className="[overflow-wrap:anywhere]">{textoDeUnidades(categoria)}</span>
      {aviso && <Badge variant="neutral">{aviso}</Badge>}
    </div>
  )
}
