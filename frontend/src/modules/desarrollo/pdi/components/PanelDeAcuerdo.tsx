import type { Pdi } from '../api'
import { fechaConHora } from '../fecha'
import { pdiText } from '../text'

const t = pdiText.detalle

/** Acuerdo del responsable y aceptación del colaborador, con el comentario si el colaborador devolvió el plan. */
export function PanelDeAcuerdo({ pdi }: { pdi: Pdi }) {
  return (
    <section aria-label={t.acuerdoTitulo} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <h3 className="m-0 text-base font-bold">{t.acuerdoTitulo}</h3>
      <dl className="m-0 grid gap-3 sm:grid-cols-2">
        <div>
          <dt className="text-[13px] text-muted">{t.acuerdoResponsable}</dt>
          <dd className="m-0 font-semibold">
            {pdi.acuerdo_responsable === null ? t.pendiente : `${fechaConHora(pdi.acuerdo_responsable.fecha)} · ${pdi.acuerdo_responsable.usuario}`}
          </dd>
        </div>
        <div>
          <dt className="text-[13px] text-muted">{t.aceptacionColaborador}</dt>
          <dd className="m-0 font-semibold">{pdi.aceptacion_colaborador === null ? t.pendiente : fechaConHora(pdi.aceptacion_colaborador)}</dd>
        </div>
      </dl>
      {pdi.comentario_colaborador !== null && pdi.comentario_colaborador !== '' && (
        <div className="rounded-lg bg-warningsoft p-3 text-warning">
          <p className="m-0 text-[13px] font-semibold">{t.comentarioColaborador}</p>
          <p className="m-0">{pdi.comentario_colaborador}</p>
        </div>
      )}
      {pdi.estado === 'Borrador' && <p className="m-0 text-[13px] text-muted">{t.guia}</p>}
    </section>
  )
}
