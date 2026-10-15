import { useCallback, useState } from 'react'

/**
 * Ramas expandidas del árbol: vive en el cliente y nunca se conserva entre visitas (al entrar solo se ve la raíz). La
 * búsqueda y el filtro de inactivas muestran todo expandido sin tocar este estado, de modo que al limpiarlos vuelve la
 * expansión previa.
 */
export function useExpansion() {
  const [expandidas, setExpandidas] = useState<ReadonlySet<number>>(new Set())

  const alternar = useCallback((id: number) => {
    setExpandidas((previas) => {
      const siguientes = new Set(previas)
      if (siguientes.has(id)) siguientes.delete(id)
      else siguientes.add(id)
      return siguientes
    })
  }, [])

  const expandir = useCallback((ids: number[]) => setExpandidas((previas) => new Set([...previas, ...ids])), [])
  const expandirTodo = useCallback((ids: number[]) => setExpandidas(new Set(ids)), [])
  const contraerTodo = useCallback(() => setExpandidas(new Set()), [])

  return { expandidas, alternar, expandir, expandirTodo, contraerTodo }
}
