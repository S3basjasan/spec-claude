import { Link, useSearchParams } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SelectField, type SelectOption } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextField } from '../../../../shared/ui/TextField'
import { ESTADOS_DE_ENTREVISTA, ESTADOS_DE_SALIDA, FILTRO_TODOS, MOTIVOS, type FiltrosDeSalidas, type ResumenDeSalidas } from '../api'
import { EstadoDeLaEntrevista, EstadoDeLaSalida, EstadoDelCierre } from '../components/Estados'
import { useListadoDeSalidas, useResumenDeSalidas } from '../hooks/useOffboarding'
import { offboardingText } from '../text'

const text = offboardingText.list
const CLAVES = ['estado', 'cierre', 'motivo', 'entrevista', 'desde', 'hasta'] as const

const TARJETAS: { clave: keyof ResumenDeSalidas; filtro: Partial<Record<(typeof CLAVES)[number], string>> }[] = [
  { clave: 'programadas', filtro: { estado: 'Programada' } },
  { clave: 'efectivas', filtro: { estado: 'Efectiva' } },
  { clave: 'con_incidencia', filtro: { estado: 'IncidenciaDeAplicacion' } },
  { clave: 'cierre_pendiente', filtro: { estado: 'Efectiva', cierre: 'Pendiente' } },
  { clave: 'entrevistas_pendientes', filtro: { entrevista: 'Pendiente' } },
  { clave: 'entrevistas_completadas', filtro: { entrevista: 'Completada' } },
  { clave: 'entrevistas_no_realizadas', filtro: { entrevista: 'NoRealizada' } },
]

const todas = (etiqueta: string): SelectOption => ({ value: FILTRO_TODOS, label: etiqueta })

/** Filtros y página del listado, guardados en la URL para poder compartirlos. */
function useFiltrosDeSalidas() {
  const [params, setParams] = useSearchParams()
  const valores = Object.fromEntries(CLAVES.map((clave) => [clave, params.get(clave) ?? (clave === 'desde' || clave === 'hasta' ? '' : FILTRO_TODOS)])) as Record<(typeof CLAVES)[number], string>
  const page = Math.max(1, Math.floor(Number(params.get('page'))) || 1)

  const actualizar = (cambios: Partial<Record<(typeof CLAVES)[number], string>>, nuevaPagina?: number, reemplazar = false) => {
    const siguiente = reemplazar ? new URLSearchParams() : new URLSearchParams(params)
    for (const [clave, valor] of Object.entries(cambios)) {
      if (valor === '' || valor === FILTRO_TODOS) siguiente.delete(clave)
      else siguiente.set(clave, valor)
    }
    if (nuevaPagina !== undefined && nuevaPagina > 1) siguiente.set('page', String(nuevaPagina))
    else siguiente.delete('page')
    setParams(siguiente, { replace: true })
  }

  const filtros: FiltrosDeSalidas = { ...valores, page }
  return { valores, filtros, page, actualizar, limpiar: () => setParams(new URLSearchParams(), { replace: true }) }
}

function Resumen({ onFiltrar }: { onFiltrar: (filtro: Partial<Record<(typeof CLAVES)[number], string>>) => void }) {
  const resumen = useResumenDeSalidas()

  if (resumen.isPending) return <Skeleton className="h-20" />
  if (resumen.isError) return <Alert>{text.summaryError}</Alert>

  return (
    <ul aria-label={text.summaryLabel} className="grid grid-cols-2 gap-3 md:grid-cols-4">
      {TARJETAS.map(({ clave, filtro }) => (
        <li key={clave}>
          <button
            type="button"
            onClick={() => onFiltrar({ estado: FILTRO_TODOS, cierre: FILTRO_TODOS, entrevista: FILTRO_TODOS, ...filtro })}
            className="flex min-h-11 w-full flex-col items-start gap-1 rounded-xl border border-border bg-surface p-3 text-left hover:bg-soft"
          >
            <span className="text-2xl font-bold">{resumen.data[clave]}</span>
            <span className="text-[13px] text-muted">{text.summary[clave]}</span>
          </button>
        </li>
      ))}
    </ul>
  )
}

