import { Link } from 'react-router-dom'
import type { BrechaPropia } from '../api'
import { adecuacionText } from '../text'

const t = adecuacionText.miAdecuacion

/** «Lo que me falta»: cada brecha con su tiempo estimado de cierre y el camino para cerrarla (cursos o práctica). */
export function ListaLoQueMeFalta({ brechas }: { brechas: BrechaPropia[] }) {
  return (
    <section aria-labelledby="falta-titulo" className="rounded-xl border border-border bg-surface px-5 py-4">
      <h2 id="falta-titulo" className="m-0 text-lg font-semibold">
        {t.faltaTitulo}
      </h2>

      {brechas.length === 0 ? (
        <p className="m-0 mt-2 text-sm text-muted">{t.sinFalta}</p>
      ) : (
        <>
          <p className="m-0 mt-0.5 text-[13px] text-muted">{t.faltaTexto(brechas.length)}</p>
          <ul className="m-0 mt-3 flex list-none flex-col gap-3 p-0">
            {brechas.map((b) => {
              const nombre = b.nombre ?? adecuacionText.panel.experiencia
              const esExperiencia = b.dimension === 'Experiencia'

              return (
                <li key={`${b.dimension}:${b.elemento_id ?? 0}`} className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3 first:border-t-0 first:pt-0">
                  <div className="min-w-0">
                    <div className="text-sm font-semibold">{nombre}</div>
                    <div className="text-[13px] text-muted">
                      {adecuacionText.dimension[b.dimension]} · {adecuacionText.brechas.textoBrecha(b.diferencia ?? 0, esExperiencia ? 'año' : 'grado')}
                    </div>
                    {b.tiempo_cierre_meses !== null && <div className="text-[13px] text-muted">{t.tiempo(b.tiempo_cierre_meses)}</div>}
                  </div>

                  {esExperiencia ? (
                    <span className="max-w-xs text-[13px] text-muted">{t.experiencia}</span>
                  ) : b.curso !== null ? (
                    <Link
                      to={b.curso.ruta}
                      aria-label={t.verCursosAria(nombre)}
                      className="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-text no-underline hover:bg-soft"
                    >
                      {t.verCursos}
                    </Link>
                  ) : (
                    <span className="text-[13px] text-muted">{t.sinCursos}</span>
                  )}
                </li>
              )
            })}
          </ul>
        </>
      )}
    </section>
  )
}
