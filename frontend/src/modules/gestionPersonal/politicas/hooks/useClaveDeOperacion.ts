import { useCallback, useRef } from 'react'
import { nuevaClaveDeOperacion } from '../validation'

/**
 * Clave de operación (`Idempotency-Key`) por intento: un reintento con el mismo contenido reutiliza la clave, de modo
 * que si el primer envío llegó al servidor este devuelve el resultado original; con otro contenido se genera una nueva.
 */
export function useClaveDeOperacion() {
  const intento = useRef<{ firma: string; clave: string } | null>(null)

  return useCallback((firma: string): string => {
    if (intento.current?.firma !== firma) intento.current = { firma, clave: nuevaClaveDeOperacion() }
    return intento.current.clave
  }, [])
}
