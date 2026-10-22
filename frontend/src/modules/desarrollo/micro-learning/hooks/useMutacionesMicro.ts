import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  actualizarMicro,
  cambiarEstadoMicro,
  completarMicro,
  crearMicro,
  descargarCertificado,
  eliminarMicro,
  guardarAvance,
  iniciarIntento,
  responderPregunta,
  type DatosMicro,
  type Micro,
} from '../api'
import { microKey } from './useMicros'

/** Crea (sin id) o reemplaza (con id) un micro-contenido. Las escrituras nunca se reintentan. */
export function useGuardarMicro(id: number | null) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (datos: DatosMicro): Promise<Micro> => (id === null ? crearMicro(datos) : actualizarMicro(id, datos)),
    retry: false,
    onSuccess: (micro) => {
      queryClient.setQueryData([...microKey, 'detalle', micro.id], micro)
      void queryClient.invalidateQueries({ queryKey: microKey })
    },
  })
}

export function useCambiarEstado() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, estado }: { id: number; estado: 'Publicado' | 'Archivado' }) => cambiarEstadoMicro(id, estado),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: microKey }),
  })
}

export function useEliminarMicro() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => eliminarMicro(id),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: microKey }),
  })
}

/** Guarda la posición del Video; no se reintenta y no invalida nada (se llama con frecuencia). */
export function useAvance(id: number) {
  return useMutation({ mutationFn: (posicion: number | null) => guardarAvance(id, posicion), retry: false })
}

export function useCompletar(id: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => completarMicro(id),
    retry: false,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: microKey }),
  })
}

export function useIniciarIntento(id: number) {
  return useMutation({ mutationFn: (reiniciar: boolean) => iniciarIntento(id, reiniciar), retry: false })
}

export function useResponderPregunta() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ intentoId, preguntaId, opcionId }: { intentoId: number; preguntaId: number; opcionId: number }) =>
      responderPregunta(intentoId, preguntaId, opcionId),
    retry: false,
    onSuccess: (resultado) => {
      // Al terminar el intento cambian el estado, el puntaje y el resumen del colaborador.
      if (resultado.intento.estado === 'Finalizado') void queryClient.invalidateQueries({ queryKey: microKey })
    },
  })
}

export function useDescargarCertificado() {
  return useMutation({
    mutationFn: ({ id, codigo }: { id: number; codigo: string }) => descargarCertificado(id, codigo),
    retry: false,
  })
}
