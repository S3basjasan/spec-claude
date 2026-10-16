import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import type { Accion, Plan } from '../api'
import { planText } from '../text'
import { BadgeEstadoAccion } from './BadgeEstadoAccion'
import { BadgeEstadoPlan } from './BadgeEstadoPlan'
import { ChipTipo } from './ChipTipo'

const t = planText.detalle

function Dato({ etiqueta, children }: { etiqueta: string; children: ReactNode }) {
  return (
    <div>
      <dt className="text-[13px] text-muted">{etiqueta}</dt>
      <dd className="mt-0.5 text-[15px] font-semibold">{children}</dd>
    </div>
  )
}

interface DetallePlanProps {
  plan: Plan
  /** Botones propios de cada acción (cambiar participantes, cancelar) según el estado del plan. */
  accionesDe?: (accion: Accion) => ReactNode
}

/** Detalle de un plan (patrón Detalle de un registro): datos generales, totales, avisos de cruce y acciones. */
export function DetallePlan({ plan, accionesDe }: DetallePlanProps) {
  return (
    <div className="flex flex-col gap-6">
      <section aria-labelledby="detalle-generales" className="rounded-xl border border-border bg-surface p-5">
        <h2 id="detalle-generales" className="mb-4 text-lg font-bold">
          {t.generales}
        </h2>
        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Dato etiqueta={t.codigo}>{plan.codigo}</Dato>
          <Dato etiqueta={t.estado}>
            <BadgeEstadoPlan estado={plan.estado} />
          </Dato>
          <Dato etiqueta={t.anio}>{plan.anio}</Dato>
          <Dato etiqueta={t.aprobador}>{plan.aprobador?.nombre ?? planText.planes.sinAprobador}</Dato>
          <Dato etiqueta={t.aprobacion}>{plan.fecha_aprobacion ? planText.fechaHora(plan.fecha_aprobacion) : planText.sinDato}</Dato>
          <Dato etiqueta={t.origen}>
            {plan.origen_dnf ? (
              <Link to={`/dnf/${plan.origen_dnf.id}`} className="text-accent underline">
                {t.origenDnf(plan.origen_dnf.codigo)}
              </Link>
            ) : (
              t.sinOrigen
            )}
          </Dato>
          <Dato etiqueta={t.creado}>{plan.creado_por ? t.porEn(plan.creado_por.nombre, planText.fechaHora(plan.creado_en)) : planText.sinDato}</Dato>
          <Dato etiqueta={t.modificado}>{plan.modificado_por ? t.porEn(plan.modificado_por.nombre, planText.fechaHora(plan.modificado_en)) : planText.sinDato}</Dato>
          {plan.descripcion && (
            <div className="sm:col-span-2 lg:col-span-3">
              <Dato etiqueta={t.descripcion}>
                <span className="font-normal">{plan.descripcion}</span>
              </Dato>
            </div>
          )}
        </dl>
      </section>

      <dl className="grid gap-4 sm:grid-cols-3" aria-label={t.acciones}>
        {[
          [t.totales.acciones, String(plan.cantidad_acciones)],
          [t.totales.costo, planText.dinero(plan.costo_total)],
          [t.totales.horas, planText.horas(plan.horas_totales)],
        ].map(([etiqueta, valor]) => (
          <div key={etiqueta} className="rounded-xl border border-border bg-surface p-4">
            <dt className="text-[13px] text-muted">{etiqueta}</dt>
            <dd className="mt-1 text-2xl font-bold">{valor}</dd>
          </div>
        ))}
      </dl>

      {plan.avisos_cruce.length > 0 && (
        <section aria-labelledby="detalle-cruces" className="rounded-xl border border-border bg-warnsoft p-4 text-warn">
          <h2 id="detalle-cruces" className="text-base font-bold">
            {t.cruces}
          </h2>
          <p className="text-[13px]">{t.crucesDescripcion}</p>
          <ul className="mt-2 list-inside list-disc text-[15px]">
            {Array.from(new Set(plan.avisos_cruce.map((a) => a.nombre))).map((nombre) => (
              <li key={nombre}>{t.cruceItem(nombre)}</li>
            ))}
          </ul>
        </section>
      )}

      <section aria-labelledby="detalle-acciones" className="flex flex-col gap-3">
        <h2 id="detalle-acciones" className="text-lg font-bold">
          {t.acciones} ({plan.acciones.length})
        </h2>
        {plan.acciones.length === 0 ? (
          <p className="rounded-xl border border-dashed border-border bg-surface px-4 py-8 text-center text-muted">{t.sinAcciones}</p>
        ) : (
          plan.acciones.map((accion) => (
            <article key={accion.id} aria-label={accion.nombre} className="rounded-xl border border-border bg-surface p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="text-base font-bold">{accion.nombre}</h3>
                  <ChipTipo tipo={accion.tipo} />
                  <BadgeEstadoAccion estado={accion.estado_accion} />
                </div>
                {accionesDe?.(accion)}
              </div>
              <dl className="mt-3 grid gap-3 text-[15px] sm:grid-cols-2 lg:grid-cols-4">
                <Dato etiqueta={t.competencias}>
                  {accion.competencias.map((c) => (c.activa ? c.nombre : `${c.nombre} (${t.competenciaInactiva})`)).join(', ') || planText.sinDato}
                </Dato>
                <Dato etiqueta={t.fechas}>{planText.rango(accion.fecha_inicio, accion.fecha_fin)}</Dato>
                <Dato etiqueta={t.formador}>{accion.formador ?? planText.sinDato}</Dato>
                <Dato etiqueta={t.horas}>{planText.horas(accion.duracion_horas)}</Dato>
                <Dato etiqueta={t.inscritos}>{planText.cupo(accion.participantes.length, accion.capacidad_maxima)}</Dato>
                <Dato etiqueta={t.costo}>{planText.dinero(accion.costo)}</Dato>
              </dl>
              <div className="mt-3">
                <p className="text-[13px] text-muted">{t.participantes}</p>
                {accion.participantes.length === 0 ? (
                  <p className="text-[15px] text-muted">{t.sinParticipantes}</p>
                ) : (
                  <ul className="mt-1 flex flex-wrap gap-2">
                    {accion.participantes.map((p) => (
                      <li key={p.colaborador_id} className="rounded-full bg-bg px-3 py-1 text-[13px] font-semibold">
                        {p.nombre}
                        {!p.vinculo_vigente && <span className="ml-1 text-warn">· {planText.asistente.paso3.noVigente}</span>}
                      </li>
                    ))}
                  </ul>
                )}
              </div>
              {accion.cancelada && <p className="mt-3 text-[13px] text-danger">{t.motivoCancelacion(accion.motivo_cancelacion)}</p>}
            </article>
          ))
        )}
      </section>
    </div>
  )
}
