import type { FilaCatalogo } from './api'
import { catalogoText } from './text'

export interface GrupoDeArea {
  clave: string
  nombre: string
  filas: FilaCatalogo[]
}

/** Agrupa por área formativa (orden alfabético); las competencias sin área van al final en "Sin área formativa". */
export function agruparPorArea(filas: FilaCatalogo[]): GrupoDeArea[] {
  const grupos = new Map<string, GrupoDeArea>()
  for (const fila of filas) {
    const clave = fila.area_formativa ? String(fila.area_formativa.id) : 'sin-area'
    const nombre = fila.area_formativa?.nombre ?? catalogoText.sinArea
    const grupo = grupos.get(clave) ?? { clave, nombre, filas: [] }
    grupo.filas.push(fila)
    grupos.set(clave, grupo)
  }
  return [...grupos.values()].sort((a, b) => {
    if (a.clave === 'sin-area') return 1
    if (b.clave === 'sin-area') return -1
    return a.nombre.localeCompare(b.nombre, 'es')
  })
}
