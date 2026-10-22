import type { CuerpoActualizarRuta, NivelEnviado, RutaDetalle } from './api'

/** Los niveles de la ruta tal como se envían al guardarla (con su id, para que no pierdan la identidad). */
export function nivelesEnviables(ruta: Pick<RutaDetalle, 'niveles'>): NivelEnviado[] {
  return ruta.niveles.map((n) => ({ id: n.id, orden: n.orden, puesto_id: n.puesto_id, nombre: n.nombre, experiencia_minima_anios: n.experiencia_minima_anios }))
}

/** Reasigna los órdenes consecutivos desde 1 según la posición. */
export function renumerar(niveles: NivelEnviado[]): NivelEnviado[] {
  return niveles.map((n, i) => ({ ...n, orden: i + 1 }))
}

/** Cuerpo completo de la actualización: datos generales de la ruta más los niveles indicados. */
export function cuerpoDeRuta(ruta: RutaDetalle, niveles: NivelEnviado[], cambios: Partial<Pick<CuerpoActualizarRuta, 'nombre' | 'tipo' | 'descripcion' | 'departamento_id'>> = {}): CuerpoActualizarRuta {
  return {
    version: ruta.version,
    nombre: ruta.nombre,
    tipo: ruta.tipo,
    descripcion: ruta.descripcion,
    departamento_id: ruta.departamento_id,
    ...cambios,
    niveles: renumerar(niveles),
  }
}

/** Intercambia el nivel de la posición `indice` con el de `indice + direccion` (1 sube, -1 baja); no hace nada fuera de rango. */
export function moverNivel(niveles: NivelEnviado[], indice: number, direccion: 1 | -1): NivelEnviado[] {
  const destino = indice + direccion
  if (destino < 0 || destino >= niveles.length) return niveles
  const copia = [...niveles]
  ;[copia[indice], copia[destino]] = [copia[destino], copia[indice]]
  return copia
}

/** Agrega un nivel al final (el más alto de la escalera). */
export function agregarNivel(niveles: NivelEnviado[], puestoId: number, nombre: string): NivelEnviado[] {
  return [...niveles, { orden: niveles.length + 1, puesto_id: puestoId, nombre, experiencia_minima_anios: null }]
}

export function quitarNivel(niveles: NivelEnviado[], nivelId: number): NivelEnviado[] {
  return niveles.filter((n) => n.id !== nivelId)
}

/** Cambia el nombre y la experiencia mínima de un nivel; el puesto no se toca. */
export function editarNivel(niveles: NivelEnviado[], nivelId: number, nombre: string, experiencia: number | null): NivelEnviado[] {
  return niveles.map((n) => (n.id === nivelId ? { ...n, nombre, experiencia_minima_anios: experiencia } : n))
}
