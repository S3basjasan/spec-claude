import { useId, type ReactNode } from 'react'
import type { Escala } from '../api'
import { competenciasText } from '../text'

const text = competenciasText.form

interface SelectorDeEscalaProps {
  escalas: Escala[]
  /** Id de la escala elegida. */
  valor: number | null
  onElegir: (escala: Escala) => void
  /** Error de la escala (p. ej. "Selecciona la escala de los grados."). */
  error?: string
  /** Campos de los nombres de categoría: se muestran solo con la escala Categorías. */
  categorias?: ReactNode
}

/**
 * Elige cómo se nombran los cuatro grados: Letras, Números o Categorías. Es un grupo de opciones (`radiogroup`)
 * con un ejemplo por escala; con Categorías se muestran además los cuatro nombres propios de la competencia.
 */
export function SelectorDeEscala({ escalas, valor, onElegir, error, categorias }: SelectorDeEscalaProps) {
  const legendId = useId()
  const elegida = escalas.find((escala) => escala.id === valor)

  return (
    <div role="radiogroup" aria-labelledby={legendId} className="flex flex-col gap-2">
      <span id={legendId} className="text-[15px] font-semibold">
        {text.scaleLegend}
      </span>
      <span className="text-[13px] text-muted">{text.scaleHint}</span>
      <div className="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-2">
        {escalas.map((escala) => {
          const activa = escala.id === valor
          const ejemplo = text.scaleExamples[escala.nombre as keyof typeof text.scaleExamples] ?? ''
          return (
            <button
              key={escala.id}
              type="button"
              role="radio"
              aria-checked={activa}
              onClick={() => onElegir(escala)}
              className={`flex min-h-14 cursor-pointer flex-col items-start gap-0.5 rounded-[10px] px-3 py-2.5 text-left text-text ${
                activa ? 'border-2 border-accent bg-soft' : 'border border-border bg-surface hover:bg-soft'
              }`}
            >
              <span className="font-semibold">{escala.nombre}</span>
              <span className="text-[13px] text-muted">{ejemplo}</span>
            </button>
          )
        })}
      </div>
      {error && (
        <p role="alert" className="text-[13px] text-danger">
          {error}
        </p>
      )}
      {elegida?.nombre === 'Categorías' && categorias}
    </div>
  )
}
