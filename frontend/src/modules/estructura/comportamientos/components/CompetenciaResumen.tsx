import { Badge } from '../../../../shared/ui/Badge'
import type { EstadoCompetencia, TipoCompetencia } from '../api'
import { comportamientosText } from '../text'

interface CompetenciaResumenProps {
  nombre: string
  descripcion: string
  tipo: TipoCompetencia
  /** Solo se muestra cuando se conoce (al editar). */
  estado?: EstadoCompetencia
  codigo?: string
}

/** Datos de la competencia asociada en solo lectura; vienen del diccionario de competencias. */
export function CompetenciaResumen({ nombre, descripcion, tipo, estado, codigo }: CompetenciaResumenProps) {
  const text = comportamientosText.summary
  return (
    <dl aria-label={comportamientosText.form.competencia} className="grid gap-x-4 gap-y-2 rounded-lg bg-bg p-4 text-sm sm:grid-cols-[auto_1fr]">
      {codigo && (
        <>
          <dt className="font-semibold">{text.code}</dt>
          <dd>{codigo}</dd>
        </>
      )}
      <dt className="font-semibold">{text.name}</dt>
      <dd>{nombre}</dd>
      <dt className="font-semibold">{text.description}</dt>
      <dd>{descripcion}</dd>
      <dt className="font-semibold">{text.type}</dt>
      <dd>
        <Badge>{comportamientosText.types[tipo]}</Badge>
      </dd>
      {estado && (
        <>
          <dt className="font-semibold">{text.status}</dt>
          <dd>
            <Badge variant={estado === 'Activo' ? 'default' : 'neutral'}>{comportamientosText.statuses[estado]}</Badge>
          </dd>
        </>
      )}
    </dl>
  )
}
