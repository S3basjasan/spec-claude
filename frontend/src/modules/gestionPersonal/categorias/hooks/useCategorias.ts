import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { toApiError } from '../../../../shared/api/httpClient'
import { listarCategorias, type FiltrosCategorias } from '../api'

export const categoriasKey = ['categorias-documentales'] as const

/** Un 4xx es una respuesta definitiva del servidor (403, 404): no se reintenta; el resto, una vez. */
const sinReintentoEn4xx = (intentos: number, error: unknown) => {
  const { status } = toApiError(error)
  return (status === undefined || status >= 500) && intentos < 1
}

/** Listado paginado; React Query cancela la petición anterior mediante `signal` al cambiar los filtros. */
export function useCategorias(filtros: FiltrosCategorias) {
  return useQuery({
    queryKey: [...categoriasKey, 'lista', filtros],
    queryFn: ({ signal }) => listarCategorias(filtros, signal),
    placeholderData: keepPreviousData,
    retry: sinReintentoEn4xx,
  })
}

/** Categorías activas para el selector de cargas nuevas (el catálogo es de decenas de elementos). */
export function useCategoriasActivas() {
  return useQuery({
    queryKey: [...categoriasKey, 'activas'],
    queryFn: ({ signal }) => listarCategorias({ estado: 'Activa', per_page: 100 }, signal),
    select: (pagina) => pagina.data,
    retry: sinReintentoEn4xx,
  })
}

/** Todas las categorías (activas e inactivas) para filtros de otras funcionalidades; el catálogo es de decenas de elementos. */
export function useCategoriasTodas(habilitado = true) {
  return useQuery({
    queryKey: [...categoriasKey, 'todas'],
    queryFn: ({ signal }) => listarCategorias({ estado: 'Todas', per_page: 100 }, signal),
    select: (pagina) => pagina.data,
    enabled: habilitado,
    retry: sinReintentoEn4xx,
  })
}
