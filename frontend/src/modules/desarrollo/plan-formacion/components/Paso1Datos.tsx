import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TextField } from '../../../../shared/ui/TextField'
import { planText } from '../text'
import type { ErroresFormulario, ValoresPaso1 } from '../validation'

const t = planText.asistente.paso1

interface Paso1DatosProps {
  valores: ValoresPaso1
  errores: ErroresFormulario
  onChange: (cambios: Partial<ValoresPaso1>) => void
}

/** Paso 1 del asistente: nombre, año y descripción del plan. */
export function Paso1Datos({ valores, errores, onChange }: Paso1DatosProps) {
  return (
    <div className="grid gap-4 sm:grid-cols-[2fr_1fr]">
      <TextField
        id="plan-nombre"
        label={t.nombre}
        value={valores.nombre}
        error={errores.nombre}
        maxLength={120}
        autoComplete="off"
        onChange={(e) => onChange({ nombre: e.target.value })}
      />
      <TextField
        id="plan-anio"
        label={t.anio}
        value={valores.anio}
        error={errores.anio}
        hint={t.anioHint}
        inputMode="numeric"
        maxLength={4}
        autoComplete="off"
        onChange={(e) => onChange({ anio: e.target.value })}
      />
      <div className="sm:col-span-2">
        <TextareaField
          id="plan-descripcion"
          label={t.descripcion}
          value={valores.descripcion}
          error={errores.descripcion}
          maxLength={2000}
          currentLength={valores.descripcion.length}
          rows={4}
          onChange={(e) => onChange({ descripcion: e.target.value })}
        />
      </div>
    </div>
  )
}
