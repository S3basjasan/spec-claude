import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconHelp, IconPlus } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useCurrentUser } from '../../../auth/login'
import type { FiltroEstado, TipoCompetencia } from '../api'
import { PreguntasTable } from '../components/PreguntasTable'
import { usePreguntas } from '../hooks/usePreguntas'
import { useListadoParams } from '../hooks/useListadoParams'
import { preguntasText } from '../text'

const RETARDO_BUSQUEDA_MS = 300
const text = preguntasText.list

export function PreguntasPage() {
  const location = useLocation()
  const { data: usuario } = useCurrentUser()
  const esAdmin = usuario?.role === 'admin'
  const { q, tipo, estado, page, update } = useListadoParams()
  // El Usuario nunca envía ni ve el filtro de estado: el backend solo le devuelve las activas.
  const consulta = usePreguntas({ q, tipo, estado: esAdmin ? estado : undefined, page })

  // Lo que la persona escribe y aún no se aplicó a la URL; null = mostrar lo que dice la URL.
  const [borrador, setBorrador] = useState<string | null>(null)
  const busqueda = borrador ?? q
  const aviso = (location.state as { aviso?: string } | null)?.aviso

  // La búsqueda se escribe sin demora y se aplica a la URL tras una breve pausa.
  useEffect(() => {
    if (borrador === null) return
    const espera = setTimeout(() => {
      update({ q: borrador.trim(), tipo, estado })
      setBorrador(null)
    }, RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [borrador, tipo, estado, update])

  const hayFiltros = q !== '' || tipo !== undefined || (esAdmin && estado !== 'Activo')

  const nuevoRegistro = esAdmin && (
    <Link
      to="/preguntas/nuevo"
      className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-accent px-5 text-[15px] font-semibold text-onaccent hover:bg-accent-hover"
    >
      <IconPlus size={18} />
      {text.newButton}
    </Link>
  )

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
        </div>
        {nuevoRegistro}
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
          id="filtro-tipo"
          label={text.typeFilterLabel}
          hideLabel
          value={tipo ?? ''}
          onChange={(event) => update({ q, tipo: event.target.value as TipoCompetencia | '', estado })}
          options={[
            { value: '', label: text.allTypes },
            { value: 'Cardinal', label: preguntasText.types.Cardinal },
            { value: 'Especifica', label: preguntasText.types.Especifica },
          ]}
        />
        {esAdmin && (
          <SelectField
            id="filtro-estado"
            label={text.statusFilterLabel}
            hideLabel
            value={estado}
            onChange={(event) => update({ q, tipo, estado: event.target.value as FiltroEstado })}
            options={[
              { value: 'Activo', label: text.statusFilters.Activo },
              { value: 'Inactivo', label: text.statusFilters.Inactivo },
              { value: 'Todos', label: text.statusFilters.Todos },
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
            retryLabel={text.retry}
            onRetry={() => void consulta.refetch()}
          />
        </div>
      ) : consulta.data.data.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          {hayFiltros ? (
            <EmptyState
              icon={<IconHelp size={24} />}
              title={text.noResultsTitle}
              description={text.noResultsDescription}
              action={
                <Button
                  variant="secondary"
                  onClick={() => {
                    setBorrador(null)
                    update({ q: '', tipo: '', estado: 'Activo' })
                  }}
                >
                  {text.clearFilters}
                </Button>
              }
            />
          ) : (
            <EmptyState
              icon={<IconHelp size={24} />}
              title={text.emptyTitle}
              description={esAdmin ? text.emptyAdminDescription : text.emptyDescription}
              action={nuevoRegistro || undefined}
            />
          )}
        </div>
      ) : (
        <>
          <PreguntasTable items={consulta.data.data} esAdmin={esAdmin} />
          <Pagination
            page={consulta.data.meta.current_page}
            lastPage={consulta.data.meta.last_page}
            total={consulta.data.meta.total}
            from={consulta.data.meta.from}
            to={consulta.data.meta.to}
            onPageChange={(siguiente) => update({ q, tipo, estado, page: siguiente })}
          />
        </>
      )}
    </div>
  )
}
