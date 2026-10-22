import { useState } from 'react'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import type { EstadoVersion, TipoPolitica, Version } from '../api'
import { politicasText } from '../text'
import { formatearInstanteLocal } from '../validation'
import { ComparacionDeVersiones } from './ComparacionDeVersiones'
import { ResumenDeParametros } from './ResumenDeParametros'

const text = politicasText.version

const VARIANTES: Record<EstadoVersion, 'default' | 'neutral' | 'warning' | 'danger'> = {
  Borrador: 'neutral',
  Programada: 'warning',
  Vigente: 'default',
  Sustituida: 'neutral',
  Retirada: 'danger',
}

function momento(iso: string | null): string {
  if (iso === null) return politicasText.none
  const valor = new Date(iso)
  return Number.isNaN(valor.getTime()) ? iso : valor.toLocaleString('es-EC', { dateStyle: 'medium', timeStyle: 'short' })
}

interface LineaDeVersionesProps {
  tipo: TipoPolitica
  /** Versiones publicadas, de la más reciente a la más antigua. */
  versiones: Version[]
  /** Hay un borrador activo: no se puede crear otro hasta publicarlo o descartarlo. */
  hayBorrador: boolean
  onCrearDesde: (version: Version) => void
  onRetirar: (version: Version) => void
  /** Id de la versión desde la que se crea un borrador (petición en curso). */
  creandoDesdeId: number | null
}

/** Historial de versiones publicadas con sus estados; una versión publicada nunca muestra controles de edición directa. */
export function LineaDeVersiones({ tipo, versiones, hayBorrador, onCrearDesde, onRetirar, creandoDesdeId }: LineaDeVersionesProps) {
  const [comparando, setComparando] = useState<number | null>(null)

  if (versiones.length === 0) return <p className="text-sm text-muted">{politicasText.detail.noVersions}</p>

  return (
    <ol aria-label={politicasText.detail.versionsLabel} className="flex flex-col gap-4">
      {versiones.map((v) => (
        <li key={v.id} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
          <div className="flex flex-wrap items-center gap-3">
            <h3 className="text-base font-bold">{text.number(v.numero)}</h3>
            <Badge variant={VARIANTES[v.estado]}>{politicasText.estados[v.estado]}</Badge>
          </div>

          <dl className="grid gap-x-6 gap-y-2 text-[15px] sm:grid-cols-2">
            <div>
              <dt className="text-sm font-semibold text-muted">{text.startsOn}</dt>
              <dd>{formatearInstanteLocal(v.inicio_vigencia_local)}</dd>
            </div>
            <div>
              <dt className="text-sm font-semibold text-muted">{text.reason}</dt>
              <dd>{v.motivo_cambio ?? politicasText.none}</dd>
            </div>
            <div>
              <dt className="text-sm font-semibold text-muted">{text.reviewer}</dt>
              <dd>{v.revision?.revisor ?? politicasText.none}</dd>
            </div>
            {v.revision?.observaciones && (
              <div>
                <dt className="text-sm font-semibold text-muted">{text.reviewerNotes}</dt>
                <dd>{v.revision.observaciones}</dd>
              </div>
            )}
            <div>
              <dt className="text-sm font-semibold text-muted">{text.publishedAt}</dt>
              <dd>
                {momento(v.publicada_en)} · {text.publishedBy(v.publicada_por)}
              </dd>
            </div>
            {v.retiro && (
              <div className="sm:col-span-2">
                <dt className="text-sm font-semibold text-muted">{text.retired}</dt>
                <dd>
                  {momento(v.retiro.en)} · {text.retiredBy(v.retiro.por)} · {text.retiredReason}: {v.retiro.motivo}
                </dd>
              </div>
            )}
          </dl>

          {v.descripcion && <p className="text-[15px]">{v.descripcion}</p>}
          <ResumenDeParametros parametros={v.parametros} label={`${text.number(v.numero)}: ${politicasText.editor.parametersTitle}`} />

          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" aria-expanded={comparando === v.id} onClick={() => setComparando(comparando === v.id ? null : v.id)}>
              {comparando === v.id ? text.hideCompare : text.compare}
            </Button>
            <Button variant="secondary" disabled={hayBorrador} loading={creandoDesdeId === v.id} onClick={() => onCrearDesde(v)}>
              {politicasText.detail.createFrom}
            </Button>
            {v.estado === 'Programada' && (
              <Button variant="secondary" onClick={() => onRetirar(v)}>
                {text.retire}
              </Button>
            )}
          </div>

          {comparando === v.id && <ComparacionDeVersiones tipo={tipo} versionId={v.id} />}
        </li>
      ))}
    </ol>
  )
}
