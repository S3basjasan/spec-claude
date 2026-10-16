import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { obtenerOpcionesDeIngreso, registrarIngreso, type DatosIngreso } from '../api'
import { colaboradoresKey, miFichaKey } from './useColaboradores'

/** Opciones del formulario de ingreso para una fecha; se vuelve a consultar al cambiar la fecha o la búsqueda. */
export function useOpcionesDeIngreso(colaboradorId: number, fecha: string, q: string, habilitado: boolean) {
  return useQuery({
    queryKey: [...colaboradoresKey, 'ingreso', 'opciones', colaboradorId, { fecha, q }],
    queryFn: ({ signal }) => obtenerOpcionesDeIngreso(colaboradorId, fecha, q, signal),
    enabled: habilitado,
    // El selector es solo informativo: nunca se reutiliza una lectura anterior como si reservara la posición.
    staleTime: 0,
    gcTime: 0,
  })
}

interface NuevoIngreso {
  datos: DatosIngreso
  /** Clave de operación generada al abrir el formulario. */
  clave: string
}

/** Registra el ingreso. Sin reintentos automáticos y sin cambio optimista del estado laboral. */
export function useRegistrarIngreso(colaboradorId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ datos, clave }: NuevoIngreso) => registrarIngreso(colaboradorId, datos, clave),
    retry: false,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: colaboradoresKey })
      void queryClient.invalidateQueries({ queryKey: miFichaKey })
    },
  })
}
