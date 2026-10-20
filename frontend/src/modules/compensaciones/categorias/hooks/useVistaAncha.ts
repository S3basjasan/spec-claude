import { useSyncExternalStore } from 'react'

/** Ancho desde el cual el listado es una tabla; por debajo se muestra como tarjetas (tablero de diseño). */
const CONSULTA = '(min-width: 1181px)'

function suscribir(avisar: () => void) {
  if (typeof window.matchMedia !== 'function') return () => {}
  const medio = window.matchMedia(CONSULTA)
  medio.addEventListener('change', avisar)
  return () => medio.removeEventListener('change', avisar)
}

function leer() {
  return typeof window.matchMedia === 'function' ? window.matchMedia(CONSULTA).matches : true
}

/** `true` en pantallas anchas (tabla) y `false` en estrechas (tarjetas). Sin `matchMedia` asume ancha. */
export function useVistaAncha(): boolean {
  return useSyncExternalStore(suscribir, leer, () => true)
}
