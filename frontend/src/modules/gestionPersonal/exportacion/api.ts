import { obtenerPdf, type PdfObtenido } from '../../../shared/api/pdf'

/** `colaboradorId` null = la propia: la ruta no acepta ningún identificador. */
export function exportarFicha(colaboradorId: number | null): Promise<PdfObtenido> {
  return obtenerPdf(colaboradorId === null ? '/api/mi-ficha/exportacion.pdf' : `/api/colaboradores/${colaboradorId}/exportacion.pdf`)
}
