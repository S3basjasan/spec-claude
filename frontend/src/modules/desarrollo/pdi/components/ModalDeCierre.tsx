import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { MAX_COMENTARIO, RESULTADOS_COMPROMISO, type Pdi, type ResultadoCompromiso } from '../api'
import { pdiText } from '../text'

const t = pdiText.modales.cierre
const OPCIONES = [{ value: '', label: t.elegir }, ...RESULTADOS_COMPROMISO.map((r) => ({ value: r, label: pdiText.resultadosCompromiso[r] }))]

interface ModalDeCierreProps {
  open: boolean
  pdi: Pdi
  busy?: boolean
  error?: string | null
  onConfirm: (resultados: { compromiso_id: number; resultado: ResultadoCompromiso }[], conclusion: string | null) => void
  onClose: () => void
}

/** Modal de cierre: resultado de cada compromiso y conclusión opcional; avisa si quedan acciones abiertas. */
export function ModalDeCierre({ open, pdi, busy = false, error = null, onConfirm, onClose }: ModalDeCierreProps) {
  const [resultados, setResultados] = useState<Record<number, ResultadoCompromiso | ''>>({})
  const [conclusion, setConclusion] = useState('')
  const [falta, setFalta] = useState(false)

  const abiertas = pdi.compromisos.flatMap((c) => c.acciones).filter((a) => a.estado === 'Pendiente' || a.estado === 'EnCurso').length

  const enviar = () => {
    const completos = pdi.compromisos.every((c) => (resultados[c.id] ?? '') !== '')
    if (!completos) {
      setFalta(true)
      return
    }
    onConfirm(
      pdi.compromisos.map((c) => ({ compromiso_id: c.id, resultado: resultados[c.id] as ResultadoCompromiso })),
      conclusion.trim() === '' ? null : conclusion.trim(),
    )
  }

  return (
    <Modal
      open={open}
      title={t.titulo}
      description={t.descripcion}
      size="lg"
      onClose={onClose}
      busy={busy}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {pdiText.modales.cancelar}
          </Button>
          <Button loading={busy} onClick={enviar} disabled={abiertas > 0}>
            {t.confirmar}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <p className="m-0 text-muted">{t.descripcion}</p>
        {abiertas > 0 && <Alert tone="neutral">{t.abiertas(abiertas)}</Alert>}
        {error !== null && <Alert>{error}</Alert>}
        {pdi.compromisos.map((c, i) => (
          <SelectField
            key={c.id}
            id={`cierre-${c.id}`}
            label={`${t.resultadoDe(i + 1)}: ${c.elemento?.nombre ?? c.descripcion ?? pdiText.dimensiones[c.dimension]}`}
            options={OPCIONES}
            value={resultados[c.id] ?? ''}
            onChange={(e) => {
              setResultados((r) => ({ ...r, [c.id]: e.target.value as ResultadoCompromiso | '' }))
              setFalta(false)
            }}
            error={falta && (resultados[c.id] ?? '') === '' ? t.falta : undefined}
          />
        ))}
        <TextareaField id="cierre-conclusion" label={t.conclusion} value={conclusion} maxLength={MAX_COMENTARIO} currentLength={conclusion.length} onChange={(e) => setConclusion(e.target.value)} />
      </div>
    </Modal>
  )
}
