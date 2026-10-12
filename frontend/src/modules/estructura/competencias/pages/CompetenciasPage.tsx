import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconAward, IconPlus } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useCurrentUser } from '../../../auth/login'
import type { Competencia, FiltroEstado, TipoCompetencia } from '../api'
import { CompetenciasTable } from '../components/CompetenciasTable'
import { useCambiarEstadoCompetencia } from '../hooks/useCambiarEstadoCompetencia'
import { useCompetencias } from '../hooks/useCompetencias'
import { useListadoParams } from '../hooks/useListadoParams'
import { competenciasText } from '../text'

const RETARDO_BUSQUEDA_MS = 300
const text = competenciasText.list

export function CompetenciasPage() {
  const location = useLocation()
  const { data: usuario } = useCurrentUser()
  const esAdmin = usuario?.role === 'admin'
  const { q, tipo, estado, page, update } = useListadoParams()
  // El Usuario nunca envía ni ve el filtro de estado: el backend solo le devuelve las activas.
  const consulta = useCompetencias({ q, tipo, estado: esAdmin ? estado : undefined, page })
  const cambiarEstado = useCambiarEstadoCompetencia()

  // Lo que la persona escribe y aún no se aplicó a la URL; null = mostrar lo que dice la URL.
  const [borrador, setBorrador] = useState<string | null>(null)
  const busqueda = borrador ?? q
  const [aDesactivar, setADesactivar] = useState<Competencia | null>(null)
  const [errorDesactivar, setErrorDesactivar] = useState<string>()
  const [aviso, setAviso] = useState<string | undefined>((location.state as { aviso?: string } | null)?.aviso)

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
  const pagina = consulta.data

  const aplicarCambio = (competencia: Competencia, nuevo: 'Activo' | 'Inactivo') => {
    cambiarEstado.mutate(
      { id: competencia.id, estado: nuevo },
      {
        onSuccess: () => {
          setADesactivar(null)
          setAviso(nuevo === 'Activo' ? text.activated : text.deactivated)
          // Si era la última de la página, se retrocede una.
          if (pagina && pagina.data.length === 1 && page > 1) update({ q, tipo, estado, page: page - 1 })
        },
        onError: (error) => {
          const { status } = toApiError(error)
          if (status === 404) {
            setADesactivar(null)
            setAviso(text.alreadyGone)
          } else if (status === 422) {
            setADesactivar(null)
            setAviso(text.alreadyInState)
          } else if (nuevo === 'Inactivo') {
            setErrorDesactivar(competenciasText.confirmDeactivate.error)
          } else {
            setAviso(text.statusError)
          }
        },
      },
    )
  }

  // Desactivar pide confirmación; activar actúa de inmediato.
  const alCambiarEstado = (competencia: Competencia) => {
    setAviso(undefined)
    if (competencia.estado === 'Activo') {
      setErrorDesactivar(undefined)
      setADesactivar(competencia)
    } else {
      aplicarCambio(competencia, 'Activo')
    }
  }

  const nuevaCompetencia = esAdmin && (
    <Link
      to="/competencias/nueva"
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
        {nuevaCompetencia}
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
            { value: 'Cardinal', label: competenciasText.types.Cardinal },
            { value: 'Especifica', label: competenciasText.types.Especifica },
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
              icon={<IconAward size={24} />}
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
              icon={<IconAward size={24} />}
              title={esAdmin ? text.noActiveTitle : text.emptyTitle}
              description={esAdmin ? `${text.emptyAdminDescription} ${text.noActiveAdminDescription}` : text.emptyDescription}
              action={nuevaCompetencia || undefined}
            />
          )}
        </div>
      ) : (
        <>
          <CompetenciasTable
            items={consulta.data.data}
            esAdmin={esAdmin}
            onCambiarEstado={alCambiarEstado}
            cambiandoId={cambiarEstado.isPending && aDesactivar === null ? (cambiarEstado.variables?.id ?? null) : null}
          />
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

      <ConfirmDialog
        open={aDesactivar !== null}
        title={competenciasText.confirmDeactivate.title(aDesactivar?.nombre ?? '')}
        description={competenciasText.confirmDeactivate.description}
        confirmLabel={competenciasText.confirmDeactivate.confirm}
        cancelLabel={competenciasText.confirmDeactivate.cancel}
        busy={cambiarEstado.isPending}
        error={errorDesactivar}
        onConfirm={() => aDesactivar && aplicarCambio(aDesactivar, 'Inactivo')}
        onCancel={() => {
          setADesactivar(null)
          setErrorDesactivar(undefined)
        }}
      />
    </div>
  )
}
