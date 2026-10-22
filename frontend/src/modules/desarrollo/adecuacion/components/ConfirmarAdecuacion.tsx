import { useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { MAX_OBSERVACIONES, type Diagnostico, type Preparacion } from '../api'
import { adecuacionText } from '../text'
import { validarObservaciones } from '../validation'
import { AccionesDeAdecuacion } from './AccionesDeAdecuacion'
import { PreparacionBadge } from './Etiquetas'

const t = adecuacionText.confirmacion

interface Props {
  diagnostico: Diagnostico
  preparacion: Preparacion | null
  faltanTiempos: boolean
  observaciones: string
  onObservaciones: (texto: string) => void
  guardando: boolean
  errorGeneral?: string
  errorObservaciones?: string
  onRegistrar: () => void
}

/** Paso «Confirmación»: preparación calculada, observaciones y registro (con confirmación) de la evaluación. */
export function ConfirmarAdecuacion({
  diagnostico: d,
  preparacion,
  faltanTiempos,
  observaciones,
  onObservaciones,
  guardando,
  errorGeneral,
  errorObservaciones,
  onRegistrar,
}: Props) {
  const [abierto, setAbierto] = useState(false)
  const errorLocal = validarObservaciones(observaciones)
  const bloqueado = faltanTiempos || errorLocal !== undefined || preparacion === null

  return (
    <section aria-labelledby="confirmacion-titulo" className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-5">
      <h2 id="confirmacion-titulo" className="m-0 text-lg font-semibold">
        {t.titulo}
      </h2>

      <div className="flex flex-wrap items-center gap-3">
        <span className="text-[13px] text-muted">{t.preparacion}</span>
        {preparacion === null ? <span className="text-[13px] text-muted">{t.preparacionPendiente}</span> : <PreparacionBadge valor={preparacion} />}
      </div>

      <TextareaField
        id="adecuacion-observaciones"
        label={t.observaciones}
        hint={t.observacionesHint}
        value={observaciones}
        maxLength={MAX_OBSERVACIONES}
        currentLength={observaciones.length}
        error={errorObservaciones ?? errorLocal}
        onChange={(e) => onObservaciones(e.target.value)}
      />

      {errorGeneral !== undefined && (
        <p role="alert" className="m-0 text-sm text-danger">
          {errorGeneral}
        </p>
      )}

      <div className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center gap-3">
          <Button type="button" loading={guardando} disabled={bloqueado || guardando} onClick={() => setAbierto(true)} aria-describedby={faltanTiempos ? 'confirmacion-faltan' : undefined}>
            {guardando ? t.registrando : t.registrar}
          </Button>
          {faltanTiempos && (
            <span id="confirmacion-faltan" className="text-[13px] text-muted">
              {t.faltanTiempos}
            </span>
          )}
        </div>
        <AccionesDeAdecuacion conBrechas={d.resultados.some((r) => r.estado === 'ConBrecha')} />
      </div>

      <ConfirmDialog
        open={abierto}
        tone="primary"
        title={t.dialogo.titulo}
        description={t.dialogo.descripcion(d.colaborador.nombre, d.puesto_objetivo.nombre, preparacion === null ? '—' : adecuacionText.preparacion[preparacion])}
        confirmLabel={t.dialogo.confirmar}
        cancelLabel={t.dialogo.cancelar}
        busy={guardando}
        onCancel={() => setAbierto(false)}
        onConfirm={() => {
          setAbierto(false)
          onRegistrar()
        }}
      />
    </section>
  )
}
