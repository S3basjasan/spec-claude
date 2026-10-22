import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TIPOS, type FiltroSituacion, type TipoPolitica } from '../api'
import { TablaDePoliticas } from '../components/TablaDePoliticas'
import { usePoliticas } from '../hooks/usePoliticas'
import { usePoliticasParams } from '../hooks/usePoliticasParams'
import { politicasText } from '../text'

const text = politicasText.list

const OPCIONES_TIPO = [{ value: '', label: text.filters.tipoAll }, ...TIPOS.map((t) => ({ value: t, label: politicasText.tipos[t] }))]
const OPCIONES_SITUACION = [
  { value: '', label: text.filters.situacionAll },
  ...(['SinConfigurar', 'Vigente', 'NoAdmiteSolicitudes', 'ConProgramada'] as const).map((s) => ({ value: s, label: text.filters.situaciones[s] })),
]

/** Políticas de personal del Administrador: un registro por tipo, filtrable por tipo y situación de configuración. */
export function PoliticasPage() {
  const { tipo, situacion, update } = usePoliticasParams()
  const consulta = usePoliticas({ tipo: tipo ?? undefined, situacion: situacion ?? undefined })
  const hayFiltros = tipo !== null || situacion !== null

  let contenido
  if (consulta.isPending) {
    contenido = (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2, 3].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  } else if (consulta.isError) {
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={politicasText.retry} onRetry={() => void consulta.refetch()} />
      </div>
    )
  } else if (consulta.data.length === 0) {
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        <EmptyState
          icon={<IconFileText size={24} />}
          title={text.emptyFilterTitle}
          description={text.emptyFilterDescription}
          action={
            <Button variant="secondary" onClick={() => update({ tipo: '', situacion: '' })}>
              {text.clearFilters}
            </Button>
          }
        />
      </div>
    )
  } else {
    contenido = <TablaDePoliticas filas={consulta.data} />
  }

  return (
    <div className="mx-auto flex max-w-300 flex-col gap-5">
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
        <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <div className="min-w-56">
          <SelectField
            id="filtro-tipo"
            label={text.filters.tipo}
            options={OPCIONES_TIPO}
            value={tipo ?? ''}
            onChange={(e) => update({ tipo: e.target.value as TipoPolitica | '' })}
          />
        </div>
        <div className="min-w-56">
          <SelectField
            id="filtro-situacion"
            label={text.filters.situacion}
            options={OPCIONES_SITUACION}
            value={situacion ?? ''}
            onChange={(e) => update({ situacion: e.target.value as FiltroSituacion | '' })}
          />
        </div>
        {hayFiltros && (
          <Button variant="secondary" onClick={() => update({ tipo: '', situacion: '' })}>
            {text.clearFilters}
          </Button>
        )}
      </div>

      {contenido}
    </div>
  )
}
