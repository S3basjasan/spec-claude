import { useCallback, useRef } from 'react'
import { nuevaClave } from '../validation'

/**
 * Clave de operación (`Idempotency-Key`) por contenido: reenviar lo mismo (doble clic, reintento tras un corte)
 * reutiliza la clave, de modo que si el primer envío llegó el servidor devuelve el resultado original; con otro
 * contenido se genera una nueva.
 */
export function useClaveDeOperacion() {
  const intento = useRef<{ firma: string; clave: string } | null>(null)

  return useCallback((firma: string): string => {
    if (intento.current?.firma !== firma) intento.current = { firma, clave: nuevaClave() }
    return intento.current.clave
  }, [])
}
