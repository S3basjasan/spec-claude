import { useSyncExternalStore } from 'react'

/** Por debajo de 1024 px el panel de detalle es una capa superpuesta; desde 1024 px va junto al árbol. */
const CONSULTA = '(max-width: 1023px)'

function suscribir(avisar: () => void) {
  const lista = window.matchMedia(CONSULTA)
  lista.addEventListener('change', avisar)

  return () => lista.removeEventListener('change', avisar)
}

export function useEsPantallaEstrecha(): boolean {
  return useSyncExternalStore(
    suscribir,
    () => window.matchMedia(CONSULTA).matches,
    () => false,
  )
}
