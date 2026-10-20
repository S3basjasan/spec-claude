import { useCallback, useMemo, useState } from 'react'
import { ancestrosDe, ramasForzadas, type ArbolCalculado } from '../arbol'

export const ZOOM_MINIMO = 0.5
export const ZOOM_MAXIMO = 1.5
export const PASO_DE_ZOOM = 0.1

/** Redondea a un decimal para evitar acumular errores de coma flotante (0,1 + 0,2). */
const redondear = (valor: number) => Math.round(valor * 10) / 10

/**
 * Estado del árbol que vive en el cliente y se pierde al salir de la pantalla: ramas contraídas por la persona,
 * posición seleccionada y zoom. La búsqueda y el filtro no lo modifican: solo fuerzan abiertas las ramas con coincidencias
 * mientras duran, y limpiarlos devuelve el árbol a como estaba.
 */
export function useEstadoDelArbol(arbol: ArbolCalculado, coincidencias: Set<number> | null) {
  const [contraidas, setContraidas] = useState<Set<number>>(new Set())
  const [seleccionada, setSeleccionada] = useState<number | null>(null)
  /** Ramas abiertas al elegir una posición desde el panel; la persona puede volver a contraerlas. */
  const [abiertasPorSeleccion, setAbiertasPorSeleccion] = useState<Set<number>>(new Set())
  /** Cambia cada vez que se elige una posición desde el panel, para llevar el árbol hasta ella. */
  const [irA, setIrA] = useState<{ id: number; version: number } | null>(null)
  const [zoom, setZoom] = useState(1)

  /** Ramas con coincidencias: siempre abiertas y con el botón deshabilitado mientras hay filtros. */
  const forzadasPorFiltro = useMemo(
    () => (coincidencias === null ? new Set<number>() : ramasForzadas(arbol.padres, coincidencias)),
    [arbol.padres, coincidencias],
  )

  const estaAbierta = useCallback(
    (id: number) => forzadasPorFiltro.has(id) || abiertasPorSeleccion.has(id) || !contraidas.has(id),
    [forzadasPorFiltro, abiertasPorSeleccion, contraidas],
  )

  const esContraible = useCallback((id: number) => !forzadasPorFiltro.has(id), [forzadasPorFiltro])

  const alternar = useCallback(
    (id: number) => {
      if (forzadasPorFiltro.has(id)) return
      if (abiertasPorSeleccion.has(id)) {
        // Se contrae una rama que se había abierto por la selección: la selección y el panel se mantienen.
        setAbiertasPorSeleccion((actual) => {
          const siguiente = new Set(actual)
          siguiente.delete(id)
          return siguiente
        })
        setContraidas((actual) => new Set(actual).add(id))
        return
      }
      setContraidas((actual) => {
        const siguiente = new Set(actual)
        if (siguiente.has(id)) siguiente.delete(id)
        else siguiente.add(id)
        return siguiente
      })
    },
    [forzadasPorFiltro, abiertasPorSeleccion],
  )

  const expandirTodo = useCallback(() => {
    setContraidas(new Set())
    setAbiertasPorSeleccion(new Set())
  }, [])

  /** Sin filtros deja visible solo la máxima autoridad; con filtros contrae solo las ramas sin coincidencias. */
  const contraerTodo = useCallback(() => {
    setAbiertasPorSeleccion(new Set())
    if (coincidencias === null) {
      setContraidas(new Set(arbol.raiz ? [arbol.raiz.posicion.id] : []))
      return
    }
    setContraidas(new Set([...arbol.conHijos].filter((id) => !forzadasPorFiltro.has(id))))
  }, [arbol.raiz, arbol.conHijos, coincidencias, forzadasPorFiltro])

  /** Elegir una tarjeta no cambia las ramas; elegir desde el panel abre las supervisoras y lleva el árbol hasta ella. */
  const seleccionar = useCallback(
    (id: number, desdeElPanel = false) => {
      setSeleccionada(id)
      if (!desdeElPanel) return
      setAbiertasPorSeleccion((actual) => new Set([...actual, ...ancestrosDe(arbol.padres, id).filter((a) => contraidas.has(a))]))
      setIrA((actual) => ({ id, version: (actual?.version ?? 0) + 1 }))
    },
    [arbol.padres, contraidas],
  )

  const cerrarDetalle = useCallback(() => setSeleccionada(null), [])

  const acercar = useCallback(() => setZoom((z) => Math.min(ZOOM_MAXIMO, redondear(z + PASO_DE_ZOOM))), [])
  const alejar = useCallback(() => setZoom((z) => Math.max(ZOOM_MINIMO, redondear(z - PASO_DE_ZOOM))), [])
  const restablecerZoom = useCallback(() => setZoom(1), [])

  return {
    seleccionada,
    zoom,
    irA,
    estaAbierta,
    esContraible,
    alternar,
    expandirTodo,
    contraerTodo,
    seleccionar,
    cerrarDetalle,
    acercar,
    alejar,
    restablecerZoom,
  }
}
