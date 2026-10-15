import { useEffect, useState } from 'react'
// import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconSchool } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { FiltroCobertura, FiltroEstado, TipoCompetencia } from '../api'
import { CatalogoMatriz } from '../components/CatalogoMatriz'
import { CoberturaIndicador } from '../components/CoberturaIndicador'
import { DetalleCompetenciaPanel } from '../components/DetalleCompetenciaPanel'
import { GestionAreasDialog } from '../components/GestionAreasDialog'
import { useAreas, useCatalogo, useCobertura } from '../hooks/useCatalogo'
import { useListadoParams } from '../hooks/useListadoParams'
import { catalogoText } from '../text'

const RETARDO_BUSQUEDA_MS = 300
const text = catalogoText.admin

/** Vista del Administrador: indicador de cobertura, matriz con filtros y panel lateral de detalle. */
export function CatalogoAdminPage() {
  const { q, tipo, area, cobertura, estado, page, competencia, update, abrir } = useListadoParams()
  const consulta = useCatalogo({
    q,
    tipo: tipo || undefined,
    area_formativa_id: area || undefined,
    cobertura: cobertura || undefined,
    estado,
    page,
  })
  const indicador = useCobertura()
  const areas = useAreas()

  const [borrador, setBorrador] = useState<string | null>(null)
  const [gestionandoAreas, setGestionandoAreas] = useState(false)
  const busqueda = borrador ?? q

  // La búsqueda se escribe sin demora y se aplica a la URL tras una breve pausa.
  useEffect(() => {
    if (borrador === null) return
    const espera = setTimeout(() => {
      update({ q: borrador.trim() })
      setBorrador(null)
    }, RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [borrador, update])

  const hayFiltros = q !== '' || tipo !== '' || area !== '' || cobertura !== '' || estado !== 'Activo'

  const limpiar = (
    <Button
      variant="secondary"
      onClick={() => {
        setBorrador(null)
        update({ q: '', tipo: '', area: '', cobertura: '', estado: 'Activo' })
      }}
    >
      {text.clearFilters}
    </Button>
  )

  const vacio = () =>
    hayFiltros ? (
      <EmptyState icon={<IconSchool size={24} />} title={text.noResults} description={text.noResultsDescription} action={limpiar} />
    ) : (
      <EmptyState icon={<IconSchool size={24} />} title={text.emptyTitle} description={text.emptyDescription} />
    )

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
        </div>
        <Button variant="secondary" onClick={() => setGestionandoAreas(true)}>
          {text.manageAreas}
        </Button>
      </div>

      {/* <Alert tone="neutral">
        <span className="font-semibold">{text.readOnlyNotice}</span> {text.readOnlyNoticeDetail}
      </Alert> */}

      <CoberturaIndicador
        cobertura={indicador.data}
        error={indicador.isError}
        onVerSinCobertura={() => update({ q: '', cobertura: 'sin_oferta', estado: 'Activo' })}
      />

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
          id="filtro-tipo"
          label={text.typeFilterLabel}
          hideLabel
          value={tipo}
          onChange={(event) => update({ tipo: event.target.value as TipoCompetencia | '' })}
          options={[
            { value: '', label: text.typeFilters.todos },
            { value: 'Cardinal', label: text.typeFilters.Cardinal },
            { value: 'Especifica', label: text.typeFilters.Especifica },
          ]}
        />
        <SelectField
          id="filtro-area"
          label={text.areaFilterLabel}
          hideLabel
          value={area === '' ? '' : String(area)}
          onChange={(event) => update({ area: event.target.value === '' ? '' : Number(event.target.value) })}
          options={[{ value: '', label: text.areaFilterAll }, ...(areas.data ?? []).map((a) => ({ value: String(a.id), label: a.nombre }))]}
        />
        <SelectField
          id="filtro-cobertura"
          label={text.coverageFilterLabel}
          hideLabel
          value={cobertura}
          onChange={(event) => update({ cobertura: event.target.value as FiltroCobertura | '' })}
          options={[
            { value: '', label: text.coverageFilters.todos },
            { value: 'con', label: text.coverageFilters.con },
            { value: 'sin', label: text.coverageFilters.sin },
            { value: 'sin_oferta', label: text.coverageFilters.sin_oferta },
          ]}
        />
        <SelectField
          id="filtro-estado"
          label={text.statusFilterLabel}
          hideLabel
          value={estado}
          onChange={(event) => update({ estado: event.target.value as FiltroEstado })}
          options={[
            { value: 'Activo', label: text.statusFilters.Activo },
            { value: 'Inactivo', label: text.statusFilters.Inactivo },
            { value: 'Todos', label: text.statusFilters.Todos },
          ]}
        />
      </div>

      {consulta.isPending ? (
        <div role="status" aria-label={text.loadingLabel} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
          {[0, 1, 2, 3].map((fila) => (
            <Skeleton key={fila} className="h-10" />
          ))}
        </div>
      ) : consulta.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={text.retry} onRetry={() => void consulta.refetch()} />
        </div>
      ) : consulta.data.data.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">{vacio()}</div>
      ) : (
        <>
          <CatalogoMatriz items={consulta.data.data} onVerDetalle={abrir} />
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

      {competencia !== null && <DetalleCompetenciaPanel key={competencia} competenciaId={competencia} onCerrar={() => abrir(null)} />}
      {gestionandoAreas && <GestionAreasDialog onCerrar={() => setGestionandoAreas(false)} />}
    </div>
  )
}
