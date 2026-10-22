import { MAX_CANDIDATOS, type ColaboradorOpcion } from '../api'
import { adecuacionText } from '../text'

const t = adecuacionText.comparar

interface Props {
  colaboradores: ColaboradorOpcion[]
  elegidos: number[]
  onAlternar: (id: number) => void
}

/** Candidatos como botones seleccionables (`aria-pressed`); se pide elegir al menos dos y se permiten hasta 20. */
export function SelectorDeCandidatos({ colaboradores, elegidos, onAlternar }: Props) {
  const lleno = elegidos.length >= MAX_CANDIDATOS

  return (
    <section aria-labelledby="candidatos-titulo" className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4 sm:p-5">
      <div>
        <h2 id="candidatos-titulo" className="m-0 text-lg font-semibold">
          {t.candidatosTitulo(elegidos.length)}
        </h2>
        <p className="m-0 mt-0.5 text-[13px] text-muted">{lleno ? t.maximo : t.candidatosAyuda}</p>
      </div>
      <div className="flex flex-wrap gap-2">
        {colaboradores.map((c) => {
          const activo = elegidos.includes(c.id)

          return (
            <button
              key={c.id}
              type="button"
              aria-pressed={activo}
              disabled={!activo && lleno}
              onClick={() => onAlternar(c.id)}
              className={`min-h-11 rounded-full border px-4 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50 ${
                activo ? 'border-accent bg-soft text-accent' : 'border-border bg-surface text-text hover:bg-soft'
              }`}
            >
              {c.nombre}
            </button>
          )
        })}
      </div>
    </section>
  )
}
