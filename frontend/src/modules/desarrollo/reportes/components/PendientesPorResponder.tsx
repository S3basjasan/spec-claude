import { useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import type { EncuestaPendiente, MiProgreso, SeguimientoPendiente } from '../api'
import { fecha } from '../formato'
import { reportesText } from '../text'
import { FormularioEncuesta } from './FormularioEncuesta'
import { FormularioSeguimiento } from './FormularioSeguimiento'

const t = reportesText.colaborador.pendientes

type Abierto = { tipo: 'encuesta'; pendiente: EncuestaPendiente } | { tipo: 'seguimiento'; pendiente: SeguimientoPendiente } | null

/** «Pendientes por responder»: encuestas de satisfacción y seguimientos a 30-60-90 días disponibles, cada uno con su formulario breve. */
export function PendientesPorResponder({ pendientes }: { pendientes: MiProgreso['pendientes'] }) {
  const [abierto, setAbierto] = useState<Abierto>(null)
  const [aviso, setAviso] = useState(false)
  const hay = pendientes.encuestas.length > 0 || pendientes.seguimientos.length > 0

  const cerrarConAviso = () => {
    setAbierto(null)
    setAviso(true)
  }

  return (
    <section aria-labelledby="mp-pend-titulo" className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <div>
        <h2 id="mp-pend-titulo" className="m-0 text-base font-bold">
          {t.titulo}
        </h2>
        <p className="m-0 text-[13px] text-muted">{t.descripcion}</p>
      </div>

      <p role="status" aria-live="polite" className={aviso ? 'm-0 rounded-lg border border-border bg-soft px-3 py-2 text-sm' : 'sr-only'}>
        {aviso ? t.enviada : ''}
      </p>

      {!hay ? (
        <p className="m-0 text-sm text-muted">{t.vacio}</p>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-2 p-0">
          {pendientes.encuestas.map((p) => (
            <li key={`e-${p.actividad_id}`} className="flex flex-wrap items-center gap-3 rounded-lg border border-border px-3 py-2.5">
              <div className="min-w-0 flex-1">
                <div className="text-xs font-semibold uppercase tracking-wide text-accent">{t.encuesta}</div>
                <div className="truncate text-[15px] font-semibold">{p.nombre}</div>
                <div className="text-[13px] text-muted">{t.vence(fecha(p.vence))}</div>
              </div>
              <Button aria-label={t.responderDe(t.encuesta, p.nombre)} onClick={() => setAbierto({ tipo: 'encuesta', pendiente: p })}>
                {t.responder}
              </Button>
            </li>
          ))}
          {pendientes.seguimientos.map((p) => (
            <li key={`s-${p.actividad_id}-${p.hito}`} className="flex flex-wrap items-center gap-3 rounded-lg border border-border px-3 py-2.5">
              <div className="min-w-0 flex-1">
                <div className="text-xs font-semibold uppercase tracking-wide text-accent">{t.seguimiento(p.hito)}</div>
                <div className="truncate text-[15px] font-semibold">{p.nombre}</div>
                <div className="text-[13px] text-muted">{t.vence(fecha(p.vence))}</div>
              </div>
              <Button aria-label={t.responderDe(t.seguimiento(p.hito), p.nombre)} onClick={() => setAbierto({ tipo: 'seguimiento', pendiente: p })}>
                {t.responder}
              </Button>
            </li>
          ))}
        </ul>
      )}

      {abierto?.tipo === 'encuesta' && <FormularioEncuesta pendiente={abierto.pendiente} onCerrar={() => setAbierto(null)} onEnviada={cerrarConAviso} />}
      {abierto?.tipo === 'seguimiento' && <FormularioSeguimiento pendiente={abierto.pendiente} onCerrar={() => setAbierto(null)} onEnviado={cerrarConAviso} />}
    </section>
  )
}
