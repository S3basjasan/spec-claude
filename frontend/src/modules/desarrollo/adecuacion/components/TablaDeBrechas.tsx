import type { Resultado } from '../api'
import { MAX_MESES_CIERRE, MIN_MESES_CIERRE } from '../api'
import { adecuacionText } from '../text'
import { brechasSinTiempo, claveDeResultado, validarMeses } from '../validation'

const t = adecuacionText.brechas

interface Props {
  resultados: Resultado[]
  tiempos: Record<string, string>
  onTiempo: (clave: string, texto: string) => void
  /** Errores devueltos por el servidor, por clave del requisito. */
  erroresDelServidor?: Record<string, string>
}

/** «Brechas y tiempo estimado de cierre»: un campo de meses por cada requisito Con brecha. */
export function TablaDeBrechas({ resultados, tiempos, onTiempo, erroresDelServidor = {} }: Props) {
  const brechas = resultados.filter((r) => r.estado === 'ConBrecha')
  const faltan = brechasSinTiempo(resultados, tiempos).length

  return (
    <section aria-labelledby="brechas-titulo" className="overflow-x-auto rounded-xl border border-border bg-surface">
      <div className="px-5 pb-2 pt-4">
        <h2 id="brechas-titulo" className="m-0 text-lg font-semibold">
          {t.titulo}
        </h2>
        <p className="m-0 mt-0.5 text-[13px] text-muted">{brechas.length === 0 ? t.sinBrechas : faltan === 0 ? t.completo : t.faltan(faltan)}</p>
      </div>

      {brechas.length > 0 && (
        <table className="w-full min-w-[640px] border-collapse text-sm">
          <thead>
            <tr className="text-left text-[13px] text-muted">
              <th scope="col" className="py-2.5 pl-5 pr-3 font-semibold">
                {t.dimension}
              </th>
              <th scope="col" className="px-3 py-2.5 font-semibold">
                {t.elemento}
              </th>
              <th scope="col" className="px-3 py-2.5 font-semibold">
                {t.brecha}
              </th>
              <th scope="col" className="py-2.5 pl-3 pr-5 font-semibold">
                {t.tiempo}
              </th>
            </tr>
          </thead>
          <tbody>
            {brechas.map((r) => {
              const clave = claveDeResultado(r)
              const nombre = r.nombre ?? adecuacionText.panel.experiencia
              const error = validarMeses(tiempos[clave] ?? '') ?? erroresDelServidor[clave]
              const idError = `error-${clave.replace(':', '-')}`

              return (
                <tr key={clave} className="border-t border-border align-top">
                  <td className="py-3 pl-5 pr-3">{adecuacionText.dimension[r.dimension]}</td>
                  <td className="px-3 py-3 font-semibold">{nombre}</td>
                  <td className="px-3 py-3">{t.textoBrecha(r.diferencia ?? 0, r.dimension === 'Experiencia' ? 'año' : 'grado')}</td>
                  <td className="py-3 pl-3 pr-5">
                    <input
                      type="number"
                      inputMode="numeric"
                      min={MIN_MESES_CIERRE}
                      max={MAX_MESES_CIERRE}
                      step={1}
                      value={tiempos[clave] ?? ''}
                      onChange={(e) => onTiempo(clave, e.target.value)}
                      placeholder={t.tiempoPlaceholder}
                      aria-label={t.tiempoAria(nombre)}
                      aria-invalid={error !== undefined}
                      aria-describedby={error === undefined ? undefined : idError}
                      className="h-11 w-28 rounded-lg border border-input-border bg-surface px-3 text-text"
                    />
                    {error !== undefined && (
                      <p id={idError} className="m-0 mt-1 text-[13px] text-danger">
                        {error}
                      </p>
                    )}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      )}
    </section>
  )
}
