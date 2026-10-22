import { Link, useSearchParams } from 'react-router-dom'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconChartBar } from '../../../../shared/ui/icons'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { DIMENSIONES_EN_ORDEN } from '../api'
import { PreparacionBadge } from '../components/Etiquetas'
import { LeyendaDeBarras } from '../components/LeyendaDeBarras'
import { ListaLoQueMeFalta } from '../components/ListaLoQueMeFalta'
import { ProgramaDeTalentosInfo } from '../components/ProgramaDeTalentosInfo'
import { TarjetaDeDimensionColaborador } from '../components/TarjetaDeDimensionColaborador'
import { useMiAdecuacion } from '../hooks/useMiAdecuacion'
import { adecuacionText } from '../text'

const t = adecuacionText
const m = adecuacionText.miAdecuacion

/** «Mi adecuación»: lo que le falta al colaborador para sus puestos objetivo. Solo ve lo propio y nunca puntajes. */
export function MiAdecuacionPage() {
  const [params, setParams] = useSearchParams()
  const pedido = Number(params.get('puesto'))
  const consulta = useMiAdecuacion(Number.isInteger(pedido) && pedido > 0 ? pedido : undefined)
  const datos = consulta.data

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={t.loading} className="mx-auto flex max-w-[1100px] flex-col gap-3">
        <Skeleton className="h-10 w-80" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  if (consulta.isError || datos === undefined) {
    return (
      <div className="mx-auto max-w-[1100px]">
        <ErrorState title={t.errores.cargar.title} description={t.errores.cargar.description} retryLabel={t.reintentar} onRetry={() => void consulta.refetch()} />
      </div>
    )
  }

  const adecuacion = datos.adecuaciones.find((a) => a.id === datos.seleccionada) ?? datos.adecuaciones[0]

  if (adecuacion === undefined) {
    return (
      <div className="mx-auto flex max-w-[1100px] flex-col gap-5">
        {/* <Encabezado titulo={m.tituloVacio} aviso={datos.aviso} /> */}
        <EmptyState icon={<IconChartBar />} title={m.vacioTitulo} description={m.vacioDescripcion} />
      </div>
    )
  }

  const cambiarPuesto = (id: string) => {
    const siguiente = new URLSearchParams(params)
    siguiente.set('puesto', id)
    setParams(siguiente, { replace: true })
  }

  return (
    <div className="mx-auto flex max-w-[1100px] flex-col gap-5">
      {/* <Encabezado titulo={m.titulo(adecuacion.puesto_objetivo.nombre)} aviso={datos.aviso} /> */}
      <Encabezado titulo={m.titulo(adecuacion.puesto_objetivo.nombre)}/>


      {datos.fuentes_no_disponibles.length > 0 && (
        <p role="status" className="m-0 rounded-lg border border-border bg-soft px-3.5 py-3 text-sm">
          {t.errores.fuentes}
        </p>
      )}

      <div className="flex flex-wrap items-end justify-between gap-4 rounded-xl border border-border bg-surface p-4 sm:p-5">
        <div className="min-w-[240px] flex-1">
          <SelectField
            id="mi-adecuacion-puesto"
            label={m.puestoAComparar}
            value={String(adecuacion.puesto_objetivo.id)}
            options={datos.adecuaciones.map((a) => ({ value: String(a.puesto_objetivo.id), label: a.puesto_objetivo.nombre }))}
            onChange={(e) => cambiarPuesto(e.target.value)}
          />
          <p className="m-0 mt-1 text-[13px] text-muted">{m.fecha(adecuacion.fecha)}</p>
        </div>
        <div className="flex flex-wrap items-center gap-4">
          <div>
            <div className="mb-1 text-[13px] text-muted">{m.preparacion}</div>
            <PreparacionBadge valor={adecuacion.preparacion} />
          </div>
          <Link to="/rutas" className="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-text no-underline hover:bg-soft">
            {m.miRuta}
          </Link>
        </div>
      </div>

      <LeyendaDeBarras items={['persona', 'futuro']} etiquetas={{ persona: m.leyendaPersona, futuro: m.leyendaFuturo }} />

      <div className="grid items-start gap-4 [grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr))]">
        {DIMENSIONES_EN_ORDEN.map((dimension) => (
          <TarjetaDeDimensionColaborador
            key={dimension}
            dimension={dimension}
            resultados={adecuacion.resultados.filter((r) => r.dimension === dimension)}
            cumplimiento={adecuacion.cumplimiento_por_dimension[dimension]}
          />
        ))}
      </div>

      <ListaLoQueMeFalta brechas={adecuacion.brechas} />
      <ProgramaDeTalentosInfo />
    </div>
  )
}

// function Encabezado({ titulo, aviso }: { titulo: string; aviso: string }) {
function Encabezado({ titulo }: { titulo: string }) {
  return (
    <>
      <header>
        {/* <p className="m-0 text-[13px] font-semibold text-muted">{t.sobretitulo}</p> */}
        <h1 className="m-0 mt-0.5 text-2xl font-bold">{titulo}</h1>
        <p className="m-0 mt-1 text-muted">{m.descripcion}</p>
      </header>
      {/* <p role="note" className="m-0 rounded-xl border border-border bg-infosoft px-4 py-3 text-sm text-info">
        <strong>{aviso}</strong> {m.avisoPropio}
      </p> */}
    </>
  )
}