/** `/offboarding`: listado del Administrador con resumen, filtros en la URL y estados distinguibles. */
export function OffboardingPage() {
  const { valores, filtros, actualizar, limpiar } = useFiltrosDeSalidas()
  const listado = useListadoDeSalidas(filtros)

  let contenido
  if (listado.isPending) {
    contenido = (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2, 3].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  } else if (listado.isError) {
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={offboardingText.retry} onRetry={() => void listado.refetch()} />
      </div>
    )
  } else if (listado.data.data.length === 0) {
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        <EmptyState icon={<IconFileText size={24} />} title={text.emptyTitle} description={text.emptyDescription} />
      </div>
    )
  } else {
    const { meta, data } = listado.data
    contenido = (
      <>
        <div className="overflow-x-auto rounded-xl border border-border bg-surface">
          <table aria-label={text.tableLabel} className="w-full min-w-[860px] border-collapse text-left text-sm">
            <thead className="bg-soft text-[13px] text-muted">
              <tr>
                {Object.values(text.columns).map((columna) => (
                  <th key={columna} scope="col" className="px-4 py-3 font-semibold">
                    {columna}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.map((fila) => (
                <tr key={fila.id} className="border-t border-border">
                  <td className="px-4 py-3">
                    <Link to={`/offboarding/${fila.id}`} aria-label={text.openFile(fila.colaborador.nombre)} className="font-semibold text-accent underline">
                      {fila.colaborador.nombre}
                    </Link>
                    <span className="block text-[13px] text-muted">{fila.colaborador.codigo}</span>
                  </td>
                  <td className="px-4 py-3">{offboardingText.motivos[fila.motivo]}</td>
                  <td className="px-4 py-3">{fila.fecha_efectiva}</td>
                  <td className="px-4 py-3">
                    <EstadoDeLaSalida estado={fila.estado} />
                  </td>
                  <td className="px-4 py-3">{fila.estado === 'Cancelada' ? offboardingText.none : <EstadoDelCierre estado={fila.cierre_estado} />}</td>
                  <td className="px-4 py-3">
                    <EstadoDeLaEntrevista estado={fila.entrevista_estado} />
                  </td>
                  <td className="px-4 py-3">{fila.tareas_pendientes}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <Pagination page={meta.current_page} lastPage={meta.last_page} total={meta.total} from={meta.from} to={meta.to} onPageChange={(p) => actualizar({}, p)} />
      </>
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{text.title}</h1>
          <p className="text-muted">{text.subtitle}</p>
        </div>
        <Link to="/offboarding/formalizar" className="inline-flex min-h-11 items-center rounded-lg bg-accent px-5 text-[15px] font-semibold text-onaccent hover:bg-accent-hover">
          {text.formalize}
        </Link>
      </header>

      <Resumen onFiltrar={(filtro) => actualizar(filtro)} />

      <form role="search" aria-label={text.filtersLabel} className="grid gap-3 rounded-xl border border-border bg-surface p-4 md:grid-cols-3" onSubmit={(e) => e.preventDefault()}>
        <SelectField id="filtro-estado" label={text.filters.status} value={valores.estado} onChange={(e) => actualizar({ estado: e.target.value })} options={[todas(text.filters.all), ...ESTADOS_DE_SALIDA.map((e) => ({ value: e, label: offboardingText.estados[e] }))]} />
        <SelectField id="filtro-cierre" label={text.filters.closing} value={valores.cierre} onChange={(e) => actualizar({ cierre: e.target.value })} options={[todas(text.filters.all), { value: 'Pendiente', label: offboardingText.cierre.Pendiente }, { value: 'Cerrado', label: offboardingText.cierre.Cerrado }]} />
        <SelectField id="filtro-motivo" label={text.filters.reason} value={valores.motivo} onChange={(e) => actualizar({ motivo: e.target.value })} options={[todas(text.filters.all), ...MOTIVOS.map((m) => ({ value: m, label: offboardingText.motivos[m] }))]} />
        <SelectField id="filtro-entrevista" label={text.filters.interview} value={valores.entrevista} onChange={(e) => actualizar({ entrevista: e.target.value })} options={[todas(text.filters.all), ...ESTADOS_DE_ENTREVISTA.map((e) => ({ value: e, label: offboardingText.entrevista[e] }))]} />
        <TextField id="filtro-desde" type="date" label={text.filters.from} value={valores.desde} onChange={(e) => actualizar({ desde: e.target.value })} />
        <div className="flex flex-col gap-3">
          <TextField id="filtro-hasta" type="date" label={text.filters.to} value={valores.hasta} onChange={(e) => actualizar({ hasta: e.target.value })} />
          <Button variant="secondary" onClick={limpiar}>
            {text.filters.clear}
          </Button>
        </div>
      </form>

      {contenido}
    </div>
  )
}
