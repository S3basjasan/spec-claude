import type { ReactNode } from 'react'
import { estiloVariacion, tonoDeVariacion } from '../estilosEstado'
import { conSigno } from '../formato'
import { reportesText } from '../text'

const t = reportesText.indicadores

interface TarjetaIndicadorProps {
  etiqueta: string
  /** Valor ya formateado («—» si no hay). */
  valor: string
  /** Nota bajo el valor; con un indicador sin datos explica por qué no hay valor. */
  nota: string
  disponible: boolean
  hayValor: boolean
  /** Valor anterior ya formateado; `null` si el período anterior no tiene datos. */
  anterior: string | null
  diferencia: number | null
  variacionPct: number | null
  /** Decimales de la diferencia (por defecto 1). */
  decimales?: number
  /** Unidad que acompaña a la diferencia, p. ej. «pts» o «h». */
  unidad?: string
  pequena?: boolean
}

/** Tarjeta de indicador con su valor, su nota y la comparación contra el período anterior equivalente (Alles, H.32). */
export function TarjetaIndicador({ etiqueta, valor, nota, disponible, hayValor, anterior, diferencia, variacionPct, decimales = 1, unidad = '', pequena = false }: TarjetaIndicadorProps) {
  const tono = tonoDeVariacion(diferencia)
  const sinComparacion: ReactNode = <span className="text-muted">{disponible ? t.sinComparacion : reportesText.noDisponible}</span>

  return (
    <article aria-label={etiqueta} className="flex flex-col gap-1.5 rounded-xl border border-border bg-surface p-4">
      <h3 className="m-0 text-[13px] font-semibold uppercase tracking-wide text-muted">{etiqueta}</h3>
      <p className={`m-0 font-bold leading-none tracking-tight ${pequena ? 'text-[26px]' : 'text-[32px]'}`}>
        <span data-testid="valor">{disponible ? valor : reportesText.noDisponible}</span>
      </p>
      <p className="m-0 min-h-10 text-[13px] text-muted">{disponible ? nota : reportesText.graficos.noDisponible}</p>
      <div className="mt-auto border-t border-border pt-2 text-[13px]">
        {!disponible || !hayValor || anterior === null || diferencia === null ? (
          sinComparacion
        ) : (
          <>
            <p className="m-0 text-muted">{t.anterior(anterior)}</p>
            <p className={`m-0 font-bold ${estiloVariacion[tono]}`}>
              {t.variacion(`${conSigno(diferencia, decimales)}${unidad ? ` ${unidad}` : ''}`, variacionPct === null ? 'sin variación porcentual' : `${conSigno(variacionPct, 1)} %`)}
            </p>
          </>
        )}
      </div>
    </article>
  )
}
