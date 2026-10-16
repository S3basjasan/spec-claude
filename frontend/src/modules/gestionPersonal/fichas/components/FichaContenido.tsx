import { useState } from 'react'
import type { ColaboradorDetalle } from '../api'
import { SeccionDesarrollo } from '../../desarrollo/components/SeccionDesarrollo'
import { fichasText } from '../text'
import { FichaDatos } from './FichaDatos'

const tabs = fichasText.detail.tabs

type Seccion = 'personal' | 'desarrollo' | 'documentos'

interface FichaContenidoProps {
  ficha: ColaboradorDetalle
  onReintentar: () => void
  /** Vista propia (Mi ficha): el desarrollo se consulta por `/api/mi-ficha` y es de solo lectura. */
  esMiFicha?: boolean
}

/**
 * Secciones de la ficha: «Personal y puesto» siempre; Desarrollo y Documentos solo cuando su
 * funcionalidad está instalada y autorizada (`capacidades`). El panel se monta solo al activar su pestaña.
 */
export function FichaContenido({ ficha, onReintentar, esMiFicha = false }: FichaContenidoProps) {
  const [activa, setActiva] = useState<Seccion>('personal')

  const disponibles: { id: Seccion; label: string }[] = [{ id: 'personal', label: tabs.personal }]
  if (ficha.capacidades.desarrollo) disponibles.push({ id: 'desarrollo', label: tabs.development })
  if (ficha.capacidades.documentos) disponibles.push({ id: 'documentos', label: tabs.documents })

  if (disponibles.length === 1) {
    return <FichaDatos ficha={ficha} onReintentar={onReintentar} />
  }

  return (
    <div className="flex flex-col gap-4">
      <div role="tablist" aria-label={fichasText.detail.sectionsLabel} className="flex gap-1 border-b border-border">
        {disponibles.map((seccion) => (
          <button
            key={seccion.id}
            id={`tab-${seccion.id}`}
            type="button"
            role="tab"
            aria-selected={activa === seccion.id}
            aria-controls={`panel-${seccion.id}`}
            onClick={() => setActiva(seccion.id)}
            className={`min-h-11 border-b-2 px-4 text-[15px] font-semibold ${
              activa === seccion.id ? 'border-accent text-accent' : 'border-transparent text-muted'
            }`}
          >
            {seccion.label}
          </button>
        ))}
      </div>
      <div id={`panel-${activa}`} role="tabpanel" aria-labelledby={`tab-${activa}`}>
        {activa === 'personal' ? <FichaDatos ficha={ficha} onReintentar={onReintentar} /> : null}
        {activa === 'desarrollo' ? <SeccionDesarrollo colaboradorId={esMiFicha ? null : ficha.id} /> : null}
      </div>
    </div>
  )
}
