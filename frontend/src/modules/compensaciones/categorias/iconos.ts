/**
 * Lista cerrada de íconos de una categoría de reconocimiento: los doce identificadores del tablero de diseño
 * «Categorías de reconocimiento». Es la única fuente en el frontend; el backend valida la misma lista.
 */
export const ICONOS = [
  { id: 'estrella', nombre: 'Estrella', d: 'M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z' },
  { id: 'trofeo', nombre: 'Trofeo', d: 'M8 21h8M12 17v4M7 4h10M17 4v8a5 5 0 0 1 -10 0v-8M5 9m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0M19 9m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0' },
  { id: 'corazon', nombre: 'Corazón', d: 'M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572' },
  { id: 'bombilla', nombre: 'Bombilla', d: 'M3 12h1M12 3v1M20 12h1M5.6 5.6l.7 .7M18.4 5.6l-.7 .7M9 16a5 5 0 1 1 6 0a3.5 3.5 0 0 0 -1 3a2 2 0 0 1 -4 0a3.5 3.5 0 0 0 -1 -3M9.7 17h4.6' },
  { id: 'rayo', nombre: 'Rayo', d: 'M13 3l0 7l6 0l-8 11l0 -7l-6 0l8 -11' },
  { id: 'escudo', nombre: 'Escudo', d: 'M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3' },
  { id: 'equipo', nombre: 'Equipo', d: 'M9 8m-3.5 0a3.5 3.5 0 1 0 7 0a3.5 3.5 0 1 0 -7 0M2.5 20c0-3.5 3-6 6.5-6s6.5 2.5 6.5 6M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2.2.6 3.5 2.6 3.5 5' },
  { id: 'objetivo', nombre: 'Objetivo', d: 'M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0M12 12m-5 0a5 5 0 1 0 10 0a5 5 0 1 0 -10 0M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0' },
  { id: 'mensaje', nombre: 'Mensaje', d: 'M8 9h8M8 13h6M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z' },
  { id: 'libro', nombre: 'Libro', d: 'M3 19a9 9 0 0 1 9 0a9 9 0 0 1 9 0M3 6a9 9 0 0 1 9 0a9 9 0 0 1 9 0M3 6l0 13M12 6l0 13M21 6l0 13' },
  { id: 'bandera', nombre: 'Bandera', d: 'M5 5a5 5 0 0 1 7 0a5 5 0 0 0 7 0v9a5 5 0 0 1 -7 0a5 5 0 0 0 -7 0v-9zM5 21v-7' },
  { id: 'diamante', nombre: 'Diamante', d: 'M6 5h12l3 5l-8.5 9.5a.7 .7 0 0 1 -1 0l-8.5 -9.5l3 -5' },
] as const

export type IconoId = (typeof ICONOS)[number]['id']

export const ICONO_IDS = ICONOS.map((icono) => icono.id) as [IconoId, ...IconoId[]]

export function iconoDe(id: string) {
  return ICONOS.find((icono) => icono.id === id)
}
