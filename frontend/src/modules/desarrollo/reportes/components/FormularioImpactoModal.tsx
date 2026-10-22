import { useState, type FormEvent } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { TextField } from '../../../../shared/ui/TextField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { MAX_INDICADOR, MAX_JUSTIFICACION, MAX_UNIDAD, type FilaImpacto } from '../api'
import { useRegistrarImpacto } from '../hooks/useMutacionesReportes'
import { dolares, fecha, porcentaje } from '../formato'
import { reportesText } from '../text'
import { aNumero, calcularRoi, datosDeImpacto, tieneErrores, validarImpacto, type BorradorImpacto, type CampoImpacto, type ErroresDe } from '../validation'

const t = reportesText.formularioImpacto

interface FormularioImpactoModalProps {
  fila: FilaImpacto
  onCerrar: () => void
  onGuardado: () => void
}

const numeroATexto = (n: number): string => String(n).replace('.', ',')

function borradorInicial(fila: FilaImpacto): BorradorImpacto {
  const i = fila.impacto
  return {
    indicador: i?.indicador ?? '',
    unidad: i?.unidad ?? '',
    valor_antes: i ? numeroATexto(i.valor_antes) : '',
    valor_despues: i ? numeroATexto(i.valor_despues) : '',
    beneficio_estimado: i ? numeroATexto(i.beneficio_estimado) : '',
    justificacion: i?.justificacion ?? '',
  }
}

/** Modal «Registrar impacto» / «Editar impacto»: datos antes y después, beneficio estimado (USD) y el ROI calculado de la acción. */
export function FormularioImpactoModal({ fila, onCerrar, onGuardado }: FormularioImpactoModalProps) {
  const guardar = useRegistrarImpacto(fila.plan_accion_id)
  const [borrador, setBorrador] = useState<BorradorImpacto>(() => borradorInicial(fila))
  const [errores, setErrores] = useState<ErroresDe<CampoImpacto>>({})
  const [errorGeneral, setErrorGeneral] = useState<string | undefined>()

  const campo = (clave: CampoImpacto) => (valor: string) => {
    setBorrador((b) => ({ ...b, [clave]: valor }))
    setErrores((e) => ({ ...e, [clave]: undefined }))
  }

  const beneficio = aNumero(borrador.beneficio_estimado)
  const roi = calcularRoi(beneficio !== null && beneficio >= 0 ? beneficio : null, fila.costo)
  const auditoria = fila.impacto

  const enviar = (evento: FormEvent) => {
    evento.preventDefault()
    if (guardar.isPending) return
    const encontrados = validarImpacto(borrador)
    setErrores(encontrados)
    setErrorGeneral(undefined)
    const datos = datosDeImpacto(borrador)
    if (tieneErrores(encontrados) || datos === null) return

    guardar.mutate(
      { ...datos, indicador: datos.indicador.trim(), unidad: datos.unidad.trim(), justificacion: datos.justificacion.trim() },
      {
        onSuccess: onGuardado,
        onError: (error) => {
          const api = toApiError(error)
          const porCampo: ErroresDe<CampoImpacto> = {}
          for (const [nombre, mensajes] of Object.entries(api.fieldErrors)) {
            const clave = nombre as CampoImpacto
            if (clave in borrador && mensajes[0] !== undefined) porCampo[clave] = mensajes[0]
          }
          setErrores(porCampo)
          setErrorGeneral(tieneErrores(porCampo) ? undefined : reportesText.mensajeDeError(api.code, api.message ?? t.errorGeneral))
        },
      },
    )
  }

  return (
    <Modal
      open
      title={t.titulo(fila.accion_nombre)}
      onClose={onCerrar}
      busy={guardar.isPending}
      size="lg"
      description={t.descripcion}
      closeLabel={t.cerrar}
      footer={
        <>
          <Button variant="secondary" onClick={onCerrar} disabled={guardar.isPending}>
            {t.cancelar}
          </Button>
          <Button type="submit" form="form-impacto" loading={guardar.isPending}>
            {t.guardar}
          </Button>
        </>
      }
    >
      <form id="form-impacto" noValidate onSubmit={enviar} className="flex flex-col gap-4">
        {errorGeneral && <Alert>{errorGeneral}</Alert>}
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField id="imp-indicador" label={t.campos.indicador} hint={t.hints.indicador} maxLength={MAX_INDICADOR} value={borrador.indicador} error={errores.indicador} onChange={(e) => campo('indicador')(e.target.value)} />
          <TextField id="imp-unidad" label={t.campos.unidad} hint={t.hints.unidad} maxLength={MAX_UNIDAD} value={borrador.unidad} error={errores.unidad} onChange={(e) => campo('unidad')(e.target.value)} />
          <TextField id="imp-antes" label={t.campos.valorAntes} inputMode="decimal" value={borrador.valor_antes} error={errores.valor_antes} onChange={(e) => campo('valor_antes')(e.target.value)} />
          <TextField id="imp-despues" label={t.campos.valorDespues} inputMode="decimal" value={borrador.valor_despues} error={errores.valor_despues} onChange={(e) => campo('valor_despues')(e.target.value)} />
          <TextField id="imp-beneficio" label={t.campos.beneficio} inputMode="decimal" value={borrador.beneficio_estimado} error={errores.beneficio_estimado} onChange={(e) => campo('beneficio_estimado')(e.target.value)} />
        </div>
        <TextareaField
          id="imp-justificacion"
          label={t.campos.justificacion}
          hint={t.hints.justificacion}
          rows={3}
          maxLength={MAX_JUSTIFICACION}
          currentLength={borrador.justificacion.length}
          value={borrador.justificacion}
          error={errores.justificacion}
          onChange={(e) => campo('justificacion')(e.target.value)}
        />

        <div role="status" aria-live="polite" className="rounded-lg border border-border bg-soft px-3.5 py-3 text-sm">
          <div className="font-semibold">{t.roi}</div>
          <div className="text-2xl font-bold">{fila.costo === 0 || fila.costo === null ? reportesText.impactos.roiNoAplica : roi === null ? reportesText.sinDato : porcentaje(roi)}</div>
          <div className="text-[13px] text-muted">
            {t.costoDeLaAccion(dolares(fila.costo, 2))}
            {roi !== null && beneficio !== null && fila.costo !== null ? ` · ${t.roiDetalle(dolares(beneficio, 2), dolares(fila.costo, 2))}` : ''}
          </div>
        </div>

        {auditoria && (
          <p className="m-0 text-[13px] text-muted">
            {t.auditoria(`${auditoria.creado_por ?? reportesText.sinDato} (${fecha(auditoria.creado_en)})`, `${auditoria.modificado_por ?? reportesText.sinDato} (${fecha(auditoria.modificado_en)})`)}
          </p>
        )}
      </form>
    </Modal>
  )
}
