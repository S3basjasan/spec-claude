import { useMutation } from '@tanstack/react-query'
import { toApiError } from '../../../../shared/api/httpClient'
import { abrirBlobEnPestana, descargarBlob, RespuestaNoEsPdfError } from '../../../../shared/api/pdf'
import { obtenerContenido } from '../api'
import { documentosText } from '../text'

/** Mensaje para el error de apertura o descarga; nunca se muestra contenido ante un fallo. */
export function mensajeDeApertura(error: unknown): string {
  if (error instanceof RespuestaNoEsPdfError) return documentosText.actions.notPdf
  const api = toApiError(error)
  if (api.status === 403) return documentosText.actions.noPermission
  if (api.status === 404) return documentosText.actions.notFound
  if (api.status === 503) return documentosText.actions.storage
  return documentosText.actions.openFailed
}

interface Solicitud {
  id: number
  titulo: string
  descargar: boolean
}

/** Obtiene el PDF autorizado como blob (el Bearer no viaja en un enlace) y lo abre o lo descarga. */
export function useAbrirDocumento(colaboradorId: number | null) {
  return useMutation({
    mutationFn: async ({ id, descargar }: Solicitud) => ({ pdf: await obtenerContenido(colaboradorId, id, descargar), descargar, id }),
    retry: false,
    onSuccess: ({ pdf, descargar, id }) => {
      if (descargar) descargarBlob(pdf.blob, pdf.nombreDeArchivo ?? `Documento_${id}.pdf`)
      else abrirBlobEnPestana(pdf.blob)
    },
  })
}
