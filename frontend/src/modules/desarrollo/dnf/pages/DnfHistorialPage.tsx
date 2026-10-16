import { Link } from 'react-router-dom'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { Combobox } from '../../../../shared/ui/Combobox'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconChartBar } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { PRIORIDADES, type FiltrosHistorial, type Prioridad } from '../api'
import { BadgePrioridad } from '../components/BadgePrioridad'
import { useHistorialParams } from '../hooks/useDnfParams'
import { useHistorialDnf } from '../hooks/useHistorialDnf'
import { useOpcionesDnf } from '../hooks/useOpcionesDnf'
import { dnfText } from '../text'
import { filtrosHistorialSchema } from '../validation'

const text = dnfText.historial
const POR_PAGINA = 15

/** Historial de DNF (Administrador): tabla filtrable de solo lectura; cada fila abre el detalle. */
export function DnfHistorialPage() {
  const params = useHistorialParams()
  const opciones = useOpcionesDnf()

  const filtros: FiltrosHistorial = {
    fecha_desde: params.fecha_desde || undefined,
    fecha_hasta: params.fecha_hasta || undefined,
    colaborador_id: params.colaborador === '' ? undefined : params.colaborador,
    departamento_id: params.departamento === '' ? undefined : params.departamento,
    prioridad: params.prioridad === '' ? undefined : params.prioridad,
    page: params.page,
    per_page: POR_PAGINA,
  }
  // Un rango de fechas invertido se avisa en el formulario y no se envía al servidor.
  const validacion = filtrosHistorialSchema.safeParse(filtros)
  const errorRango = validacion.success ? undefined : validacion.error.issues.find((i) => i.path[0] === 'fecha_hasta')?.message
  const consulta: FiltrosHistorial = errorRango ? { ...filtros, fecha_desde: undefined, fecha_hasta: undefined } : filtros
  const historial = useHistorialDnf(consulta)
  const hayFiltros = Boolean(params.fecha_desde || params.fecha_hasta || params.colaborador || params.departamento || params.prioridad)

  const meta = historial.data?.meta

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <Breadcrumb
        label={text.title}
        items={[{ label: text.breadcrumbInicio, to: '/dnf' }, { label: text.title }]}
      />
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
        <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
      </div>

      <div className="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div className="flex flex-col gap-1.5">
          <label htmlFor="hist-desde" className="text-sm font-semibold">
            {text.filtros.desde}
          </label>
          <input
            id="hist-desde"
            type="date"
            value={params.fecha_desde}
            onChange={(e) => params.update({ fecha_desde: e.target.value })}
            className="h-11 rounded-lg border border-input-border bg-surface px-3 text-[15px]"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor="hist-hasta" className="text-sm font-semibold">
            {text.filtros.hasta}
          </label>
          <input
            id="hist-hasta"
            type="date"
            value={params.fecha_hasta}
            onChange={(e) => params.update({ fecha_hasta: e.target.value })}
            aria-invalid={errorRango ? true : undefined}
            aria-describedby={errorRango ? 'hist-hasta-error' : undefined}
            className={`h-11 rounded-lg border bg-surface px-3 text-[15px] ${errorRango ? 'border-danger' : 'border-input-border'}`}
          />
          {errorRango && (
            <p id="hist-hasta-error" className="text-[13px] text-danger">
              {errorRango}
            </p>
          )}
        </div>
        <Combobox
          id="hist-colaborador"
          label={text.filtros.colaborador}
          placeholder={text.filtros.colaboradorPlaceholder}
          options={(opciones.data?.colaboradores ?? []).map((c) => ({ value: String(c.id), label: c.nombre, description: c.puesto }))}
          value={params.colaborador === '' ? null : String(params.colaborador)}
          onChange={(valor) => params.update({ colaborador: valor === null ? '' : Number(valor) })}
        />
        <SelectField
          id="hist-departamento"
          label={text.filtros.departamento}
          value={params.departamento === '' ? '' : String(params.departamento)}
          onChange={(e) => params.update({ departamento: e.target.value === '' ? '' : Number(e.target.value) })}
          options={[
            { value: '', label: text.filtros.departamentoTodos },
            ...(opciones.data?.departamentos ?? []).map((d) => ({ value: String(d.id), label: d.nombre })),
          ]}
        />
        <SelectField
          id="hist-prioridad"
          label={text.filtros.prioridad}
          value={params.prioridad}
          onChange={(e) => params.update({ prioridad: e.target.value as Prioridad | '' })}
          options={[
            { value: '', label: text.filtros.prioridadTodas },
            ...PRIORIDADES.map((p) => ({ value: p, label: dnfText.prioridad[p] })),
          ]}
        />
        <div>
          <Button variant="secondary" onClick={params.limpiar} disabled={!hayFiltros}>
            {text.filtros.limpiar}
          </Button>
        </div>
      </div>

      {historial.isPending ? (
        <div role="status" aria-label={text.tableLabel} className="flex flex-col gap-2">
          {[0, 1, 2, 3].map((fila) => (
            <Skeleton key={fila} className="h-12" />
          ))}
        </div>
      ) : historial.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState
            title={dnfText.admin.error.title}
            description={dnfText.admin.error.description}
            retryLabel={dnfText.admin.error.retry}
            onRetry={() => void historial.refetch()}
          />
        </div>
      ) : historial.data.data.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState
            icon={<IconChartBar size={24} />}
            title={hayFiltros ? text.sinCoincidencias : text.vacio.title}
            description={hayFiltros ? '' : text.vacio.description}
          />
        </div>
      ) : (
        <>
          <div className="overflow-x-auto rounded-xl border border-border bg-surface">
            <table aria-label={text.tableLabel} className="w-full min-w-175 border-collapse text-left">
              <thead>
                <tr className="text-[13px] font-semibold text-muted">
                  <th scope="col" className="px-4 py-3.5">{text.columnas.codigo}</th>
                  <th scope="col" className="px-4 py-3.5">{text.columnas.fecha}</th>
                  <th scope="col" className="px-4 py-3.5">{text.columnas.alcance}</th>
                  <th scope="col" className="px-4 py-3.5">{text.columnas.objetivo}</th>
                  <th scope="col" className="px-4 py-3.5">{text.columnas.brechas}</th>
                  <th scope="col" className="px-4 py-3.5">{text.columnas.prioridad}</th>
                  <th scope="col" className="px-4 py-3.5">{text.columnas.plan}</th>
                  <th scope="col" className="px-4 py-3.5 text-right">{text.columnas.acciones}</th>
                </tr>
              </thead>
              <tbody>
                {historial.data.data.map((fila) => (
                  <tr key={fila.id} className="border-t border-border text-[15px] hover:bg-soft">
                    <td className="px-4 py-3.5 font-semibold">{fila.codigo}</td>
                    <td className="px-4 py-3.5">{dnfText.fecha(fila.fecha)}</td>
                    <td className="px-4 py-3.5">{dnfText.alcance[fila.alcance]}</td>
                    <td className="px-4 py-3.5">{fila.colaborador?.nombre ?? fila.departamento?.nombre ?? dnfText.noDisponible}</td>
                    <td className="px-4 py-3.5">{fila.cantidad_brechas}</td>
                    <td className="px-4 py-3.5">
                      <BadgePrioridad prioridad={fila.prioridad_mas_alta} />
                    </td>
                    <td className="px-4 py-3.5">{fila.tiene_plan ? text.tienePlan : text.sinPlan}</td>
                    <td className="px-4 py-3.5 text-right">
                      <Link
                        to={`/dnf/${fila.id}`}
                        aria-label={text.verDetalleDe(fila.codigo)}
                        className="inline-flex min-h-11 items-center px-3 font-semibold text-accent underline"
                      >
                        {text.verDetalle}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {meta && (
            <Pagination
              page={meta.page}
              lastPage={meta.last_page}
              total={meta.total}
              from={(meta.page - 1) * meta.per_page + 1}
              to={Math.min(meta.page * meta.per_page, meta.total)}
              onPageChange={(page) => params.update({ page })}
            />
          )}
        </>
      )}
    </div>
  )
}
