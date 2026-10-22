import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import type { Definicion, Tarea } from '../api'
import { politicasText } from '../text'
import type { ParametrosEnEdicion } from '../validation'
import { EditorDeTareas } from './EditorDeTareas'

const text = politicasText

const OPCIONES_BOOLEANAS = [
  { value: '', label: text.params.undefined },
  { value: 'true', label: text.yes },
  { value: 'false', label: text.no },
]

interface FormularioDeParametrosProps {
  /** Prefijo de los ids de los campos. */
  id: string
  definicion: Definicion
  valores: ParametrosEnEdicion
  onChange: (valores: ParametrosEnEdicion) => void
  /** Errores por campo con las claves `parametros.<clave>` del servidor. */
  errores: Record<string, string>
  disabled?: boolean
}

/**
 * Campos de los parámetros admitidos para el tipo, generados desde la definición del servidor. Ningún campo trae un
 * valor de negocio supuesto: «Sin definir» hasta que el administrador elige.
 */
export function FormularioDeParametros({ id, definicion, valores, onChange, errores, disabled = false }: FormularioDeParametrosProps) {
  const cambiar = (clave: string, valor: ParametrosEnEdicion[string]) => onChange({ ...valores, [clave]: valor })

  return (
    <div className="flex flex-col gap-5">
      {definicion.parametros.map((parametro) => {
        const campo = `${id}-${parametro.clave}`
        const error = errores[`parametros.${parametro.clave}`]

        if (parametro.tipo === 'booleano') {
          const valor = valores[parametro.clave]
          return (
            <div key={parametro.clave} className="flex flex-col gap-1">
              <SelectField
                id={campo}
                label={parametro.etiqueta}
                options={OPCIONES_BOOLEANAS}
                value={typeof valor === 'boolean' ? String(valor) : ''}
                onChange={(e) => cambiar(parametro.clave, e.target.value === '' ? undefined : e.target.value === 'true')}
                error={error}
                disabled={disabled}
                aria-describedby={`${campo}-ayuda`}
              />
              <p id={`${campo}-ayuda`} className="text-[13px] text-muted">
                {parametro.ayuda}
              </p>
            </div>
          )
        }

        if (parametro.tipo === 'entero') {
          const valor = valores[parametro.clave]
          return (
            <TextField
              key={parametro.clave}
              id={campo}
              label={parametro.etiqueta}
              hint={parametro.ayuda}
              type="number"
              inputMode="numeric"
              min={parametro.minimo ?? 0}
              step={1}
              value={valor === undefined ? '' : String(valor)}
              onChange={(e) => cambiar(parametro.clave, e.target.value === '' ? undefined : e.target.value)}
              error={error}
              disabled={disabled}
            />
          )
        }

        const tareas = Array.isArray(valores[parametro.clave]) ? (valores[parametro.clave] as Tarea[]) : []
        return (
          <div key={parametro.clave} className="flex flex-col gap-2">
            <p className="text-[13px] text-muted">{parametro.ayuda}</p>
            <EditorDeTareas id={campo} tareas={tareas} onChange={(siguiente) => cambiar(parametro.clave, siguiente)} errores={errores} errorDelGrupo={error} disabled={disabled} />
          </div>
        )
      })}
    </div>
  )
}
