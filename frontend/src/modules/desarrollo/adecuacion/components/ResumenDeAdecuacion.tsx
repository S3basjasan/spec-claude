import type { Diagnostico, Preparacion } from '../api'
import { adecuacionText } from '../text'
import { PreparacionBadge, TipoCambioBadge } from './Etiquetas'

const t = adecuacionText.resumen

interface Props {
  diagnostico: Diagnostico
  /** Preparación a mostrar (la calculada en pantalla); `null` mientras falten tiempos de cierre. */
  preparacion: Preparacion | null
}

function Metrica({ etiqueta, valor }: { etiqueta: string; valor: string }) {
  return (
    <div>
      <div className="text-[13px] text-muted">{etiqueta}</div>
      <div className="text-[26px] font-bold tabular-nums">{valor}</div>
    </div>
  )
}

/** Resumen del diagnóstico: tipo de cambio, puesto actual → objetivo, adecuación a cada puesto y nivel de preparación. */
export function ResumenDeAdecuacion({ diagnostico: d, preparacion }: Props) {
  const porcentaje = (valor: number | null) => (valor === null ? '—' : `${Math.round(valor)} %`)

  return (
    <section
      aria-label={t.etiqueta}
      className="flex flex-wrap items-center justify-between gap-x-8 gap-y-4 rounded-xl border border-border bg-surface px-5 py-[18px]"
    >
      <div className="min-w-0">
        <div className="text-[13px] text-muted">
          <TipoCambioBadge valor={d.tipo_cambio} />
        </div>
        <div className="mt-1 text-lg font-bold">
          {d.puesto_actual?.nombre ?? t.sinPuestoActual} → {d.puesto_objetivo.nombre}
        </div>
        <div className="mt-0.5 text-[13px] text-muted">{t.area(d.colaborador.nombre, d.colaborador.departamento, d.experiencia_de_la_persona_anios)}</div>
      </div>
      <div className="flex flex-wrap items-center gap-6">
        <Metrica etiqueta={t.actual} valor={porcentaje(d.cumplimiento_actual)} />
        <Metrica etiqueta={t.objetivo} valor={porcentaje(d.cumplimiento_objetivo)} />
        <div>
          <div className="mb-1 text-[13px] text-muted">{t.preparacion}</div>
          {preparacion === null ? <span className="text-[13px] text-muted">{adecuacionText.confirmacion.preparacionPendiente}</span> : <PreparacionBadge valor={preparacion} />}
        </div>
      </div>
    </section>
  )
}
