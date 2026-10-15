import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { FiltroEstado } from '../api'

const RETARDO_BUSQUEDA_MS = 300

interface Cambios {
  q?: string
  estado?: FiltroEstado
}

/** Búsqueda y filtro de estado del listado, guardados en la URL para poder compartirlos. */
export function useListadoParams() {
  const [params, setParams] = useSearchParams()

  const q = params.get('q') ?? ''
  const estadoParam = params.get('estado')
  // `Activa` es el valor por defecto y no se escribe en la URL.
  const estado: FiltroEstado = estadoParam === 'Inactiva' || estadoParam === 'Todas' ? estadoParam : 'Activa'

  // Lo que la persona escribe y aún no se aplicó a la URL; null = mostrar lo que dice la URL.
  const [borrador, setBorrador] = useState<string | null>(null)

  const actualizar = useCallback(
    (cambios: Cambios) => {
      setParams(
        (previos) => {
          const siguientes = new URLSearchParams(previos)
          if (cambios.q !== undefined) {
            if (cambios.q) siguientes.set('q', cambios.q)
            else siguientes.delete('q')
          }
          if (cambios.estado !== undefined) {
            if (cambios.estado === 'Activa') siguientes.delete('estado')
            else siguientes.set('estado', cambios.estado)
          }
          return siguientes
        },
        { replace: true },
      )
    },
    [setParams],
  )

  // La búsqueda se escribe sin demora y se aplica a la URL tras una breve pausa.
  useEffect(() => {
    if (borrador === null) return
    const espera = setTimeout(() => {
      actualizar({ q: borrador.trim() })
      setBorrador(null)
    }, RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [borrador, actualizar])

  return {
    q,
    estado,
    /** Texto del campo de búsqueda: lo que se está escribiendo o, si no, lo que dice la URL. */
    busqueda: borrador ?? q,
    escribir: setBorrador,
    cambiarEstado: (siguiente: FiltroEstado) => actualizar({ estado: siguiente }),
    limpiar: () => {
      setBorrador(null)
      actualizar({ q: '', estado: 'Activa' })
    },
    /** "Ver en el árbol": busca el código de la unidad y muestra todos los estados, para que aparezca aunque esté inactiva. */
    mostrarUnidad: (codigo: string) => {
      setBorrador(null)
      actualizar({ q: codigo, estado: 'Todas' })
    },
  }
}
