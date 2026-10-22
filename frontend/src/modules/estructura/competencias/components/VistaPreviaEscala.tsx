import { Button } from '../../../../shared/ui/Button'
import type { Escala } from '../api'
import { competenciasText } from '../text'

const text = competenciasText.scale

interface VistaPreviaEscalaProps {
  escalas: Escala[]
  /** Escala guardada de la competencia. */
  actual: number | null
  /** Escala que se está previsualizando; null o igual a la actual = sin vista previa. */
  seleccion: number | null
  onSeleccionar: (id: number) => void
  onDescartar: () => void
  /** Solo el administrador puede aplicar la escala. */
  esAdmin: boolean
  onAplicar: () => void
  aplicando?: boolean
  error?: string
}

/**
 * Vista previa de la escala de grados: tres chips ("Ver con escala:") que cambian los nombres solo en pantalla.
 * Con una vista previa distinta de la guardada aparecen "Aplicar esta escala" (solo administrador) y "Descartar".
 */
export function VistaPreviaEscala({ escalas, actual, seleccion, onSeleccionar, onDescartar, esAdmin, onAplicar, aplicando = false, error }: VistaPreviaEscalaProps) {
  const elegida = seleccion ?? actual
  const hayVistaPrevia = seleccion !== null && seleccion !== actual

  return (
    <div role="group" aria-label={text.groupLabel} className="flex flex-wrap items-center gap-2">
      <span className="text-[13px] text-muted">{text.previewLabel}</span>
      {escalas.map((escala) => {
        const activa = escala.id === elegida
        return (
          <button
            key={escala.id}
            type="button"
            aria-pressed={activa}
            onClick={() => onSeleccionar(escala.id)}
            className={`min-h-11 cursor-pointer rounded-full px-3.5 text-sm font-semibold ${
              activa ? 'border-2 border-accent bg-soft text-accent' : 'border border-border bg-surface text-text hover:bg-soft'
            }`}
          >
            {text.names[escala.nombre as keyof typeof text.names] ?? escala.nombre}
          </button>
        )
      })}
      {hayVistaPrevia && (
        <>
          {esAdmin && (
            <Button onClick={onAplicar} loading={aplicando}>
              {aplicando ? text.applying : text.apply}
            </Button>
          )}
          <Button variant="secondary" onClick={onDescartar} disabled={aplicando}>
            {text.discard}
          </Button>
        </>
      )}
      {error && (
        <p role="alert" className="basis-full text-[13px] text-danger">
          {error}
        </p>
      )}
    </div>
  )
}
