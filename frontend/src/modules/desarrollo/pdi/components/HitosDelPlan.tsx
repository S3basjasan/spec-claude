import { useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import type { EstadoHito, Hito } from '../api'
import { fechaCorta } from '../fecha'
import { pdiText } from '../text'

const t = pdiText.detalle
const OPCIONES = (['Pendiente', 'Realizado'] as const).map((e) => ({ value: e, label: pdiText.estadosHito[e] }))

interface HitosDelPlanProps {
  hitos: Hito[]
  /** El administrador puede registrar avances en un plan Acordado. */
  puedeRegistrarAvance: boolean
  busy?: boolean
  onGuardar?: (hitoId: number, estado: EstadoHito, observaciones: string | null) => void
}

function FilaDeHito({ hito, puedeRegistrarAvance, busy, onGuardar }: { hito: Hito; puedeRegistrarAvance: boolean; busy: boolean; onGuardar?: HitosDelPlanProps['onGuardar'] }) {
  const [estado, setEstado] = useState<EstadoHito>(hito.estado)
  const [observaciones, setObservaciones] = useState(hito.observaciones ?? '')

  return (
    <li className="flex flex-col gap-2 rounded-lg border border-border p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="m-0 font-semibold">
          {fechaCorta(hito.fecha)} · {hito.descripcion}
        </p>
        {hito.vencido && <span className="rounded-full bg-dangersoft px-2.5 py-[3px] text-xs font-semibold text-danger">{pdiText.vencido}</span>}
        {!puedeRegistrarAvance && <span className="text-[13px] text-muted">{pdiText.estadosHito[hito.estado]}</span>}
      </div>
      {!puedeRegistrarAvance && hito.observaciones !== null && hito.observaciones !== '' && <p className="m-0 text-[13px] text-muted">{hito.observaciones}</p>}
      {puedeRegistrarAvance && (
        <div className="grid items-end gap-3 sm:grid-cols-[180px_1fr_auto]">
          <SelectField id={`hito-${hito.id}-estado`} label={t.estadoHito} options={OPCIONES} value={estado} onChange={(e) => setEstado(e.target.value as EstadoHito)} />
          <TextField id={`hito-${hito.id}-obs`} label={t.observaciones} value={observaciones} maxLength={500} onChange={(e) => setObservaciones(e.target.value)} />
          <Button variant="secondary" loading={busy} onClick={() => onGuardar?.(hito.id, estado, observaciones.trim() === '' ? null : observaciones.trim())}>
            {t.guardarHito}
          </Button>
        </div>
      )}
    </li>
  )
}

/** Lista de hitos de revisión; el administrador marca avances en un plan Acordado. */
export function HitosDelPlan({ hitos, puedeRegistrarAvance, busy = false, onGuardar }: HitosDelPlanProps) {
  if (hitos.length === 0) return <p className="m-0 text-muted">{t.sinHitos}</p>

  return (
    <ul className="m-0 flex list-none flex-col gap-2 p-0">
      {hitos.map((h) => (
        <FilaDeHito key={`${h.id}-${h.estado}-${h.observaciones ?? ''}`} hito={h} puedeRegistrarAvance={puedeRegistrarAvance} busy={busy} onGuardar={onGuardar} />
      ))}
    </ul>
  )
}
