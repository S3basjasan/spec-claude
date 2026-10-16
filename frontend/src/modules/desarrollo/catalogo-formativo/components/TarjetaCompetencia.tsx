import { Badge } from '../../../../shared/ui/Badge'
import type { FilaCatalogo } from '../api'
import { catalogoText } from '../text'
import { NivelPuntos } from './NivelPuntos'

interface TarjetaCompetenciaProps {
  fila: FilaCatalogo
  onAbrir: (id: number) => void
}

/** Tarjeta de una competencia: nombre, tipo, nivel actual (puntos A/B/C/D) y cursos disponibles. Toda ella abre el detalle. */
export function TarjetaCompetencia({ fila, onAbrir }: TarjetaCompetenciaProps) {
  return (
    <button
      type="button"
      onClick={() => onAbrir(fila.competencia_id)}
      className="flex h-full w-full flex-col gap-3 rounded-xl border border-border bg-surface p-5 text-left hover:bg-soft"
    >
      <span className="flex flex-wrap items-center gap-2">
        <span className="text-base font-bold">{fila.nombre}</span>
        <Badge>{catalogoText.tipoCompetencia[fila.tipo]}</Badge>
      </span>
      <NivelPuntos nivel={fila.nivel_actual} />
      <span className="text-[13px] text-muted">{catalogoText.collaborator.cardCourses(fila.cursos_count)}</span>
    </button>
  )
}
