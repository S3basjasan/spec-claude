import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import type { Documento, MiDocumento } from '../api'
import { documentosText } from '../text'
import { formatearTamano } from '../validation'

const text = documentosText.table

export type FilaDeDocumento = Documento | MiDocumento

const esAdministrador = (fila: FilaDeDocumento): fila is Documento => 'visible_colaborador' in fila

function fecha(iso: string): string {
  const valor = new Date(iso)
  return Number.isNaN(valor.getTime()) ? iso : valor.toLocaleDateString('es-EC', { day: 'numeric', month: 'short', year: 'numeric' })
}

interface DocumentosTablaProps {
  filas: FilaDeDocumento[]
  /** Vista del Administrador: muestra la visibilidad y las acciones de escritura. */
  administrador: boolean
  /** Los archivados no admiten acciones de escritura. */
  archivados: boolean
  /** Id del documento con una apertura o un cambio en curso. */
  ocupadoId: number | null
  onAbrir: (fila: FilaDeDocumento) => void
  onDescargar: (fila: FilaDeDocumento) => void
  onCambiarVisibilidad: (fila: Documento) => void
  onArchivar: (fila: Documento) => void
}

/** Título, categoría, tamaño y fecha; las acciones dependen de quien consulta. Nunca hay Eliminar, Restaurar ni Editar. */
export function DocumentosTabla({ filas, administrador, archivados, ocupadoId, onAbrir, onDescargar, onCambiarVisibilidad, onArchivar }: DocumentosTablaProps) {
  return (
    <div role="region" aria-label={text.label} tabIndex={0} className="relative overflow-x-auto rounded-xl border border-border bg-surface">
      <table className="w-full min-w-160 border-collapse text-left text-[15px]">
        <caption className="sr-only">{text.label}</caption>
        <thead>
          <tr className="border-b border-border text-sm text-muted">
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.title}
            </th>
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.category}
            </th>
            <th scope="col" className="px-4 py-3 font-semibold">
              {text.columns.size}
            </th>
            <th scope="col" className="px-4 py-3 font-semibold">
              {archivados ? text.columns.archivedBy : text.columns.date}
            </th>
            {administrador && !archivados && (
              <th scope="col" className="px-4 py-3 font-semibold">
                {text.columns.visibility}
              </th>
            )}
            <th scope="col" className="px-4 py-3 font-semibold">
              <span className="sr-only">{text.columns.actions}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {filas.map((fila) => {
            const admin = esAdministrador(fila) ? fila : null
            const ocupado = ocupadoId === fila.id
            return (
              <tr key={fila.id} className="border-b border-border last:border-b-0">
                <td className="px-4 py-3 font-semibold">{fila.titulo}</td>
                <td className="px-4 py-3">
                  <span>{fila.categoria?.nombre ?? documentosText.notRegistered}</span>
                  {fila.categoria?.estado === 'Inactiva' && (
                    <span className="ml-2">
                      <Badge variant="neutral">{text.inactiveCategory}</Badge>
                    </span>
                  )}
                </td>
                <td className="px-4 py-3 whitespace-nowrap">{formatearTamano(fila.tamano_bytes)}</td>
                <td className="px-4 py-3 whitespace-nowrap">
                  {archivados && admin?.archivado_en ? text.archivedOn(fecha(admin.archivado_en)) : fecha(fila.created_at)}
                </td>
                {administrador && !archivados && admin && (
                  <td className="px-4 py-3">
                    <Button
                      variant="secondary"
                      aria-pressed={admin.visible_colaborador}
                      aria-label={admin.visible_colaborador ? text.makeHidden(admin.titulo) : text.makeVisible(admin.titulo)}
                      disabled={ocupado}
                      onClick={() => onCambiarVisibilidad(admin)}
                    >
                      {admin.visible_colaborador ? text.visible : text.notVisible}
                    </Button>
                  </td>
                )}
                <td className="px-4 py-3">
                  <div className="flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" aria-label={text.openOf(fila.titulo)} disabled={ocupado} onClick={() => onAbrir(fila)}>
                      {text.open}
                    </Button>
                    <Button variant="secondary" aria-label={text.downloadOf(fila.titulo)} disabled={ocupado} onClick={() => onDescargar(fila)}>
                      {text.download}
                    </Button>
                    {admin && !archivados && (
                      <Button variant="secondary" aria-label={text.archiveOf(admin.titulo)} disabled={ocupado} onClick={() => onArchivar(admin)}>
                        {text.archive}
                      </Button>
                    )}
                  </div>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
