import { useCallback, useRef, type PointerEvent } from 'react'

/** Elementos sobre los que arrastrar no desplaza: botones, enlaces, campos y selectores. */
const INTERACTIVOS = 'button, a, input, select, textarea, [role="button"]'

/**
 * Desplaza un contenedor arrastrando con el puntero (`scrollLeft` y `scrollTop`). No se inicia si el puntero está sobre
 * un control. Devuelve los manejadores para el contenedor y una referencia a él.
 */
export function useArrastre<T extends HTMLElement>() {
  const ref = useRef<T>(null)
  const inicio = useRef<{ x: number; y: number; izquierda: number; arriba: number } | null>(null)

  const alPulsar = useCallback((evento: PointerEvent<T>) => {
    const contenedor = ref.current
    if (!contenedor || (evento.target instanceof Element && evento.target.closest(INTERACTIVOS))) return
    inicio.current = { x: evento.clientX, y: evento.clientY, izquierda: contenedor.scrollLeft, arriba: contenedor.scrollTop }
  }, [])

  const alMover = useCallback((evento: PointerEvent<T>) => {
    const contenedor = ref.current
    const punto = inicio.current
    if (!contenedor || !punto) return
    contenedor.scrollLeft = punto.izquierda - (evento.clientX - punto.x)
    contenedor.scrollTop = punto.arriba - (evento.clientY - punto.y)
  }, [])

  const alSoltar = useCallback(() => {
    inicio.current = null
  }, [])

  return { ref, manejadores: { onPointerDown: alPulsar, onPointerMove: alMover, onPointerUp: alSoltar, onPointerLeave: alSoltar } }
}
