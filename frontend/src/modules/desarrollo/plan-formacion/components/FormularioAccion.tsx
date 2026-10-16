import { useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { Combobox } from '../../../../shared/ui/Combobox'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import { Modal } from '../../../../shared/ui/Modal'
import { IconX } from '../../../../shared/ui/icons'
import { TIPOS_ACCION, type CursoCatalogo, type OpcionesPlan, type TipoAccion } from '../api'
import { planText } from '../text'
import { ACCION_VACIA, validarAccion, type ErroresFormulario, type ResultadoAccion, type ValoresAccion } from '../validation'

const t = planText.asistente.accion

interface FormularioAccionProps {
  open: boolean
  anio: number
  /** Valores iniciales (edición) o vacíos (nueva). */
  inicial?: ValoresAccion
  opciones: OpcionesPlan | undefined
  cursos: CursoCatalogo[] | undefined
  /** Nombres de competencias que ya no están activas, para mostrar las ya asociadas. */
  nombresExtra: Record<number, string>
  onGuardar: (accion: Extract<ResultadoAccion, { ok: true }>['accion']) => void
  onCancelar: () => void
}

/** Formulario de una acción formativa: en un Borrador solo el nombre y las competencias objetivo son obligatorios. */
export function FormularioAccion({ open, anio, inicial, opciones, cursos, nombresExtra, onGuardar, onCancelar }: FormularioAccionProps) {
  // El contenido se monta solo con el modal abierto, así cada apertura parte de los valores iniciales.
  if (!open) return null
  return <Contenido anio={anio} inicial={inicial ?? ACCION_VACIA} opciones={opciones} cursos={cursos} nombresExtra={nombresExtra} onGuardar={onGuardar} onCancelar={onCancelar} esNueva={inicial === undefined} />
}

function Contenido({
  anio,
  inicial,
  opciones,
  cursos,
  nombresExtra,
  onGuardar,
  onCancelar,
  esNueva,
}: Omit<FormularioAccionProps, 'open' | 'inicial'> & { inicial: ValoresAccion; esNueva: boolean }) {
  const [valores, setValores] = useState<ValoresAccion>(inicial)
  const [errores, setErrores] = useState<ErroresFormulario>({})

  const cambiar = (cambios: Partial<ValoresAccion>) => setValores((v) => ({ ...v, ...cambios }))
  const nombreDe = (id: number) => opciones?.competencias.find((c) => c.id === id)?.nombre ?? nombresExtra[id] ?? `#${id}`
  const activas = new Set(opciones?.competencias.map((c) => c.id) ?? [])

  const elegirCurso = (valor: string | null) => {
    if (valor === null) {
      cambiar({ curso_id: null })
      return
    }
    const curso = cursos?.find((c) => String(c.id) === valor)
    if (!curso) return
    // El curso propone tipo, formador, duración y competencias activas; todo se puede modificar después.
    setValores((v) => ({
      ...v,
      curso_id: curso.id,
      nombre: v.nombre.trim() === '' ? curso.nombre : v.nombre,
      tipo: curso.tipo_sugerido ?? v.tipo,
      formador: curso.formador ?? v.formador,
      duracion_horas: String(curso.duracion_horas),
      competencia_ids: Array.from(new Set([...v.competencia_ids, ...curso.competencia_ids.filter((id) => activas.has(id))])),
    }))
  }

  const agregarCompetencia = (valor: string | null) => {
    if (valor === null) return
    const id = Number(valor)
    if (!valores.competencia_ids.includes(id)) cambiar({ competencia_ids: [...valores.competencia_ids, id] })
  }

  const guardar = () => {
    const resultado = validarAccion(valores, anio)
    if (!resultado.ok) {
      setErrores(resultado.errores)
      return
    }
    onGuardar(resultado.accion)
  }

  return (
    <Modal
      open
      size="lg"
      title={esNueva ? t.titleNueva : t.titleEditar}
      onClose={onCancelar}
      footer={
        <>
          <Button variant="secondary" onClick={onCancelar}>
            {t.cancelar}
          </Button>
          <Button onClick={guardar}>{t.aceptar}</Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <div>
          <Combobox
            id="accion-curso"
            label={t.curso}
            placeholder={t.cursoPlaceholder}
            emptyText={t.cursoSinResultados}
            options={(cursos ?? []).map((c) => ({ value: String(c.id), label: c.nombre, description: c.formador ?? undefined }))}
            value={valores.curso_id === null ? null : String(valores.curso_id)}
            onChange={elegirCurso}
          />
          <p className="mt-1.5 text-[13px] text-muted">{t.cursoHint}</p>
        </div>

        <TextField id="accion-nombre" label={t.nombre} value={valores.nombre} error={errores.nombre} maxLength={120} autoComplete="off" onChange={(e) => cambiar({ nombre: e.target.value })} />

        <div className="flex flex-col gap-2">
          <Combobox
            id="accion-competencia"
            label={t.competencias}
            placeholder={t.competenciasAgregar}
            options={(opciones?.competencias ?? []).filter((c) => !valores.competencia_ids.includes(c.id)).map((c) => ({ value: String(c.id), label: c.nombre, description: c.codigo }))}
            value={null}
            error={errores.competencia_ids}
            onChange={agregarCompetencia}
          />
          {valores.competencia_ids.length > 0 && (
            <ul aria-label={t.competencias} className="flex flex-wrap gap-2">
              {valores.competencia_ids.map((id) => (
                <li key={id} className="inline-flex items-center gap-1 rounded-full bg-soft py-1 pl-3 pr-1 text-[13px] font-semibold text-accent">
                  {nombreDe(id)}
                  {!activas.has(id) && <span className="text-muted">({t.competenciasInactiva})</span>}
                  <button
                    type="button"
                    aria-label={t.competenciasQuitar(nombreDe(id))}
                    onClick={() => cambiar({ competencia_ids: valores.competencia_ids.filter((c) => c !== id) })}
                    className="flex size-7 items-center justify-center rounded-full hover:bg-surface"
                  >
                    <IconX size={14} />
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField
            id="accion-tipo"
            label={t.tipo}
            value={valores.tipo}
            onChange={(e) => cambiar({ tipo: e.target.value as TipoAccion | '' })}
            options={[{ value: '', label: t.tipoSinSeleccion }, ...TIPOS_ACCION.map((tipo) => ({ value: tipo, label: planText.tipo[tipo] }))]}
          />
          <TextField id="accion-formador" label={t.formador} value={valores.formador} maxLength={120} autoComplete="off" onChange={(e) => cambiar({ formador: e.target.value })} />
          <TextField id="accion-inicio" type="date" label={t.fechaInicio} value={valores.fecha_inicio} error={errores.fecha_inicio} onChange={(e) => cambiar({ fecha_inicio: e.target.value })} />
          <TextField id="accion-fin" type="date" label={t.fechaFin} value={valores.fecha_fin} error={errores.fecha_fin} onChange={(e) => cambiar({ fecha_fin: e.target.value })} />
          <TextField id="accion-horas" label={t.horas} value={valores.duracion_horas} error={errores.duracion_horas} inputMode="decimal" autoComplete="off" onChange={(e) => cambiar({ duracion_horas: e.target.value })} />
          <TextField id="accion-capacidad" label={t.capacidad} value={valores.capacidad_maxima} error={errores.capacidad_maxima} inputMode="numeric" autoComplete="off" onChange={(e) => cambiar({ capacidad_maxima: e.target.value })} />
          <TextField id="accion-costo" label={t.costo} value={valores.costo} error={errores.costo} inputMode="decimal" autoComplete="off" onChange={(e) => cambiar({ costo: e.target.value })} />
        </div>
      </div>
    </Modal>
  )
}
