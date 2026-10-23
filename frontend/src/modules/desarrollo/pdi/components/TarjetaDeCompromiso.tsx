import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { GRADOS, type DimensionPdi, type Grado } from '../api'
import { accionVacia, type AccionForm, type CompromisoForm } from '../asistente'
import { pdiText } from '../text'
import { AccionEditable } from './AccionEditable'

const t = pdiText.asistente.paso2

interface TarjetaDeCompromisoProps {
  numero: number
  compromiso: CompromisoForm
  errores: Record<string, string>
  dimensiones: DimensionPdi[]
  competencias: { id: number; nombre: string }[]
  onChange: (cambios: Partial<CompromisoForm>) => void
  onQuitar: () => void
  /** Abre el selector de acciones vinculables para esa acción. */
  onVincular: (accionClave: string) => void
  puedeVincular: boolean
}

const OPCIONES_NIVEL = [{ value: '', label: t.sinNivel }, ...GRADOS.map((g) => ({ value: g, label: g }))]

/** Tarjeta editable de un compromiso (con sus acciones) dentro del paso 2 del asistente. */
export function TarjetaDeCompromiso({ numero, compromiso: c, errores, dimensiones, competencias, onChange, onQuitar, onVincular, puedeVincular }: TarjetaDeCompromisoProps) {
  const ruta = `compromisos.${numero - 1}`
  const id = `c${numero - 1}`
  const conElemento = c.dimension === 'Competencia' || c.dimension === 'Conocimiento'

  const cambiarDimension = (dimension: DimensionPdi) => {
    const nuevoElemento = dimension === 'Competencia' || dimension === 'Conocimiento'
    onChange({
      dimension,
      tipo_elemento: nuevoElemento ? dimension : null,
      elemento_id: nuevoElemento ? c.elemento_id : null,
      elemento_nombre: nuevoElemento ? c.elemento_nombre : null,
      nivel_actual: nuevoElemento ? c.nivel_actual : '',
      nivel_objetivo: nuevoElemento ? c.nivel_objetivo : '',
    })
  }

  const cambiarAccion = (clave: string, cambios: Partial<AccionForm>) => onChange({ acciones: c.acciones.map((a) => (a.clave === clave ? { ...a, ...cambios } : a)) })

  const opcionesElemento = [
    { value: '', label: t.elegirElemento },
    ...competencias.map((x) => ({ value: String(x.id), label: x.nombre })),
    ...(c.elemento_id !== null && !competencias.some((x) => x.id === c.elemento_id) ? [{ value: String(c.elemento_id), label: c.elemento_nombre ?? `#${c.elemento_id}` }] : []),
  ]

  return (
    <section aria-label={t.compromiso(numero)} className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4">
      <header className="flex items-center justify-between gap-3">
        <h3 className="m-0 text-base font-bold">{t.compromiso(numero)}</h3>
        <Button variant="secondary" onClick={onQuitar}>
          {t.quitar}
        </Button>
      </header>

      <div className="grid gap-3 sm:grid-cols-2">
        <SelectField
          id={`${id}-dimension`}
          label={t.dimension}
          options={dimensiones.map((d) => ({ value: d, label: pdiText.dimensiones[d] }))}
          value={c.dimension}
          onChange={(e) => cambiarDimension(e.target.value as DimensionPdi)}
          error={errores[`${ruta}.dimension`]}
        />
        {conElemento ? (
          <SelectField
            id={`${id}-elemento`}
            label={t.elemento}
            options={opcionesElemento}
            value={c.elemento_id === null ? '' : String(c.elemento_id)}
            onChange={(e) => {
              const elegido = competencias.find((x) => String(x.id) === e.target.value)
              onChange({ elemento_id: elegido?.id ?? null, elemento_nombre: elegido?.nombre ?? null })
            }}
            error={errores[`${ruta}.elemento_id`]}
          />
        ) : (
          <TextareaField id={`${id}-descripcion`} label={t.descripcion} value={c.descripcion} maxLength={500} currentLength={c.descripcion.length} onChange={(e) => onChange({ descripcion: e.target.value })} error={errores[`${ruta}.descripcion`]} />
        )}
      </div>

      {conElemento && (
        <div className="grid gap-3 sm:grid-cols-2">
          <SelectField id={`${id}-nivel-actual`} label={t.nivelActual} options={OPCIONES_NIVEL} value={c.nivel_actual} onChange={(e) => onChange({ nivel_actual: e.target.value as Grado | '' })} error={errores[`${ruta}.nivel_actual`]} />
          <SelectField id={`${id}-nivel-objetivo`} label={t.nivelObjetivo} options={OPCIONES_NIVEL} value={c.nivel_objetivo} onChange={(e) => onChange({ nivel_objetivo: e.target.value as Grado | '' })} error={errores[`${ruta}.nivel_objetivo`]} />
        </div>
      )}

      <TextField id={`${id}-indicador`} label={t.indicador} value={c.indicador} maxLength={500} onChange={(e) => onChange({ indicador: e.target.value })} error={errores[`${ruta}.indicador`]} />
      <div className="grid gap-3 sm:grid-cols-2">
        <TextField id={`${id}-inicio`} type="date" label={t.inicio} value={c.fecha_inicio} onChange={(e) => onChange({ fecha_inicio: e.target.value })} error={errores[`${ruta}.fecha_inicio`]} />
        <TextField id={`${id}-fin`} type="date" label={t.fin} value={c.fecha_fin} onChange={(e) => onChange({ fecha_fin: e.target.value })} error={errores[`${ruta}.fecha_fin`]} />
      </div>

      <div className="flex flex-col gap-3">
        <h4 className="m-0 text-[15px] font-semibold">{t.acciones}</h4>
        {c.acciones.length === 0 && <p className="m-0 text-[13px] text-danger">{t.sinAcciones}</p>}
        {c.acciones.map((a, j) => (
          <AccionEditable
            key={a.clave}
            id={`${id}-a${j}`}
            numero={j + 1}
            accion={a}
            errores={errores}
            ruta={`${ruta}.acciones.${j}`}
            puedeQuitar={c.acciones.length > 1}
            puedeVincular={puedeVincular}
            onChange={(cambios) => cambiarAccion(a.clave, cambios)}
            onQuitar={() => onChange({ acciones: c.acciones.filter((x) => x.clave !== a.clave) })}
            onVincular={() => onVincular(a.clave)}
          />
        ))}
        <div>
          <Button variant="secondary" onClick={() => onChange({ acciones: [...c.acciones, accionVacia(c.fecha_inicio, c.fecha_fin)] })}>
            {t.agregarAccion}
          </Button>
        </div>
      </div>
    </section>
  )
}
