import { NIVELES, type Nivel } from '../api'
import { catalogoText } from '../text'

interface NivelPuntosProps {
  /** Nivel actual de la persona; sin nivel registrado los cuatro puntos van sin resaltar. */
  nivel: Nivel | null | undefined
}

/**
 * Cuatro puntos A/B/C/D con el nivel actual resaltado. El nivel no se comunica solo con color: el texto
 * accesible dice "Tu nivel: B" o "Sin nivel registrado".
 */
export function NivelPuntos({ nivel }: NivelPuntosProps) {
  const texto = nivel ? catalogoText.collaborator.yourLevel(nivel) : catalogoText.collaborator.noLevel

  return (
    <div className="flex items-center gap-1.5">
      <span className="sr-only">{texto}</span>
      <div aria-hidden="true" className="flex gap-1.5">
        {NIVELES.map((letra) => (
          <span
            key={letra}
            data-resaltado={letra === nivel ? 'true' : undefined}
            className={`flex size-6 items-center justify-center rounded-full text-[11px] font-bold ${
              letra === nivel ? 'bg-accent text-onaccent' : 'bg-border text-muted'
            }`}
          >
            {letra}
          </span>
        ))}
      </div>
    </div>
  )
}
