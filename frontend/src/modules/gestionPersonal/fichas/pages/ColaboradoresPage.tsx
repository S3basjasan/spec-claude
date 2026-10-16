import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconList, IconPlus } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { FILTRO_TODOS } from '../api'
import { ColaboradoresTable } from '../components/ColaboradoresTable'
import { useColaboradores, useFiltrosDeColaboradores } from '../hooks/useColaboradores'
import { useListadoParams } from '../hooks/useListadoParams'
import { fichasText } from '../text'

const RETARDO_BUSQUEDA_MS = 300
const text = fichasText.list

export function ColaboradoresPage() {
  const location = useLocation()
  const { q, estado, unidadId, page, update } = useListadoParams()
  const consulta = useColaboradores({ q, estado, unidad_id: unidadId ?? undefined, page })
  const filtros = useFiltrosDeColaboradores()

  // Lo que la persona escribe y aún no se aplicó a la URL; null = mostrar lo que dice la URL.
  const [borrador, setBorrador] = useState<string | null>(null)
  const busqueda = borrador ?? q
  const aviso = (location.state as { aviso?: string } | null)?.aviso

  // La búsqueda se escribe sin demora y se aplica a la URL tras una breve pausa (300 ms).
  useEffect(() => {
    if (borrador === null) return
    const espera = setTimeout(() => {
      update({ q: borrador.trim() })
      setBorrador(null)
    }, RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [borrador, update])

  const hayFiltros = q !== '' || estado !== FILTRO_TODOS || unidadId !== null

  const nuevaFicha = (
    <Link
      to="/colaboradores/nuevo"
      className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-accent px-5 text-[15px] font-semibold text-onaccent hover:bg-accent-hover"
    >
      <IconPlus size={18} />
      {text.newButton}
    </Link>
  )

  const limpiarFiltros = (
    <Button
      variant="secondary"
      onClick={() => {
        setBorrador(null)
        update({ q: '', estado: FILTRO_TODOS, unidadId: null })
      }}
    >
      {text.clearFilters}
    </Button>
  )

  const opcionesEstado = (filtros.data?.estados ?? [{ valor: FILTRO_TODOS }]).map((opcion) => ({
    value: opcion.valor,
    label: opcion.valor === FILTRO_TODOS ? text.statusAll : (fichasText.statuses[opcion.valor] ?? opcion.valor),
  }))
  const unidades = filtros.data?.unidades ?? []

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
        </div>
        {nuevaFicha}
      </div>

      {aviso && (
        <p role="status" className="rounded-lg bg-soft px-3.5 py-3 text-sm font-semibold text-accent">
          {aviso}
        </p>
      )}

      <div className="flex flex-wrap items-end gap-3">
        <SearchField
          id="busqueda"
          label={text.searchLabel}
          placeholder={text.searchPlaceholder}
          value={busqueda}
          onChange={(event) => setBorrador(event.target.value)}
          maxLength={50}
          className="min-w-70 flex-1"
        />
        <SelectField
          id="filtro-estado"
          label={text.statusFilterLabel}
          hideLabel
          value={estado}
          onChange={(event) => update({ estado: event.target.value })}
          options={opcionesEstado}
        />
        {unidades.length > 0 && (
          <SelectField
            id="filtro-unidad"
            label={text.unitFilterLabel}
            hideLabel
            value={unidadId === null ? '' : String(unidadId)}
            onChange={(event) => update({ unidadId: event.target.value === '' ? null : Number(event.target.value) })}
            options={[
              { value: '', label: text.unitAll },
              ...unidades.map((unidad) => ({ value: String(unidad.id), label: unidad.nombre })),
            ]}
          />
        )}
      </div>

      {consulta.isPending ? (
        <div role="status" aria-label={text.loadingLabel} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
          {[0, 1, 2, 3].map((fila) => (
            <Skeleton key={fila} className="h-10" />
          ))}
        </div>
      ) : consulta.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState
            title={text.errorTitle}
            description={text.errorDescription}
            retryLabel={fichasText.retry}
            onRetry={() => void consulta.refetch()}
          />
        </div>
      ) : consulta.data.data.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          {hayFiltros ? (
            <EmptyState
              icon={<IconList size={24} />}
              title={text.noResults}
              description={text.noResultsDescription}
              action={limpiarFiltros}
            />
          ) : (
            <EmptyState
              icon={<IconList size={24} />}
              title={text.emptyTitle}
              description={text.emptyDescription}
              action={nuevaFicha}
            />
          )}
        </div>
      ) : (
        <>
          <ColaboradoresTable items={consulta.data.data} />
          <Pagination
            page={consulta.data.meta.current_page}
            lastPage={consulta.data.meta.last_page}
            total={consulta.data.meta.total}
            from={consulta.data.meta.from}
            to={consulta.data.meta.to}
            onPageChange={(siguiente) => update({ page: siguiente })}
          />
        </>
      )}
    </div>
  )
}
