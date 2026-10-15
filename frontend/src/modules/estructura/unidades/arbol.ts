import type { UnidadResumen } from './api'

/**
 * Filas que se ven según las ramas expandidas. El servidor entrega las unidades en orden jerárquico con su nivel en el
 * árbol: una fila se oculta si alguna de sus unidades superiores está contraída. Con `todoExpandido` (búsqueda o filtro
 * de estado activo) no se oculta ninguna.
 */
export function filasVisibles(unidades: UnidadResumen[], expandidas: ReadonlySet<number>, todoExpandido: boolean): UnidadResumen[] {
  if (todoExpandido) return unidades

  // abierta[n]: la última unidad vista en el nivel n tiene su rama expandida.
  const abierta: boolean[] = []
  const visibles: UnidadResumen[] = []

  for (const unidad of unidades) {
    const nivel = unidad.nivel_arbol
    let visible = true
    for (let k = 0; k < nivel; k++) {
      if (abierta[k] === false) {
        visible = false
        break
      }
    }
    if (visible) visibles.push(unidad)
    abierta[nivel] = expandidas.has(unidad.id)
    abierta.length = nivel + 1
  }

  return visibles
}

/** Ids de las unidades que tienen dependientes en el listado actual (las que pueden expandirse). */
export function idsExpandibles(unidades: UnidadResumen[]): number[] {
  const conHijas = new Set<number>()
  for (const unidad of unidades) {
    if (unidad.unidad_padre) conHijas.add(unidad.unidad_padre.id)
  }

  return unidades.filter((unidad) => conHijas.has(unidad.id)).map((unidad) => unidad.id)
}

/** La unidad y todas las de las que depende, según el listado actual (para expandirlas y que se vea). */
export function conAncestras(unidades: UnidadResumen[], id: number): number[] {
  const porId = new Map(unidades.map((unidad) => [unidad.id, unidad]))
  const resultado: number[] = []
  for (let actual = porId.get(id); actual && !resultado.includes(actual.id); actual = actual.unidad_padre ? porId.get(actual.unidad_padre.id) : undefined) {
    resultado.push(actual.id)
  }

  return resultado
}
