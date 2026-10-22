import { useState, type FormEvent } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TextField } from '../../../../shared/ui/TextField'
import { MAX_DESCRIPCION_RUTA, MAX_NOMBRE_RUTA, TIPOS_RUTA, type OpcionesRutas, type RutaDetalle, type TipoRuta } from '../api'
import { useActualizarRuta, useCrearRuta } from '../hooks/useMutacionesRutas'
import { useOpcionesRutas } from '../hooks/useRutas'
import { cuerpoDeRuta, nivelesEnviables } from '../niveles'
import { rutasText } from '../text'
import { tieneErrores, validarRuta, type CampoRuta, type ErroresDe } from '../validation'

const t = rutasText.formulario

interface FormularioRutaModalProps {
  /** Ruta a editar; `null` para crear una nueva. */
  ruta: RutaDetalle | null
  onCerrar: () => void
  onGuardado: (ruta: RutaDetalle, creada: boolean) => void
  /** Se invoca cuando otro administrador cambió la ruta (409): el editor la vuelve a leer. */
  onConflicto?: () => void
}

/** Modal «Nueva ruta de carrera» / «Editar ruta» (patrón Formulario en modal, RutasAdmin.dc.html). Se monta al abrirse. */
export function FormularioRutaModal({ ruta, onCerrar, onGuardado, onConflicto }: FormularioRutaModalProps) {
  const opciones = useOpcionesRutas()

  return (
    <Modal open title={ruta === null ? t.tituloCrear : t.tituloEditar} onClose={onCerrar} size="lg" description={t.descripcion} closeLabel={rutasText.cerrar}>
      {opciones.isError ? (
        <ErrorState title={rutasText.error.title} description={rutasText.error.description} retryLabel={rutasText.error.retry} onRetry={() => void opciones.refetch()} />
      ) : opciones.isPending ? (
        <div role="status" aria-label={rutasText.loading} className="flex flex-col gap-3">
          <Skeleton className="h-11" />
          <Skeleton className="h-11" />
        </div>
      ) : (
        <Cuerpo ruta={ruta} opciones={opciones.data} onCerrar={onCerrar} onGuardado={onGuardado} onConflicto={onConflicto} />
      )}
    </Modal>
  )
}

interface CuerpoProps extends Omit<FormularioRutaModalProps, never> {
  opciones: OpcionesRutas
}

function Cuerpo({ ruta, opciones, onCerrar, onGuardado, onConflicto }: CuerpoProps) {
  const editando = ruta !== null
  const crear = useCrearRuta()
  const actualizar = useActualizarRuta(ruta?.id ?? 0)
  const pendiente = crear.isPending || actualizar.isPending

  const [nombre, setNombre] = useState(ruta?.nombre ?? '')
  const [tipo, setTipo] = useState<TipoRuta | ''>(ruta?.tipo ?? 'Especialista')
  const [departamento, setDepartamento] = useState<number | null>(ruta?.departamento_id ?? null)
  const [descripcion, setDescripcion] = useState(ruta?.descripcion ?? '')
  const [errores, setErrores] = useState<ErroresDe<CampoRuta>>({})
  const [errorGeneral, setErrorGeneral] = useState<string | undefined>()
  const [conflicto, setConflicto] = useState(false)

  const admiteDepartamento = tipo === '' || (opciones.tipos.find((o) => o.valor === tipo)?.admite_departamento ?? true)

  const cambiarTipo = (nuevo: TipoRuta | '') => {
    setTipo(nuevo)
    if (nuevo === 'Interareas') setDepartamento(null)
  }

  const enviar = (evento: FormEvent) => {
    evento.preventDefault()
    if (pendiente) return
    const encontrados = validarRuta({ nombre, tipo, descripcion, departamento_id: departamento })
    setErrores(encontrados)
    setErrorGeneral(undefined)
    setConflicto(false)
    if (tieneErrores(encontrados) || tipo === '') return

    const datos = { nombre: nombre.trim(), tipo, descripcion: descripcion.trim() === '' ? null : descripcion.trim(), departamento_id: admiteDepartamento ? departamento : null }
    const alError = (error: unknown) => {
      const e = toApiError(error)
      if (e.status === 409) {
        setConflicto(true)
        return
      }
      const campo = (clave: string) => e.fieldErrors[clave]?.[0]
      const delServidor: ErroresDe<CampoRuta> = { nombre: campo('nombre'), tipo: campo('tipo'), descripcion: campo('descripcion'), departamento_id: campo('departamento_id') }
      setErrores(delServidor)
      setErrorGeneral(tieneErrores(delServidor) ? undefined : (e.message ?? t.errorGuardar))
    }

    if (ruta === null) {
      crear.mutate(datos, { onSuccess: (creada) => onGuardado(creada, true), onError: alError })
    } else {
      actualizar.mutate(cuerpoDeRuta(ruta, nivelesEnviables(ruta), datos), { onSuccess: (guardada) => onGuardado(guardada, false), onError: alError })
    }
  }

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
      {errorGeneral && <Alert>{errorGeneral}</Alert>}
      {conflicto && (
        <Alert>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <span>{rutasText.conflicto.mensaje}</span>
            <Button variant="secondary" onClick={() => (onConflicto ? onConflicto() : onCerrar())}>
              {rutasText.conflicto.recargar}
            </Button>
          </div>
        </Alert>
      )}

      <TextField id="ruta-nombre" label={t.nombre} value={nombre} onChange={(e) => setNombre(e.target.value)} error={errores.nombre} maxLength={MAX_NOMBRE_RUTA + 20} required />

      <div className="grid gap-4 sm:grid-cols-2 [&>*]:min-w-0">
        <div className="flex flex-col gap-1.5">
          <SelectField
            id="ruta-tipo"
            label={t.tipo}
            value={tipo}
            onChange={(e) => cambiarTipo(e.target.value as TipoRuta | '')}
            error={errores.tipo}
            options={TIPOS_RUTA.map((valor) => ({ value: valor, label: rutasText.tipos[valor] }))}
          />
          {tipo !== '' && <span className="text-xs leading-snug text-muted">{rutasText.ayudaTipo[tipo]}</span>}
        </div>
        <SelectField
          id="ruta-departamento"
          label={t.departamento}
          value={departamento === null ? '' : String(departamento)}
          onChange={(e) => setDepartamento(e.target.value === '' ? null : Number(e.target.value))}
          error={errores.departamento_id}
          disabled={!admiteDepartamento}
          options={[
            { value: '', label: admiteDepartamento ? t.sinDepartamento : t.departamentoInterareas },
            ...opciones.departamentos.map((d) => ({ value: String(d.id), label: d.nombre })),
          ]}
        />
      </div>

      <TextareaField
        id="ruta-descripcion"
        label={t.descripcionCampo}
        rows={3}
        value={descripcion}
        onChange={(e) => setDescripcion(e.target.value)}
        error={errores.descripcion}
        currentLength={descripcion.length}
        maxLength={MAX_DESCRIPCION_RUTA}
      />

      {!editando && <p className="m-0 text-[13px] text-muted">{t.notaCrear}</p>}

      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
        <Button variant="secondary" className="border-transparent" onClick={onCerrar} disabled={pendiente}>
          {rutasText.cancelar}
        </Button>
        <Button type="submit" loading={pendiente}>
          {editando ? t.guardarCambios : t.crear}
        </Button>
      </div>
    </form>
  )
}
