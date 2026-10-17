import type { ReactNode } from 'react'
import type { Referencia, TipoAccion } from '../api'
import { actividadesText } from '../text'
import { EtiquetaTipo } from './EtiquetaTipo'

const campos = actividadesText.formulario.campos

export interface AccionHeredada {
  nombre: string
  tipo: TipoAccion | null
  competencias: Referencia[]
  fecha_inicio: string | null
  fecha_fin: string | null
  formador: string | null
  capacidad: number | null
  inscritos: number
  plan: string
}

/** Datos de la acción del plan, solo lectura: se gestionan en el plan y siempre se leen vigentes. */
export function DatosHeredados({ accion }: { accion: AccionHeredada }) {
  const filas: [string, ReactNode][] = [
    [campos.nombre, accion.nombre],
    [campos.plan, accion.plan],
    [campos.tipo, <EtiquetaTipo key="tipo" tipo={accion.tipo} />],
    [campos.competencias, accion.competencias.length > 0 ? accion.competencias.map((c) => c.nombre).join(', ') : actividadesText.sinDato],
    [campos.fechas, actividadesText.rango(accion.fecha_inicio, accion.fecha_fin)],
    [campos.formador, accion.formador ?? actividadesText.sinDato],
    [campos.capacidad, accion.capacidad ?? actividadesText.sinDato],
    [campos.inscritos, accion.inscritos],
  ]

  return (
    <section aria-label={actividadesText.formulario.heredados} className="rounded-lg border border-border bg-soft p-4">
      <h3 className="mb-3 text-sm font-bold text-muted">{actividadesText.formulario.heredados}</h3>
      <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2 [&>*]:min-w-0 [&_dd]:break-words">
        {filas.map(([etiqueta, valor]) => (
          <div key={etiqueta} className="flex flex-col gap-0.5">
            <dt className="text-[13px] font-semibold text-muted">{etiqueta}</dt>
            <dd className="text-[15px]">{valor}</dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
