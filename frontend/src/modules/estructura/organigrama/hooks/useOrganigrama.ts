import { useQuery } from '@tanstack/react-query'
import { obtenerOrganigrama } from '../api'

export const organigramaKey = ['organigrama'] as const

/**
 * Posiciones vigentes y unidades activas, cargadas una vez al entrar. Los cambios que se hagan en otros módulos se ven en
 * la siguiente carga: no se vuelve a pedir al enfocar la ventana.
 */
export function useOrganigrama() {
  return useQuery({
    queryKey: organigramaKey,
    queryFn: obtenerOrganigrama,
    refetchOnMount: 'always',
    refetchOnWindowFocus: false,
    retry: false,
  })
}
