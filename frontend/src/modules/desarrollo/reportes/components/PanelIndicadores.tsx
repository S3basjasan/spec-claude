import type { Dashboard, Indicador } from '../api'
import { dolares, fecha, horas, numero, porcentaje, promedio } from '../formato'
import { reportesText } from '../text'
import { TarjetaIndicador } from './TarjetaIndicador'

const t = reportesText.indicadores

/** Valor anterior formateado; `null` si el período anterior no tiene datos o no está disponible. */
function anteriorDe(i: Indicador, formatear: (v: number) => string): string | null {
  return i.anterior === null ? null : formatear(i.anterior)
}

interface PanelIndicadoresProps {
  dashboard: Dashboard
}

/** Cinco tarjetas del período con su comparación y la fila secundaria de indicadores de gestión (Alles, H.32). */
export function PanelIndicadores({ dashboard }: PanelIndicadoresProps) {
  const { indicadores: i, gestion: g } = dashboard

  return (
    <section aria-labelledby="rep-ind-titulo" className="flex flex-col gap-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="rep-ind-titulo" className="m-0 text-xl font-bold">
          {t.titulo}
        </h2>
        <span className="text-[13px] text-muted">
          {fecha(dashboard.periodo.desde)} – {fecha(dashboard.periodo.hasta)} · {reportesText.indicadores.anterior(`${fecha(dashboard.periodo_anterior.desde)} – ${fecha(dashboard.periodo_anterior.hasta)}`)}
        </span>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <TarjetaIndicador
          etiqueta={t.nombres.plan_cumplido}
          valor={porcentaje(i.plan_cumplido.valor)}
          nota={i.plan_cumplido.valor === null ? t.notas.sinPlan : t.notas.planCumplido(i.plan_cumplido.acciones_finalizadas, i.plan_cumplido.acciones_validas)}
          disponible={i.plan_cumplido.disponible}
          hayValor={i.plan_cumplido.valor !== null}
          anterior={anteriorDe(i.plan_cumplido, (v) => porcentaje(v))}
          diferencia={i.plan_cumplido.diferencia}
          variacionPct={i.plan_cumplido.variacion_pct}
          unidad="pts"
        />
        <TarjetaIndicador
          etiqueta={t.nombres.total_horas}
          valor={horas(i.total_horas.valor)}
          nota={i.total_horas.valor === null ? t.notas.sinHoras : t.notas.totalHoras(horas(i.total_horas.horas_actividades), horas(i.total_horas.horas_microlearning))}
          disponible={i.total_horas.disponible}
          hayValor={i.total_horas.valor !== null}
          anterior={anteriorDe(i.total_horas, horas)}
          diferencia={i.total_horas.diferencia}
          variacionPct={i.total_horas.variacion_pct}
          unidad="h"
        />
        <TarjetaIndicador
          etiqueta={t.nombres.satisfaccion}
          valor={promedio(i.satisfaccion.valor)}
          nota={i.satisfaccion.valor === null ? t.notas.sinSatisfaccion : t.notas.satisfaccion(i.satisfaccion.respuestas)}
          disponible={i.satisfaccion.disponible}
          hayValor={i.satisfaccion.valor !== null}
          anterior={anteriorDe(i.satisfaccion, promedio)}
          diferencia={i.satisfaccion.diferencia}
          variacionPct={i.satisfaccion.variacion_pct}
        />
        <TarjetaIndicador
          etiqueta={t.nombres.brechas_cerradas}
          valor={i.brechas_cerradas.valor === null ? '—' : numero(i.brechas_cerradas.valor)}
          nota={i.brechas_cerradas.valor === null ? t.notas.sinBrechas : t.notas.brechas(i.brechas_cerradas.valor, i.brechas_cerradas.detectadas)}
          disponible={i.brechas_cerradas.disponible}
          hayValor={i.brechas_cerradas.valor !== null}
          anterior={anteriorDe(i.brechas_cerradas, (v) => numero(v))}
          diferencia={i.brechas_cerradas.diferencia}
          variacionPct={i.brechas_cerradas.variacion_pct}
          decimales={0}
        />
        <TarjetaIndicador
          etiqueta={t.nombres.roi_estimado}
          valor={porcentaje(i.roi_estimado.valor)}
          nota={i.roi_estimado.valor === null ? t.notas.sinRoi : t.notas.roi(i.roi_estimado.acciones_evaluadas)}
          disponible={i.roi_estimado.disponible}
          hayValor={i.roi_estimado.valor !== null}
          anterior={anteriorDe(i.roi_estimado, (v) => porcentaje(v))}
          diferencia={i.roi_estimado.diferencia}
          variacionPct={i.roi_estimado.variacion_pct}
          unidad="pts"
        />
      </div>

      <div>
        <h3 className="m-0 mb-2 text-base font-bold">{t.gestionTitulo}</h3>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <TarjetaIndicador
            pequena
            etiqueta={t.nombres.inversion_por_colaborador}
            valor={dolares(g.inversion_por_colaborador.valor, 2)}
            nota={g.inversion_por_colaborador.valor === null ? t.notas.sinGestion : t.notas.gestion}
            disponible={g.inversion_por_colaborador.disponible}
            hayValor={g.inversion_por_colaborador.valor !== null}
            anterior={anteriorDe(g.inversion_por_colaborador, (v) => dolares(v, 2))}
            diferencia={g.inversion_por_colaborador.diferencia}
            variacionPct={g.inversion_por_colaborador.variacion_pct}
            decimales={2}
          />
          <TarjetaIndicador
            pequena
            etiqueta={t.nombres.horas_por_colaborador}
            valor={horas(g.horas_por_colaborador.valor)}
            nota={g.horas_por_colaborador.valor === null ? t.notas.sinGestion : t.notas.gestion}
            disponible={g.horas_por_colaborador.disponible}
            hayValor={g.horas_por_colaborador.valor !== null}
            anterior={anteriorDe(g.horas_por_colaborador, horas)}
            diferencia={g.horas_por_colaborador.diferencia}
            variacionPct={g.horas_por_colaborador.variacion_pct}
            unidad="h"
          />
          <TarjetaIndicador
            pequena
            etiqueta={t.nombres.costo_por_hora}
            valor={dolares(g.costo_por_hora.valor, 2)}
            nota={g.costo_por_hora.valor === null ? t.notas.sinGestion : t.notas.gestion}
            disponible={g.costo_por_hora.disponible}
            hayValor={g.costo_por_hora.valor !== null}
            anterior={anteriorDe(g.costo_por_hora, (v) => dolares(v, 2))}
            diferencia={g.costo_por_hora.diferencia}
            variacionPct={g.costo_por_hora.variacion_pct}
            decimales={2}
          />
        </div>
      </div>
    </section>
  )
}
