import { adecuacionText } from '../text'

const t = adecuacionText.miAdecuacion

/** «Programa de Talentos»: informativo; el programa depende del Plan de Desarrollo Individual, que aún no existe. */
export function ProgramaDeTalentosInfo() {
  return (
    <section aria-labelledby="programa-titulo" className="rounded-xl border border-border bg-surface px-5 py-4">
      <h2 id="programa-titulo" className="m-0 text-lg font-semibold">
        {t.programaTitulo}
      </h2>
      <p className="m-0 mt-2 text-sm text-muted">{t.programaVacio}</p>
    </section>
  )
}
