import { IconBook, IconExternalLink, IconFileText } from '../../../../shared/ui/icons'
import type { TipoMicro } from '../api'
import { microText } from '../text'
import { formatearMinutos } from '../tiempo'

interface VisorDocumentoProps {
  tipo: Extract<TipoMicro, 'PDF' | 'Articulo'>
  titulo: string
  url: string | null
  duracionMinutos: number
}

/** Acceso a un PDF o Artículo mediante su URL (spec 4.7): marcador de visor y enlace que abre en una pestaña nueva. */
export function VisorDocumento({ tipo, titulo, url, duracionMinutos }: VisorDocumentoProps) {
  const t = microText.player
  const esPdf = tipo === 'PDF'

  return (
    <div className="flex flex-col gap-3">
      <div className="flex min-h-48 w-full flex-col items-center justify-center gap-2 rounded-xl border border-border bg-neutralsoft p-6 text-center text-neutral">
        {esPdf ? <IconFileText size={44} /> : <IconBook size={44} />}
        <p className="text-sm font-semibold">{esPdf ? t.documentPdf : t.documentArticle}</p>
        {!esPdf && <p className="text-[13px]">{t.estimated(formatearMinutos(duracionMinutos))}</p>}
      </div>
      {url ? (
        <a
          href={url}
          target="_blank"
          rel="noopener noreferrer"
          aria-label={t.openContentOf(titulo)}
          className="inline-flex min-h-11 items-center gap-2 self-start rounded-lg border border-transparent bg-accent px-4 text-[15px] font-semibold text-onaccent hover:bg-accent-hover"
        >
          <IconExternalLink size={18} />
          {t.openContent}
        </a>
      ) : (
        <p className="text-sm text-muted">{t.notAvailableDescription}</p>
      )}
    </div>
  )
}
