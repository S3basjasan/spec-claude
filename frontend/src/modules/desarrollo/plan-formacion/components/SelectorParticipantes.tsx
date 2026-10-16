import { useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { SearchField } from '../../../../shared/ui/SearchField'
import { IconX } from '../../../../shared/ui/icons'
import { useBuscarColaboradores } from '../hooks/useOpcionesPlan'
import { planText } from '../text'

const t = planText.asistente.paso3

export interface ParticipanteSeleccionado {
  id: number
  nombre: string
}

interface SelectorParticipantesProps {
  /** Prefijo único para los ids de los controles. */
  idBase: string
  capacidad: number | null
  seleccionados: ParticipanteSeleccionado[]
  /** Avisos de cruce por colaborador (informativos); la lista vacía no muestra nada. */
  avisos?: Record<number, string[]>
  onChange: (seleccionados: ParticipanteSeleccionado[]) => void
  /** Ids con vínculo no vigente (solo informativo, p. ej. al cambiar participantes de un plan aprobado). */
  sinVinculo?: Set<number>
}

/**
 * Selector de participantes de una acción (design/DESIGN.md §4): búsqueda por colaborador con vínculo vigente,
 * indicador de capacidad usada que bloquea agregar al llegar al máximo, y avisos de cruce junto al participante.
 */
export function SelectorParticipantes({ idBase, capacidad, seleccionados, avisos = {}, onChange, sinVinculo }: SelectorParticipantesProps) {
  const [texto, setTexto] = useState('')
  const busqueda = useBuscarColaboradores(texto, true)
  const llena = capacidad !== null && seleccionados.length >= capacidad
  const ids = new Set(seleccionados.map((s) => s.id))
  const resultados = (busqueda.data?.data ?? []).filter((c) => !ids.has(c.id))

  return (
    <div className="flex flex-col gap-3">
      <p className={`text-[13px] font-semibold ${llena ? 'text-warn' : 'text-muted'}`} role="status">
        {t.capacidadUsada(seleccionados.length, capacidad)}
        {llena && ` · ${t.capacidadLlena}`}
      </p>

      {seleccionados.length === 0 ? (
        <p className="text-[13px] text-muted">{t.sinParticipantes}</p>
      ) : (
        <ul aria-label={t.participantes} className="flex flex-col gap-1.5">
          {seleccionados.map((p) => (
            <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border px-3 py-1.5">
              <div className="min-w-0">
                <span className="text-[15px] font-semibold">{p.nombre}</span>
                {sinVinculo?.has(p.id) && <span className="ml-2 text-[13px] text-warn">{t.noVigente}</span>}
                {(avisos[p.id] ?? []).map((aviso) => (
                  <p key={aviso} className="text-[13px] text-warn">
                    ⚠ {aviso}
                  </p>
                ))}
              </div>
              <button
                type="button"
                aria-label={t.quitar(p.nombre)}
                onClick={() => onChange(seleccionados.filter((s) => s.id !== p.id))}
                className="flex size-9 items-center justify-center rounded-lg text-muted hover:bg-soft"
              >
                <IconX size={16} />
              </button>
            </li>
          ))}
        </ul>
      )}

      <div className="flex flex-col gap-2">
        <SearchField id={`${idBase}-buscar`} label={t.buscar} placeholder={t.buscarPlaceholder} value={texto} onChange={(e) => setTexto(e.target.value)} disabled={llena} />
        {!llena && (
          <ul aria-label={t.buscar} className="flex max-h-48 flex-col gap-1 overflow-y-auto">
            {resultados.length === 0 && !busqueda.isPending && <li className="px-1 text-[13px] text-muted">{t.sinResultados}</li>}
            {resultados.map((c) => (
              <li key={c.id} className="flex items-center justify-between gap-2 rounded-lg px-1 py-0.5 hover:bg-soft">
                <span className="text-[15px]">
                  {c.nombre} <span className="text-[13px] text-muted">{c.codigo}</span>
                </span>
                <Button variant="secondary" aria-label={`${t.agregar}: ${c.nombre}`} onClick={() => onChange([...seleccionados, { id: c.id, nombre: c.nombre }])}>
                  {t.agregar}
                </Button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  )
}
