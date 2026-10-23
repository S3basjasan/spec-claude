import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import { TIPOS_ACCION, TIPOS_VINCULABLES, type TipoAccion } from '../api'
import type { AccionForm } from '../asistente'
import { pdiText } from '../text'

const t = pdiText.asistente.paso2

const OPCIONES_TIPO = TIPOS_ACCION.map((tipo) => ({ value: tipo, label: pdiText.tiposAccion[tipo] }))

interface AccionEditableProps {
  /** Prefijo único para los ids de los campos, p. ej. `c0-a1`. */
  id: string
  numero: number
  accion: AccionForm
  errores: Record<string, string>
  /** Prefijo de las claves de error, p. ej. `compromisos.0.acciones.1`. */
  ruta: string
  puedeQuitar: boolean
  puedeVincular: boolean
  onChange: (cambios: Partial<AccionForm>) => void
  onQuitar: () => void
  onVincular: () => void
}

/** Fila editable de una acción dentro de un compromiso del asistente. */
export function AccionEditable({ id, numero, accion, errores, ruta, puedeQuitar, puedeVincular, onChange, onQuitar, onVincular }: AccionEditableProps) {
  const vinculable = TIPOS_VINCULABLES.includes(accion.tipo)

  const cambiarTipo = (tipo: TipoAccion) => {
    // Un tipo que no se vincula pierde el vínculo (FR-018).
    onChange(TIPOS_VINCULABLES.includes(tipo) ? { tipo } : { tipo, plan_accion_id: null, vinculo_nombre: null })
  }

  return (
    <fieldset className="flex flex-col gap-3 rounded-lg border border-border p-3">
      <legend className="px-1 text-[13px] font-semibold text-muted">{t.accion(numero)}</legend>
      <div className="grid gap-3 sm:grid-cols-2">
        <SelectField id={`${id}-tipo`} label={t.tipoAccion} options={OPCIONES_TIPO} value={accion.tipo} onChange={(e) => cambiarTipo(e.target.value as TipoAccion)} error={errores[`${ruta}.tipo`]} />
        <TextField id={`${id}-persona`} label={t.personaCargo} value={accion.persona_cargo} maxLength={150} onChange={(e) => onChange({ persona_cargo: e.target.value })} error={errores[`${ruta}.persona_cargo`]} />
      </div>
      <TextField id={`${id}-descripcion`} label={t.descripcionAccion} value={accion.descripcion} maxLength={500} onChange={(e) => onChange({ descripcion: e.target.value })} error={errores[`${ruta}.descripcion`]} />
      <div className="grid gap-3 sm:grid-cols-2">
        <TextField id={`${id}-inicio`} type="date" label={t.inicio} value={accion.fecha_inicio} onChange={(e) => onChange({ fecha_inicio: e.target.value })} error={errores[`${ruta}.fecha_inicio`]} />
        <TextField id={`${id}-fin`} type="date" label={t.fin} value={accion.fecha_fin} onChange={(e) => onChange({ fecha_fin: e.target.value })} error={errores[`${ruta}.fecha_fin`]} />
      </div>
      {errores[`${ruta}.plan_accion_id`] !== undefined && <p role="alert" className="m-0 text-[13px] text-danger">{errores[`${ruta}.plan_accion_id`]}</p>}
      <div className="flex flex-wrap items-center gap-2">
        {vinculable && puedeVincular && accion.plan_accion_id === null && (
          <Button variant="secondary" onClick={onVincular}>
            {t.vincular}
          </Button>
        )}
        {accion.plan_accion_id !== null && (
          <>
            <span className="text-[13px] text-muted">{t.vinculadaA(accion.vinculo_nombre ?? `#${accion.plan_accion_id}`)}</span>
            <Button variant="secondary" onClick={() => onChange({ plan_accion_id: null, vinculo_nombre: null })}>
              {t.desvincular}
            </Button>
          </>
        )}
        {puedeQuitar && (
          <Button variant="secondary" onClick={onQuitar} className="ml-auto">
            {t.quitarAccion}
          </Button>
        )}
      </div>
    </fieldset>
  )
}
