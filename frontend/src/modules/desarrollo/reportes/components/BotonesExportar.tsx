import { toApiError } from '../../../../shared/api/httpClient'
import { Button } from '../../../../shared/ui/Button'
import { IconDownload } from '../../../../shared/ui/icons'
import type { FiltrosReportes, FormatoExportacion } from '../api'
import { useExportar } from '../hooks/useExportar'
import { reportesText } from '../text'

const t = reportesText.admin

interface BotonesExportarProps {
  filtros: FiltrosReportes
  /** Con el rango de fechas invertido no se exporta. */
  deshabilitado?: boolean
}

/** «Exportar Excel» y «Exportar PDF»: exportan con los filtros aplicados; el estado se anuncia con `aria-live`. */
export function BotonesExportar({ filtros, deshabilitado = false }: BotonesExportarProps) {
  const exportar = useExportar()
  const formatoActual = exportar.variables?.formato

  const lanzar = (formato: FormatoExportacion) => exportar.mutate({ formato, filtros })
  const mensaje = exportar.isPending
    ? t.exportando
    : exportar.isSuccess
      ? t.exportado(exportar.data)
      : exportar.isError
        ? reportesText.mensajeDeError(toApiError(exportar.error).code, t.exportError)
        : ''

  return (
    <div className="flex flex-col items-end gap-1.5">
      <div role="group" aria-label={reportesText.exportar.grupo} className="flex flex-wrap gap-2">
        <Button variant="secondary" disabled={deshabilitado || exportar.isPending} loading={exportar.isPending && formatoActual === 'xlsx'} onClick={() => lanzar('xlsx')}>
          <IconDownload size={18} />
          {t.exportarExcel}
        </Button>
        <Button disabled={deshabilitado || exportar.isPending} loading={exportar.isPending && formatoActual === 'pdf'} onClick={() => lanzar('pdf')}>
          <IconDownload size={18} />
          {t.exportarPdf}
        </Button>
      </div>
      <p role="status" aria-live="polite" className={`m-0 min-h-4 text-[13px] ${exportar.isError ? 'text-danger' : 'text-muted'}`}>
        {mensaje}
      </p>
    </div>
  )
}
