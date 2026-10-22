import { useMutation } from '@tanstack/react-query'
import { descargarBlob } from '../../../../shared/api/pdf'
import { descargarCertificado, exportarReportes, type FiltrosReportes, type FormatoExportacion } from '../api'

/** Exporta con los filtros aplicados y descarga el archivo; el Bearer viaja por el cliente compartido. Sin reintentos. */
export function useExportar() {
  return useMutation({
    mutationFn: async ({ formato, filtros }: { formato: FormatoExportacion; filtros: FiltrosReportes }): Promise<string> => {
      const archivo = await exportarReportes(formato, filtros)
      descargarBlob(archivo.blob, archivo.nombreDeArchivo)
      return archivo.nombreDeArchivo
    },
    retry: false,
  })
}

/** Descarga el certificado PDF de un micro-contenido completado. */
export function useDescargarCertificado() {
  return useMutation({
    mutationFn: async ({ ruta, titulo }: { ruta: string; titulo: string }): Promise<void> => {
      const archivo = await descargarCertificado(ruta)
      descargarBlob(archivo.blob, archivo.nombreDeArchivo === 'certificado.pdf' ? `certificado-${titulo}.pdf` : archivo.nombreDeArchivo)
    },
    retry: false,
  })
}
