import { Button } from '../../../../shared/ui/Button'
import { TextField } from '../../../../shared/ui/TextField'
import { nuevaClave, type HitoForm } from '../asistente'
import { pdiText } from '../text'

const t = pdiText.asistente.paso3

interface CalendarioDeHitosProps {
  hitos: HitoForm[]
  errores: Record<string, string>
  onChange: (hitos: HitoForm[]) => void
}

/** Calendario editable de hitos intermedios de revisión (fecha y descripción). */
export function CalendarioDeHitos({ hitos, errores, onChange }: CalendarioDeHitosProps) {
  const cambiar = (clave: string, cambios: Partial<HitoForm>) => onChange(hitos.map((h) => (h.clave === clave ? { ...h, ...cambios } : h)))

  return (
    <section aria-label={t.hitos} className="flex flex-col gap-3">
      <div>
        <h3 className="m-0 text-base font-bold">{t.hitos}</h3>
        <p className="m-0 text-[13px] text-muted">{t.hitosAyuda}</p>
      </div>
      {hitos.length === 0 && <p className="m-0 text-muted">{t.sinHitos}</p>}
      {hitos.map((h, i) => (
        <div key={h.clave} className="grid items-end gap-3 rounded-lg border border-border p-3 sm:grid-cols-[180px_1fr_auto]">
          <TextField id={`hito-${i}-fecha`} type="date" label={t.fechaHito} value={h.fecha} onChange={(e) => cambiar(h.clave, { fecha: e.target.value })} error={errores[`hitos.${i}.fecha`]} />
          <TextField id={`hito-${i}-descripcion`} label={t.descripcionHito} value={h.descripcion} maxLength={300} onChange={(e) => cambiar(h.clave, { descripcion: e.target.value })} error={errores[`hitos.${i}.descripcion`]} />
          <Button variant="secondary" onClick={() => onChange(hitos.filter((x) => x.clave !== h.clave))} aria-label={`${t.quitarHito} ${i + 1}`}>
            {t.quitarHito}
          </Button>
        </div>
      ))}
      <div>
        <Button variant="secondary" onClick={() => onChange([...hitos, { clave: nuevaClave(), fecha: '', descripcion: '' }])}>
          {t.agregarHito}
        </Button>
      </div>
    </section>
  )
}
