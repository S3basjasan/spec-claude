import { useEffect, useState } from 'react'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { SearchField } from '../../../../shared/ui/SearchField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Recurso, TipoRecurso } from '../api'
import { useRecursosDisponibles } from '../hooks/useCatalogo'
import { catalogoText } from '../text'

interface SelectorDeRecursosProps {
  tipo: TipoRecurso
  competenciaId: number
  /** Ids ya elegidos en el panel (aún sin guardar): no se vuelven a ofrecer. */
  excluidos: number[]
  /** Devuelve los recursos elegidos para agregarlos a la competencia. */
  onAsociar: (recursos: Recurso[]) => void
  onVolver: () => void
}

const RETARDO_BUSQUEDA_MS = 300
const text = catalogoText.selector

/** Selector de selección múltiple de los cursos o micro-contenidos disponibles que aún no están asociados. */
export function SelectorDeRecursos({ tipo, competenciaId, excluidos, onAsociar, onVolver }: SelectorDeRecursosProps) {
  const [busqueda, setBusqueda] = useState('')
  const [consultaTexto, setConsultaTexto] = useState('')
  const [elegidos, setElegidos] = useState<number[]>([])
  const consulta = useRecursosDisponibles(tipo, competenciaId, consultaTexto)

  useEffect(() => {
    const espera = setTimeout(() => setConsultaTexto(busqueda.trim()), RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [busqueda])

  const disponibles = (consulta.data ?? []).filter((recurso) => !excluidos.includes(recurso.id))
  const titulo = tipo === 'curso' ? text.courseTitle : text.microTitle

  const alternar = (id: number) => setElegidos((actual) => (actual.includes(id) ? actual.filter((x) => x !== id) : [...actual, id]))

  const vacio = () => {
    if (consultaTexto !== '') return text.noResults
    return tipo === 'microcontenido' && (consulta.data ?? []).length === 0 ? text.noMicro : text.allAssociated
  }

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h3 className="text-lg font-bold">{titulo}</h3>
        <p className="text-sm text-muted">{text.help}</p>
      </div>

      <SearchField id="buscar-recurso" label={text.searchLabel} placeholder={text.searchPlaceholder} value={busqueda} onChange={(e) => setBusqueda(e.target.value)} maxLength={50} />

      {consulta.isPending ? (
        <div role="status" aria-label={text.loadingLabel} className="flex flex-col gap-2">
          <Skeleton className="h-14" />
          <Skeleton className="h-14" />
        </div>
      ) : consulta.isError ? (
        <ErrorState title={text.errorTitle} description="" retryLabel={text.retry} onRetry={() => void consulta.refetch()} />
      ) : disponibles.length === 0 ? (
        <p className="rounded-lg border border-border bg-surface px-4 py-6 text-center text-sm text-muted">{vacio()}</p>
      ) : (
        <ul aria-label={titulo} className="m-0 flex list-none flex-col gap-2 p-0">
          {disponibles.map((recurso) => {
            const id = `recurso-${tipo}-${recurso.id}`
            return (
              <li key={recurso.id}>
                <label htmlFor={id} className="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-border bg-surface px-4 py-3 hover:bg-soft">
                  <input
                    id={id}
                    type="checkbox"
                    checked={elegidos.includes(recurso.id)}
                    onChange={() => alternar(recurso.id)}
                    className="mt-1 size-4 accent-[var(--accent)]"
                  />
                  <span className="flex min-w-0 flex-1 flex-col gap-1">
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="text-[15px] font-semibold">{recurso.nombre}</span>
                      <Badge>{catalogoText.tipoRecurso[recurso.tipo] ?? recurso.tipo}</Badge>
                    </span>
                    <span className="text-[13px] text-muted">
                      {catalogoText.duracion(recurso.duracion_minutos)}
                      {recurso.formador ? ` · ${recurso.formador}` : ''}
                    </span>
                    <span className="text-[13px] text-muted">{text.usedIn(recurso.competencias_count ?? 0)}</span>
                  </span>
                </label>
              </li>
            )
          })}
        </ul>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <button type="button" onClick={onVolver} className="min-h-11 font-semibold text-accent underline">
          {text.back}
        </button>
        <Button
          disabled={elegidos.length === 0}
          onClick={() => onAsociar(disponibles.filter((recurso) => elegidos.includes(recurso.id)))}
        >
          {text.associate(elegidos.length)}
        </Button>
      </div>
    </div>
  )
}
