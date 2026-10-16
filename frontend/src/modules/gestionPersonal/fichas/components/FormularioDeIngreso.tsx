import type { FormEvent, ReactNode } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconList } from '../../../../shared/ui/icons'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextField } from '../../../../shared/ui/TextField'
import type { OpcionesDeIngreso } from '../api'
import { fichasText } from '../text'
import { SelectorDePosicion } from './SelectorDePosicion'

const text = fichasText.entry

export interface ValoresDeIngreso {
  fecha: string
  contrato: string
  posicionId: number | null
  busqueda: string
}

interface FormularioDeIngresoProps {
  valores: ValoresDeIngreso
  onCambio: (cambios: Partial<ValoresDeIngreso>) => void
  opciones: OpcionesDeIngreso | undefined
  cargandoOpciones: boolean
  errorDeOpciones: boolean
  onReintentarOpciones: () => void
  errores: Record<string, string>
  /** Avisos generales (rechazo con motivo, selección descartada…) sobre los campos. */
  avisos?: ReactNode
  guardando: boolean
  onConfirmar: () => void
  onCancelar: () => void
}

/** Formulario de ingreso: fecha, contrato y posición (patrón Formulario de design/DESIGN.md §5). */
export function FormularioDeIngreso({
  valores,
  onCambio,
  opciones,
  cargandoOpciones,
  errorDeOpciones,
  onReintentarOpciones,
  errores,
  avisos,
  guardando,
  onConfirmar,
  onCancelar,
}: FormularioDeIngresoProps) {
  const enviar = (evento: FormEvent) => {
    evento.preventDefault()
    onConfirmar()
  }

  const hayErrores = Object.keys(errores).length > 0
  const posiciones = opciones?.posiciones ?? []

  return (
    <form noValidate onSubmit={enviar} className="flex flex-col gap-5">
      {hayErrores && (
        <div role="alert" className="rounded-lg bg-dangersoft px-3.5 py-3 text-sm text-danger">
          {fichasText.form.errorsAlert}
        </div>
      )}
      {avisos}
      {opciones?.conflicto_ficha && (
        <Alert>{text.fichaConflict(opciones.conflicto_ficha.desde, opciones.conflicto_ficha.hasta)}</Alert>
      )}

      <fieldset className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
        <legend className="px-1 text-base font-bold">{text.title}</legend>

        <TextField
          id="fecha_ingreso"
          type="date"
          label={text.date}
          hint={text.dateHint}
          value={valores.fecha}
          onChange={(evento) => onCambio({ fecha: evento.target.value })}
          error={errores.fecha_ingreso}
          required
        />

        {opciones?.ingreso_programado && (
          <Alert tone="neutral">
            <p className="font-semibold">{text.scheduled}</p>
            <p>{text.scheduledNotice}</p>
          </Alert>
        )}

        <SelectField
          id="tipo_contrato"
          label={text.contract}
          value={valores.contrato}
          onChange={(evento) => onCambio({ contrato: evento.target.value })}
          options={[
            { value: '', label: text.contractPlaceholder },
            ...(opciones?.tipos_contrato ?? []).map((tipo) => ({ value: tipo.clave, label: tipo.etiqueta })),
          ]}
          error={errores.tipo_contrato}
        />

        {cargandoOpciones && (
          <div role="status" aria-label={text.loadingOptions} className="flex flex-col gap-2">
            <Skeleton className="h-10" />
            <Skeleton className="h-10" />
          </div>
        )}

        {!cargandoOpciones && errorDeOpciones && (
          <div className="rounded-lg border border-border">
            <ErrorState
              title={text.optionsError}
              description={text.optionsErrorDescription}
              retryLabel={fichasText.retry}
              onRetry={onReintentarOpciones}
            />
          </div>
        )}

        {!cargandoOpciones && !errorDeOpciones && opciones && posiciones.length === 0 && valores.busqueda === '' && (
          <div className="rounded-lg border border-border">
            <EmptyState icon={<IconList size={24} />} title={text.noPositions} description={text.noPositionsDescription} />
          </div>
        )}

        {!errorDeOpciones && opciones && (posiciones.length > 0 || valores.busqueda !== '') && (
          <SelectorDePosicion
            posiciones={posiciones}
            total={opciones.total}
            valor={valores.posicionId}
            onChange={(id) => onCambio({ posicionId: id })}
            busqueda={valores.busqueda}
            onBusquedaChange={(texto) => onCambio({ busqueda: texto })}
            error={errores.posicion_id}
          />
        )}
      </fieldset>

      <div className="flex flex-wrap gap-3">
        <Button type="submit" loading={guardando}>
          {guardando ? text.confirming : text.confirm}
        </Button>
        <Button variant="secondary" onClick={onCancelar} disabled={guardando}>
          {text.cancel}
        </Button>
      </div>
    </form>
  )
}
