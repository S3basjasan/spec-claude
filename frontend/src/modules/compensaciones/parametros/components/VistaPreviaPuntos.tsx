import { fechaLarga } from '../formato'
import { parametrosText } from '../text'

interface VistaPreviaPuntosProps {
  actual: number
  /** Valor escrito por el Gestor, ya validado; nulo si todavía no es un número válido. */
  nuevo: number | null
  /** Primer día del mes siguiente (YYYY-MM-DD). */
  vigenteDesde: string
}

const text = parametrosText.preview

/** Vista previa de los puntos por colaborador: actual → nuevo, desde cuándo rige y un equipo de ejemplo de 10 personas. Sin cálculo por líder. */
export function VistaPreviaPuntos({ actual, nuevo, vigenteDesde }: VistaPreviaPuntosProps) {
  const mostrado = nuevo ?? actual
  return (
    <div aria-live="polite" className="flex flex-col gap-1 rounded-lg border border-border bg-soft px-3.5 py-3 text-sm">
      <p className="font-semibold">{text.title}</p>
      {nuevo !== null && nuevo !== actual && (
        <>
          <p>{text.change(actual, nuevo)}</p>
          <p className="text-muted">{text.since(fechaLarga(vigenteDesde))}</p>
        </>
      )}
      <p>{text.example(mostrado * 10)}</p>
    </div>
  )
}
