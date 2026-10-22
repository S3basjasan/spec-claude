import type { Nivel } from '../api'
import { rutasText } from '../text'

const t = rutasText.cantera

interface CanteraPorNivelProps {
  niveles: Nivel[]
  /** Participantes activos sin nivel actual definido: se informan aparte. */
  sinNivel: number
}

/** Cantera de talento: personas con inscripción Activa en cada nivel actual (RutasAdmin.dc.html). */
export function CanteraPorNivel({ niveles, sinNivel }: CanteraPorNivelProps) {
  const cuentas = niveles.map((n) => n.inscritos_activos ?? 0)
  const maximo = Math.max(1, ...cuentas)
  const vacios = niveles.filter((n, i) => n.orden > 1 && cuentas[i] === 0).map((n) => n.orden)
  const aria = t.aria(niveles.map((n, i) => `nivel ${n.orden}, ${cuentas[i]}`).join('; '))

  return (
    <section aria-labelledby="cantera-titulo" className="flex flex-col gap-3 rounded-xl border border-border bg-surface px-5 py-[18px]">
      <div>
        <h2 id="cantera-titulo" className="m-0 text-lg">
          {t.title}
        </h2>
        <p className="mt-0.5 text-[13px] text-muted">{t.descripcion}</p>
      </div>
      <div role="img" aria-label={aria} className="flex flex-col gap-2.5">
        {niveles.map((n, i) => (
          <div key={n.id} className="grid grid-cols-[1fr_100px_30px] items-center gap-2.5 text-[13px]">
            <span>
              <b>{t.nivel(n.orden)}</b> · {n.nombre}
            </span>
            <div className="h-3 rounded-[3px] bg-bg">
              <div className="h-full rounded-[3px] bg-info" style={{ width: `${(cuentas[i] / maximo) * 100}%` }} />
            </div>
            <span className="text-right font-bold tabular-nums">{cuentas[i]}</span>
          </div>
        ))}
      </div>
      {sinNivel > 0 && <p className="m-0 text-[13px] text-muted">{t.sinNivel(sinNivel)}</p>}
      {vacios.length > 0 && <p className="m-0 text-[13px] text-warn">{t.sinCantera(vacios)}</p>}
    </section>
  )
}
