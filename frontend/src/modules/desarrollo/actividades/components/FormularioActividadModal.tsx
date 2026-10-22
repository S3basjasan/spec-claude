import { useState, type FormEvent } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextField } from '../../../../shared/ui/TextField'
import { TIPOS_CON_ENLACE, type AccionDisponible, type ActividadDetalle, type OpcionesActividades, type TipoAccion } from '../api'
import { useActividad, useOpcionesActividades } from '../hooks/useActividades'
import { useGuardarActividad } from '../hooks/useMutacionesActividad'
import { actividadesText } from '../text'
import { tieneErrores, validarActividad, type BorradorActividad, type ErroresActividad } from '../validation'
import { DatosHeredados, type AccionHeredada } from './DatosHeredados'
import { SelectorAccion } from './SelectorAccion'

const t = actividadesText.formulario

interface FormularioActividadModalProps {
  /** Id de la actividad a editar; `null` para crear una nueva. */
  actividadId: number | null
  onCerrar: () => void
  onGuardado: (actividad: ActividadDetalle, creada: boolean) => void
}

const heredadaDeAccion = (a: AccionDisponible): AccionHeredada => ({
  nombre: a.nombre,
  tipo: a.tipo,
  competencias: a.competencias,
  fecha_inicio: a.fecha_inicio,
  fecha_fin: a.fecha_fin,
  formador: a.formador,
  capacidad: a.capacidad,
  inscritos: a.inscritos,
  plan: `${a.plan.codigo} · ${a.plan.nombre}`,
})

const heredadaDeActividad = (a: ActividadDetalle): AccionHeredada => ({
  nombre: a.nombre,
  tipo: a.tipo,
  competencias: a.competencias,
  fecha_inicio: a.fecha_inicio,
  fecha_fin: a.fecha_fin,
  formador: a.formador,
  capacidad: a.capacidad,
  inscritos: a.inscritos,
  plan: `${a.plan.codigo} · ${a.plan.nombre}`,
})

/** Modal «Nueva actividad» / «Editar actividad» (patrón Formulario en modal). Se monta al abrirse para partir de cero. */
export function FormularioActividadModal({ actividadId, onCerrar, onGuardado }: FormularioActividadModalProps) {
  const editando = actividadId !== null
  const detalle = useActividad(actividadId, true)
  const opciones = useOpcionesActividades()

  const titulo = editando ? t.tituloEditar : t.tituloCrear

  return (
    <Modal open title={titulo} onClose={onCerrar} size="lg" description={t.descripcion} closeLabel={t.cerrar}>
      {editando && detalle.isPending ? (
        <div role="status" aria-label={actividadesText.loading} className="flex flex-col gap-3">
          <Skeleton className="h-24" />
          <Skeleton className="h-11" />
        </div>
      ) : (editando && detalle.isError) || opciones.isError ? (
        <ErrorState
          title={actividadesText.error.title}
          description={actividadesText.error.description}
          retryLabel={actividadesText.error.retry}
          onRetry={() => {
            void detalle.refetch()
            void opciones.refetch()
          }}
        />
      ) : opciones.isPending ? (
        <div role="status" aria-label={actividadesText.loading}>
          <Skeleton className="h-24" />
        </div>
      ) : (
        <Cuerpo actividad={editando ? (detalle.data ?? null) : null} opciones={opciones.data} actividadId={actividadId} onCerrar={onCerrar} onGuardado={onGuardado} />
      )}
    </Modal>
  )
}

interface CuerpoProps {
  actividad: ActividadDetalle | null
  opciones: OpcionesActividades
  actividadId: number | null
  onCerrar: () => void
  onGuardado: (actividad: ActividadDetalle, creada: boolean) => void
}

