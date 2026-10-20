import type { PosicionDelOrganigrama, UnidadDelFiltro } from './api'
import { organigramaText } from './text'

/** Nodo del árbol de supervisión: una posición y las que le reportan directamente, ordenadas por código. */
export interface NodoDelArbol {
  posicion: PosicionDelOrganigrama
  hijos: NodoDelArbol[]
}

/** Estructura derivada de una sola pasada sobre la respuesta; se calcula una vez por carga. */
export interface ArbolCalculado {
  raiz: NodoDelArbol | null
  /** Posición → su supervisora (nulo en la raíz). */
  padres: Map<number, number | null>
  /** Total de descendientes (directos e indirectos) de cada posición. */
  descendientes: Map<number, number>
  /** Posiciones que tienen subordinadas. */
  conHijos: Set<number>
  porId: Map<number, PosicionDelOrganigrama>
  /** Subordinadas directas de cada posición, por código. */
  hijosDe: Map<number, PosicionDelOrganigrama[]>
}

/** Parte numérica del código (`POS-0302` → 302): ordena `POS-9` antes que `POS-10`. */
export function numeroDeCodigo(codigo: string): number {
  return Number(codigo.replace(/\D+/g, '')) || 0
}

export function porCodigo(a: PosicionDelOrganigrama, b: PosicionDelOrganigrama): number {
  return numeroDeCodigo(a.codigo) - numeroDeCodigo(b.codigo) || a.id - b.id
}

/** Texto sin mayúsculas ni tildes, recortado. */
export function normalizar(texto: string): string {
  return texto
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{Mn}+/gu, '')
}

/**
 * Arma el árbol a partir de `posicion_jefe_id`: la raíz es la posición sin supervisor y las hijas de cada nodo se ordenan
 * por código. Sin límite de profundidad. Las estructuras auxiliares se calculan aquí, una sola vez por carga.
 */
export function construirArbol(posiciones: PosicionDelOrganigrama[]): ArbolCalculado {
  const porId = new Map(posiciones.map((p) => [p.id, p]))
  const hijosDe = new Map<number, PosicionDelOrganigrama[]>()
  const padres = new Map<number, number | null>()
  let raizPosicion: PosicionDelOrganigrama | null = null

  for (const posicion of posiciones) {
    padres.set(posicion.id, posicion.posicion_jefe_id)
    if (posicion.posicion_jefe_id === null) {
      raizPosicion ??= posicion
    } else {
      const lista = hijosDe.get(posicion.posicion_jefe_id) ?? []
      lista.push(posicion)
      hijosDe.set(posicion.posicion_jefe_id, lista)
    }
  }
  for (const lista of hijosDe.values()) lista.sort(porCodigo)

  const descendientes = new Map<number, number>()
  const nodoDe = (posicion: PosicionDelOrganigrama): NodoDelArbol => {
    const hijos = (hijosDe.get(posicion.id) ?? []).map(nodoDe)
    descendientes.set(posicion.id, hijos.reduce((total, hijo) => total + 1 + (descendientes.get(hijo.posicion.id) ?? 0), 0))

    return { posicion, hijos }
  }

  return {
    raiz: raizPosicion === null ? null : nodoDe(raizPosicion),
    padres,
    descendientes,
    conHijos: new Set(hijosDe.keys()),
    porId,
    hijosDe,
  }
}

/** Texto del botón de una rama contraída: «+ 1 posición» / «+ N posiciones». */
export function textoDeRama(cantidad: number): string {
  return organigramaText.tree.expandBranch(cantidad)
}

/** La unidad y todas sus dependientes (se sube por `unidad_padre_id`). */
export function unidadesDependientes(unidades: UnidadDelFiltro[], unidadId: number): Set<number> {
  const resultado = new Set<number>([unidadId])
  let cambio = true
  while (cambio) {
    cambio = false
    for (const unidad of unidades) {
      if (unidad.unidad_padre_id !== null && resultado.has(unidad.unidad_padre_id) && !resultado.has(unidad.id)) {
        resultado.add(unidad.id)
        cambio = true
      }
    }
  }

  return resultado
}

/**
 * Posiciones que coinciden con la búsqueda (código, puesto, unidad u ocupante, sin tildes ni mayúsculas) **y** con el
 * filtro de unidad cuando ambos están activos (cada criterio vacío no restringe). Sin búsqueda ni unidad devuelve nulo:
 * no hay filtros.
 */
export function coincidencias(
  posiciones: PosicionDelOrganigrama[],
  unidades: UnidadDelFiltro[],
  busqueda: string,
  unidadId: number | null,
): Set<number> | null {
  const texto = normalizar(busqueda)
  if (texto === '' && unidadId === null) return null

  const dependientes = unidadId === null ? null : unidadesDependientes(unidades, unidadId)

  return new Set(
    posiciones
      .filter((p) => {
        if (dependientes !== null && !dependientes.has(p.unidad.id)) return false
        if (texto === '') return true
        const ocupante = p.ocupante?.nombre ?? organigramaText.noOccupant

        return [p.codigo, p.puesto.nombre, p.unidad.nombre, ocupante].some((valor) => normalizar(valor).includes(texto))
      })
      .map((p) => p.id),
  )
}

/** Supervisoras de una posición, de la más cercana a la raíz (sin incluirla). */
export function ancestrosDe(padres: Map<number, number | null>, id: number): number[] {
  const resultado: number[] = []
  const visitados = new Set<number>([id])
  for (let actual = padres.get(id) ?? null; actual !== null && !visitados.has(actual); actual = padres.get(actual) ?? null) {
    visitados.add(actual)
    resultado.push(actual)
  }

  return resultado
}

/** Ramas que contienen alguna de las posiciones dadas: sus supervisoras, directas o indirectas. */
export function ramasForzadas(padres: Map<number, number | null>, ids: Iterable<number>): Set<number> {
  const forzadas = new Set<number>()
  for (const id of ids) for (const ancestro of ancestrosDe(padres, id)) forzadas.add(ancestro)

  return forzadas
}

/** Primera coincidencia por código (la que el árbol debe mostrar al buscar). */
export function primeraCoincidencia(porId: Map<number, PosicionDelOrganigrama>, ids: Set<number>): PosicionDelOrganigrama | null {
  let primera: PosicionDelOrganigrama | null = null
  for (const id of ids) {
    const posicion = porId.get(id)
    if (posicion && (primera === null || porCodigo(posicion, primera) < 0)) primera = posicion
  }

  return primera
}
