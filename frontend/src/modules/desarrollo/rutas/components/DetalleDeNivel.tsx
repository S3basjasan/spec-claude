import type { ReactNode } from 'react'
import type { CompetenciaDeNivel, Grado, Nivel } from '../api'
import { rutasText } from '../text'

const t = rutasText.detalle

interface DetalleDeNivelProps {
  nivel: Nivel
  /** El nivel 1 no tiene nivel anterior: no muestra diferencial. */
  esPrimero: boolean
  titulo: string
  subtitulo: string
}

const tarjeta = 'rounded-xl border border-border bg-surface px-4 py-3.5'

function Grado({ valor, etiqueta }: { valor: Grado; etiqueta: string }) {
  return (
    <span aria-label={etiqueta} className="min-w-7 shrink-0 rounded-md bg-soft px-2 py-px text-center text-[13px] font-bold text-accent">
      {valor}
    </span>
  )
}

function textoDiferencial(c: CompetenciaDeNivel, esPrimero: boolean): string {
  const d = c.diferencial
  if (esPrimero || d === null) return t.primerNivel
  switch (d.tipo) {
    case 'nuevo':
      return t.nuevo
    case 'sube':
      return t.sube(d.delta ?? 0, d.antes ?? c.grado)
    case 'baja':
      return t.baja(d.delta ?? 0, d.antes ?? c.grado)
    default:
      return t.sinCambio
  }
}

function Lista({ titulo, items }: { titulo: string; items: string[] }) {
  if (items.length === 0) return null
  return (
    <div>
      <h4 className="m-0 mb-1 text-xs font-bold uppercase tracking-wide text-muted">{titulo}</h4>
      <ul className="m-0 flex list-disc flex-col gap-1 pl-4 text-sm">
        {items.map((item, i) => (
          <li key={i}>{item}</li>
        ))}
      </ul>
    </div>
  )
}

function Tarjeta({ titulo, children, etiqueta }: { titulo: string; children: ReactNode; etiqueta?: string }) {
  return (
    <section className={tarjeta}>
      <div className="mb-2.5 flex items-center justify-between gap-2">
        <h3 className="m-0 text-sm text-accent">{titulo}</h3>
        {etiqueta && <span className="rounded-full bg-neutralsoft px-2 py-px text-[11px] font-semibold text-neutral">{etiqueta}</span>}
      </div>
      {children}
    </section>
  )
}

/**
 * Detalle de un nivel en tres tarjetas (RutasAdmin.dc.html). Solo las competencias llevan grado y diferencial frente al nivel
 * anterior; «Conocimientos» es informativo (requisitos de formación y capacitación del puesto, sin grado ni comparación)
 * y «Experiencia» muestra los años mínimos que guarda el nivel. Todo es de solo lectura aquí.
 */
export function DetalleDeNivel({ nivel, esPrimero, titulo, subtitulo }: DetalleDeNivelProps) {
  const { conocimientos, experiencia } = nivel
  const sinConocimientos = conocimientos.formacion.length === 0 && conocimientos.capacitacion.length === 0

  return (
    <div className="flex flex-col gap-3.5">
      <div>
        <h2 id="detalle-nivel-titulo" className="m-0 text-lg">
          {titulo}
        </h2>
        <p className="mt-1 text-[13px] text-muted">{subtitulo}</p>
      </div>

      <div className="grid grid-cols-[repeat(auto-fit,minmax(210px,1fr))] gap-3.5">
        <Tarjeta titulo={t.competencias}>
          {nivel.competencias.length === 0 ? (
            <p className="m-0 text-sm text-muted">{t.sinCompetencias}</p>
          ) : (
            <ul className="m-0 flex list-none flex-col gap-2.5 p-0">
              {nivel.competencias.map((c) => (
                <li key={c.competencia_id}>
                  <div className="flex justify-between gap-2 text-sm font-semibold">
                    <span>{c.nombre}</span>
                    <Grado valor={c.grado} etiqueta={t.gradoDe(c.nombre, c.grado)} />
                  </div>
                  <div className="text-xs text-muted">{textoDiferencial(c, esPrimero)}</div>
                </li>
              ))}
            </ul>
          )}
          {nivel.competencias_quitadas.length > 0 && (
            <div className="mt-3 border-t border-border pt-2.5">
              <h4 className="m-0 mb-1 text-xs font-bold uppercase tracking-wide text-muted">{t.quitadas}</h4>
              <ul className="m-0 flex list-none flex-col gap-1 p-0 text-sm text-muted">
                {nivel.competencias_quitadas.map((c) => (
                  <li key={c.competencia_id}>
                    {c.nombre} ({c.grado_anterior})
                  </li>
                ))}
              </ul>
            </div>
          )}
        </Tarjeta>

        <Tarjeta titulo={t.conocimientos} etiqueta={t.informativo}>
          {sinConocimientos ? (
            <p className="m-0 text-sm text-muted">{t.sinConocimientos}</p>
          ) : (
            <div className="flex flex-col gap-3">
              <Lista titulo={t.formacion} items={conocimientos.formacion} />
              <Lista titulo={t.capacitacion} items={conocimientos.capacitacion} />
            </div>
          )}
          <p className="mb-0 mt-3 text-xs text-muted">{t.informativoAyuda}</p>
        </Tarjeta>

        <Tarjeta titulo={t.experiencia}>
          <div className="text-[22px] font-bold tabular-nums">{rutasText.anios(experiencia.anios_minimos)}</div>
          <div className="mt-0.5 text-xs text-muted">{t.anioDif(experiencia.diferencial_anios, esPrimero)}</div>
          {experiencia.requisitos.length > 0 ? (
            <ul className="mb-0 mt-2.5 flex list-disc flex-col gap-1 pl-4 text-sm leading-normal">
              {experiencia.requisitos.map((r, i) => (
                <li key={i}>{r}</li>
              ))}
            </ul>
          ) : (
            <p className="mb-0 mt-2.5 text-sm text-muted">{t.sinRequisitos}</p>
          )}
        </Tarjeta>
      </div>

      <p className="m-0 text-xs text-muted">{t.escala}</p>
    </div>
  )
}
