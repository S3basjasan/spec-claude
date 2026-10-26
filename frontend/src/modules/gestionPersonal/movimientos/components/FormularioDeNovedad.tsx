import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import type { z } from 'zod'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TextField } from '../../../../shared/ui/TextField'
import { TIPOS_DE_NOVEDAD, type ParametrosConservados, type Respaldo, type TipoDeNovedad } from '../api'
import { movimientosText } from '../text'
import { esquemaDeAusencia, esquemaDeNovedad, MOTIVO_MAXIMO } from '../validation'
import { SelectorDeRespaldos } from './SelectorDeRespaldos'

const text = movimientosText.novelty

export interface ValoresDeNovedad {
  tipo: TipoDeNovedad
  fecha_inicio: string
  fecha_fin: string
  motivo: string
}

interface Envio {
  tipo: TipoDeNovedad
  fecha_inicio: string
  fecha_fin: string
  motivo: string
  documento_ids: number[]
}

interface FormularioDeNovedadProps {
  /** `ausencia` informa un hecho ocurrido: sin tipo elegible ni anticipación. */
  modo: 'novedad' | 'ausencia'
  tipo: TipoDeNovedad
  onTipoChange?: (tipo: TipoDeNovedad) => void
  /** Requisitos de la política vigente mostrada; null si no hay política aplicable. */
  requisitos: ParametrosConservados | null
  /** La política no admite presentar (sin configurar, sin solicitudes nuevas o no disponible). */
  bloqueado: boolean
  respaldos: { items: Respaldo[] | undefined; cargando: boolean; fallo: boolean }
  pendiente: boolean
  /** Mensaje de la última presentación fallida; el formulario conserva lo escrito. */
  error?: string
  conflicto?: boolean
  onActualizar?: () => void
  /** Errores por campo devueltos por el servidor (clave = nombre del campo del cuerpo). */
  erroresDelServidor?: Record<string, string>
  etiquetaEnviar: string
  onSubmit: (valores: Envio) => void
  /** Contenido previo a los campos (selector de persona del Administrador). */
  children?: ReactNode
  /** Valores iniciales recuperables (por ejemplo, tras un conflicto). */
  inicial?: Partial<ValoresDeNovedad>
}

/**
 * Formulario de novedad (permiso, licencia, vacaciones) o de ausencia. Muestra solo los campos del tipo y los
 * requisitos de la política; conserva los valores ante errores de validación o conflictos y bloquea envíos pendientes.
 */
export function FormularioDeNovedad({
  modo,
  tipo,
  onTipoChange,
  requisitos,
  bloqueado,
  respaldos,
  pendiente,
  error,
  conflicto = false,
  onActualizar,
  erroresDelServidor = {},
  etiquetaEnviar,
  onSubmit,
  children,
  inicial,
}: FormularioDeNovedadProps) {
  const esquema = useMemo<z.ZodType<ValoresDeNovedad, ValoresDeNovedad>>(
    () => (modo === 'ausencia' ? esquemaDeAusencia(requisitos) : esquemaDeNovedad(requisitos)),
    [modo, requisitos],
  )
  const [respaldosElegidos, setRespaldosElegidos] = useState<number[]>([])
  const [errorDeRespaldo, setErrorDeRespaldo] = useState<string | undefined>()

  const {
    register,
    handleSubmit,
    control,
    setError,
    setValue,
    clearErrors,
    formState: { errors },
  } = useForm<ValoresDeNovedad>({
    resolver: zodResolver(esquema),
    defaultValues: { tipo, fecha_inicio: '', fecha_fin: '', motivo: '', ...inicial },
  })

  const motivoActual = useWatch({ control, name: 'motivo' }) ?? ''
  const documentoExigido = requisitos?.documento_obligatorio === true

  // Los errores por campo del servidor se asignan a su campo sin perder lo escrito.
  useEffect(() => {
    for (const campo of ['fecha_inicio', 'fecha_fin', 'motivo', 'tipo'] as const) {
      const mensaje = erroresDelServidor[campo]
      if (mensaje) setError(campo, { type: 'server', message: mensaje })
    }
  }, [erroresDelServidor, setError])

  const enviar = handleSubmit((valores) => {
    if (documentoExigido && respaldosElegidos.length === 0) {
      setErrorDeRespaldo(movimientosText.errors.supportRequired)
      return
    }
    setErrorDeRespaldo(undefined)
    onSubmit({ tipo: modo === 'ausencia' ? tipo : valores.tipo, fecha_inicio: valores.fecha_inicio, fecha_fin: valores.fecha_fin, motivo: valores.motivo.trim(), documento_ids: respaldosElegidos })
  })

  const textoDeAnticipacion =
    modo === 'novedad' && typeof requisitos?.anticipacion_dias === 'number'
      ? requisitos.anticipacion_dias === 0
        ? text.advanceZero
        : text.advance(requisitos.anticipacion_dias)
      : undefined

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-5">
      {modo === 'ausencia' && <Alert tone="neutral">{movimientosText.absence.banner}</Alert>}
      {conflicto && (
        <Alert>
          <p className="font-semibold">{movimientosText.errors.conflictTitle}</p>
          <p>{movimientosText.errors.conflict}</p>
          {onActualizar && (
            <Button variant="secondary" onClick={onActualizar} className="mt-2">
              {movimientosText.refresh}
            </Button>
          )}
        </Alert>
      )}
      {error && !conflicto && <Alert>{error}</Alert>}

      {children}

      {modo === 'novedad' && (
        <SelectField
          id="novedad-tipo"
          label={text.type}
          value={tipo}
          onChange={(e) => {
            const elegido = e.target.value as TipoDeNovedad
            setValue('tipo', elegido)
            onTipoChange?.(elegido)
          }}
          options={TIPOS_DE_NOVEDAD.map((t) => ({ value: t, label: movimientosText.tipos[t] }))}
        />
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <TextField
          id="novedad-inicio"
          type="date"
          label={modo === 'ausencia' ? movimientosText.absence.start : text.start}
          hint={textoDeAnticipacion}
          error={errors.fecha_inicio?.message}
          {...register('fecha_inicio', { onChange: () => clearErrors('fecha_inicio') })}
        />
        <TextField
          id="novedad-fin"
          type="date"
          label={modo === 'ausencia' ? movimientosText.absence.end : text.end}
          error={errors.fecha_fin?.message}
          {...register('fecha_fin', { onChange: () => clearErrors('fecha_fin') })}
        />
      </div>

      <TextareaField
        id="novedad-motivo"
        label={modo === 'ausencia' ? movimientosText.absence.reason : text.reason}
        hint={requisitos?.justificacion_obligatoria ? text.reasonRequired : text.reasonOptional}
        maxLength={MOTIVO_MAXIMO}
        currentLength={motivoActual.length}
        error={errors.motivo?.message}
        {...register('motivo', { onChange: () => clearErrors('motivo') })}
      />

      <SelectorDeRespaldos
        respaldos={respaldos.items}
        cargando={respaldos.cargando}
        fallo={respaldos.fallo}
        elegidos={respaldosElegidos}
        onChange={(ids) => {
          setRespaldosElegidos(ids)
          setErrorDeRespaldo(undefined)
        }}
        exigido={documentoExigido}
        error={errorDeRespaldo ?? erroresDelServidor.documento_ids}
      />

      {modo === 'novedad' && <p className="text-sm text-muted">{text.versionWarning}</p>}

      <div className="flex justify-end">
        <Button type="submit" loading={pendiente} disabled={bloqueado}>
          {etiquetaEnviar}
        </Button>
      </div>
    </form>
  )
}
