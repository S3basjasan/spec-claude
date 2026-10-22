import { useMutation, useQueryClient } from '@tanstack/react-query'
import { generar, type Adecuacion, type DatosGenerar } from '../api'
import { adecuacionKey } from './claves'

/** Las escrituras nunca se reintentan automáticamente (Constitución II). */
const sinReintentos = { retry: false } as const

/** Guarda la adecuación; al éxito se invalidan las listas que la muestran (historiales, mapa y «Mi adecuación»). */
export function useGenerarAdecuacion() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosGenerar): Promise<Adecuacion> => generar(datos),
    ...sinReintentos,
    onSuccess: (adecuacion) => {
      queryClient.setQueryData([...adecuacionKey, 'detalle', adecuacion.id], adecuacion)
      for (const parte of ['opciones', 'lista', 'mapa', 'comparar', 'mia']) {
        void queryClient.invalidateQueries({ queryKey: [...adecuacionKey, parte] })
      }
    },
  })
}
