import { isAxiosError } from 'axios'
import { http } from './httpClient'

/** La respuesta no es un PDF (por ejemplo, un error recibido con otro tipo de contenido): nunca se descarga como `.pdf`. */
export class RespuestaNoEsPdfError extends Error {
  constructor() {
    super('La respuesta no es un PDF.')
    this.name = 'RespuestaNoEsPdfError'
  }
}

export interface PdfObtenido {
  blob: Blob
  /** Nombre propuesto por el servidor (`Content-Disposition`), si lo envió. */
  nombreDeArchivo: string | null
}

/** Con `responseType: 'blob'` un error JSON llega como Blob: se lee para que `toApiError` pueda interpretarlo. */
async function leerErrorComoJson(error: unknown): Promise<unknown> {
  if (isAxiosError(error) && error.response && error.response.data instanceof Blob) {
    try {
      error.response.data = JSON.parse(await error.response.data.text())
    } catch {
      error.response.data = {}
    }
  }
  return error
}

function nombreDe(contentDisposition: unknown): string | null {
  if (typeof contentDisposition !== 'string') return null
  const coincidencia = /filename="?([^";]+)"?/i.exec(contentDisposition)
  return coincidencia ? coincidencia[1] : null
}

/**
 * GET autenticado de un PDF con el cliente compartido (el Bearer no viaja en un enlace). Comprueba el estado y que
 * `Content-Type` sea `application/pdf` antes de aceptar el archivo.
 */
export async function obtenerPdf(url: string, params?: Record<string, string | number>): Promise<PdfObtenido> {
  try {
    const respuesta = await http.get<Blob>(url, { params, responseType: 'blob', headers: { Accept: 'application/pdf, application/json' } })
    const tipo = String(respuesta.headers['content-type'] ?? '').toLowerCase()
    if (!tipo.startsWith('application/pdf')) throw new RespuestaNoEsPdfError()
    return { blob: respuesta.data, nombreDeArchivo: nombreDe(respuesta.headers['content-disposition']) }
  } catch (error) {
    throw await leerErrorComoJson(error)
  }
}

/** Descarga un blob con un enlace temporal y libera la URL al terminar. */
export function descargarBlob(blob: Blob, nombreDeArchivo: string): void {
  const url = URL.createObjectURL(blob)
  const enlace = document.createElement('a')
  enlace.href = url
  enlace.download = nombreDeArchivo
  document.body.appendChild(enlace)
  enlace.click()
  enlace.remove()
  setTimeout(() => URL.revokeObjectURL(url), 0)
}

/** Abre un blob en una pestaña nueva y libera la URL pasado un tiempo. */
export function abrirBlobEnPestana(blob: Blob): void {
  const url = URL.createObjectURL(blob)
  window.open(url, '_blank', 'noopener')
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}
