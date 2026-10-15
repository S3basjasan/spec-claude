import type { UnidadResumen } from '../api'
import { unidadesText } from '../text'
import { UnidadFila } from './UnidadFila'

interface UnidadesTreeProps {
  /** Filas que se ven (ya filtradas por las ramas contraídas). */
  unidades: UnidadResumen[]
  esAdmin: boolean
  expandidas: ReadonlySet<number>
  expansionForzada: boolean
  /** Advertencia "No sigue la cadena" de cada unidad que la tenga, por id. */
  advertencias: ReadonlyMap<number, string>
  /** Unidad con un cambio de estado en curso. */
  ocupadaId?: number
  onAlternar: (unidad: UnidadResumen) => void
  onEditar: (unidad: UnidadResumen) => void
  onAgregar: (unidad: UnidadResumen) => void
  onDesactivar: (unidad: UnidadResumen) => void
  onReactivar: (unidad: UnidadResumen) => void
}

const encabezado = 'px-5 py-3.5 text-left text-[13px] font-semibold text-muted'

/** Árbol de unidades como tabla: el servidor entrega el orden jerárquico y el nivel de cada fila. */
export function UnidadesTree({ unidades, esAdmin, expandidas, expansionForzada, advertencias, ocupadaId, ...acciones }: UnidadesTreeProps) {
  const columnas = unidadesText.list.columns

  return (
    <div className="relative overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={unidadesText.list.treeLabel} className="w-full min-w-220 border-collapse text-[15px]">
        <thead>
          <tr>
            <th scope="col" className={encabezado}>
              {columnas.name}
            </th>
            <th scope="col" className={encabezado}>
              {columnas.code}
            </th>
            <th scope="col" className={encabezado}>
              {columnas.type}
            </th>
            <th scope="col" className={encabezado}>
              {columnas.status}
            </th>
            {esAdmin && (
              <th scope="col" className={`${encabezado} text-right`}>
                {columnas.actions}
              </th>
            )}
          </tr>
        </thead>
        <tbody>
          {unidades.map((unidad) => (
            <UnidadFila
              key={unidad.id}
              unidad={unidad}
              esAdmin={esAdmin}
              expandida={expandidas.has(unidad.id)}
              expansionForzada={expansionForzada}
              advertencia={advertencias.get(unidad.id)}
              ocupada={ocupadaId === unidad.id}
              onAlternar={acciones.onAlternar}
              onEditar={acciones.onEditar}
              onAgregar={acciones.onAgregar}
              onDesactivar={acciones.onDesactivar}
              onReactivar={acciones.onReactivar}
            />
          ))}
        </tbody>
      </table>
    </div>
  )
}