function Cuerpo({ actividad, opciones, actividadId, onCerrar, onGuardado }: CuerpoProps) {
  const editando = actividadId !== null
  const guardar = useGuardarActividad(actividadId)
  const [accion, setAccion] = useState<AccionDisponible | null>(null)
  const [lugar, setLugar] = useState(actividad?.lugar_enlace ?? '')
  const [departamento, setDepartamento] = useState<number | null>(actividad?.departamento?.id ?? null)
  const [errores, setErrores] = useState<ErroresActividad>({})
  const [errorDepartamento, setErrorDepartamento] = useState<string | undefined>()
  const [errorGeneral, setErrorGeneral] = useState<string | undefined>()

  const soloLectura = editando && actividad !== null && !actividad.puede_editar
  const tipo: TipoAccion | null = editando ? (actividad?.tipo ?? null) : (accion?.tipo ?? null)
  const esEnlace = tipo !== null && TIPOS_CON_ENLACE.includes(tipo)
  const heredada = editando ? (actividad ? heredadaDeActividad(actividad) : null) : accion ? heredadaDeAccion(accion) : null

  const enviar = (evento: FormEvent) => {
    evento.preventDefault()
    if (soloLectura || guardar.isPending) return
    const borrador: BorradorActividad = { plan_accion_id: accion?.accion_id ?? null, lugar_enlace: lugar, departamento_id: departamento }
    const encontrados = validarActividad(borrador, tipo, editando ? 'editar' : 'crear')
    setErrores(encontrados)
    setErrorDepartamento(undefined)
    setErrorGeneral(undefined)
    if (tieneErrores(encontrados)) return

    guardar.mutate(
      { ...(editando ? {} : { plan_accion_id: accion?.accion_id }), lugar_enlace: lugar.trim(), departamento_id: departamento },
      {
        onSuccess: (guardada) => onGuardado(guardada, !editando),
        onError: (error) => {
          const e = toApiError(error)
          const campo = (nombre: string) => e.fieldErrors[nombre]?.[0]
          const delServidor: ErroresActividad = {
            plan_accion_id: campo('plan_accion_id'),
            lugar_enlace: campo('lugar_enlace'),
          }
          setErrores(delServidor)
          setErrorDepartamento(campo('departamento_id'))
          setErrorGeneral(!delServidor.plan_accion_id && !delServidor.lugar_enlace && !campo('departamento_id') ? (e.message ?? t.errorGuardar) : undefined)
        },
      },
    )
  }

  const etiquetaLugar = esEnlace ? t.enlace : t.lugar

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-5">
      {errorGeneral && <Alert>{errorGeneral}</Alert>}
      {soloLectura && <Alert tone="neutral">{t.soloLectura}</Alert>}

      {!editando && <SelectorAccion planes={opciones.planes} valor={accion?.accion_id ?? null} onChange={setAccion} error={errores.plan_accion_id} />}
      {heredada && <DatosHeredados accion={heredada} />}

      {(editando || accion !== null) && (
        <div className="grid gap-4 sm:grid-cols-2 [&>*]:min-w-0">
          <TextField
            id="actividad-lugar"
            label={etiquetaLugar}
            value={lugar}
            onChange={(e) => setLugar(e.target.value)}
            error={errores.lugar_enlace}
            hint={esEnlace ? t.enlaceHint : t.lugarHint}
            disabled={soloLectura}
            maxLength={600}
            required
          />
          <SelectField
            id="actividad-departamento"
            label={`${t.departamento} (${t.departamentoOpcional.toLowerCase()})`}
            value={departamento === null ? '' : String(departamento)}
            onChange={(e) => setDepartamento(e.target.value === '' ? null : Number(e.target.value))}
            error={errorDepartamento}
            disabled={soloLectura}
            options={[
              { value: '', label: t.sinDepartamento },
              ...opciones.departamentos.map((d) => ({ value: String(d.id), label: d.nombre })),
              // Un departamento que dejó de estar activo se conserva como valor actual.
              ...(actividad?.departamento && !opciones.departamentos.some((d) => d.id === actividad.departamento?.id)
                ? [{ value: String(actividad.departamento.id), label: actividad.departamento.nombre }]
                : []),
            ]}
          />
        </div>
      )}

      <div className="flex flex-wrap justify-end gap-3">
        <Button type="button" variant="secondary" onClick={onCerrar} disabled={guardar.isPending}>
          {t.cancelar}
        </Button>
        {!soloLectura && (
          <Button type="submit" loading={guardar.isPending}>
            {editando ? t.guardar : t.crear}
          </Button>
        )}
      </div>
    </form>
  )
}
