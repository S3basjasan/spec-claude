import { useMutation } from '@tanstack/react-query'
import { toApiError } from '../../../../shared/api/httpClient'
import { descargarBlob, RespuestaNoEsPdfError } from '../../../../shared/api/pdf'
import { exportarFicha } from '../api'
import { exportacionText } from '../text'

export function mensajeDeExportacion(error: unknown): { mensaje: string; permiso: boolean } {
  if (error instanceof RespuestaNoEsPdfError) return { mensaje: exportacionText.errors.notPdf, permiso: false }
  const api = toApiError(error)
  if (api.status === 403) return { mensaje: exportacionText.errors.forbidden, permiso: true }
  if (api.status === 404) return { mensaje: exportacionText.errors.notFound, permiso: false }
  if (api.status === 503) return { mensaje: exportacionText.errors.unavailable, permiso: false }
  return { mensaje: exportacionText.errors.generic, permiso: false }
}

/**
 * Solicita el PDF con el cliente autenticado y lo descarga solo si la respuesta es un PDF. Mientras la petición está en
 * curso `isPending` deshabilita nuevas solicitudes. Nunca se descarga un error con extensión `.pdf`.
 */
export function useExportarFicha(colaboradorId: number | null, codigo: string | null) {
  return useMutation({
    mutationFn: async () => {
      const pdf = await exportarFicha(colaboradorId)
      const nombre = pdf.nombreDeArchivo ?? `Ficha_${(codigo ?? 'ficha').replace(/[^A-Za-z0-9_-]/g, '')}.pdf`
      descargarBlob(pdf.blob, nombre)
      return nombre
    },
    retry: false,
  })
}
