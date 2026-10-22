import { IconLock } from '../../../../shared/ui/icons'
import type { ReglaFija } from '../api'
import { politicasText } from '../text'

const text = politicasText.fixedRules

/** Reglas fijas del tipo, solo lectura: las define el sistema y ninguna política puede cambiarlas. */
export function CondicionesFijas({ reglas }: { reglas: ReglaFija[] }) {
  if (reglas.length === 0) return null

  return (
    <section aria-labelledby="reglas-fijas-titulo" className="rounded-xl border border-border bg-soft p-4">
      <h2 id="reglas-fijas-titulo" className="flex items-center gap-2 text-base font-bold">
        <IconLock size={18} />
        {text.title}
      </h2>
      <p className="mt-1 text-sm text-muted">{text.note}</p>
      <ul className="mt-3 space-y-1.5 text-[15px]">
        {reglas.map((regla) => (
          <li key={regla.clave}>{regla.texto}</li>
        ))}
      </ul>
    </section>
  )
}
