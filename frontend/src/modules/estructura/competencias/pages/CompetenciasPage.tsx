import { useEffect, useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
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
import type { Competencia, TipoCompetencia } from '../api'
import { AvisoEmergente } from '../components/AvisoEmergente'
import { CompetenciaCard } from '../components/CompetenciaCard'
import { FormularioCompetencia } from '../components/FormularioCompetencia'
import { FormularioSeccion, type SeccionEditable } from '../components/FormularioSeccion'
import { PestanasCompetencia } from '../components/PestanasCompetencia'
import { useCambiarEstadoCompetencia } from '../hooks/useCambiarEstadoCompetencia'
import { useCompetencias } from '../hooks/useCompetencias'
import { useListadoParams } from '../hooks/useListadoParams'
import { competenciasText } from '../text'

const RETARDO_BUSQUEDA_MS = 300
const text = competenciasText.list

/** Competencias con sus grados, comportamientos y preguntas: tarjetas desplegables con tres pestañas. */
export function CompetenciasPage() {
  const { data: usuario } = useCurrentUser()
  const esAdmin = usuario?.role === 'admin'
  const { q, tipo, page, update } = useListadoParams()
  // No hay filtro de estado: el administrador ve activas e inactivas y el resto solo las activas (lo decide el backend).
  const consulta = useCompetencias({ q, tipo, page })
  const cambiarEstado = useCambiarEstadoCompetencia()

  // Lo que la persona escribe y aún no se aplicó a la URL; null = mostrar lo que dice la URL.
  const [borrador, setBorrador] = useState<string | null>(null)
  const busqueda = borrador ?? q
  const [abiertas, setAbiertas] = useState<ReadonlySet<number>>(new Set())
  const [creando, setCreando] = useState(false)
  const [edicion, setEdicion] = useState<{ id: number; seccion: SeccionEditable } | null>(null)
  const [aDesactivar, setADesactivar] = useState<Competencia | null>(null)
  const [errorDesactivar, setErrorDesactivar] = useState<string>()
  const [errorEstado, setErrorEstado] = useState<string>()
  const [aviso, setAviso] = useState<string>()

  // La búsqueda se escribe sin demora y se aplica a la URL tras una breve pausa.
  useEffect(() => {
    if (borrador === null) return
    const espera = setTimeout(() => {
      update({ q: borrador.trim(), tipo })
      setBorrador(null)
    }, RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [borrador, tipo, update])

  const hayFiltros = q !== '' || tipo !== undefined
  const pagina = consulta.data

  const alternar = (id: number) =>
    setAbiertas((actuales) => {
      const siguientes = new Set(actuales)
      if (siguientes.has(id)) siguientes.delete(id)
      else siguientes.add(id)
      return siguientes
    })

  const aplicarCambio = (competencia: Competencia, nuevo: 'Activo' | 'Inactivo') => {
    cambiarEstado.mutate(
      { id: competencia.id, estado: nuevo },
      {
        onSuccess: () => {
          setADesactivar(null)
          setAviso(nuevo === 'Activo' ? competenciasText.notices.activated : competenciasText.notices.deactivated)
        },
        onError: (error) => {
          const api = toApiError(error)
          if (nuevo === 'Inactivo') {
            setADesactivar(api.status === 404 ? null : competencia)
            setErrorDesactivar(competenciasText.confirmDeactivate.error)
            return
          }
          // Activar una competencia incompleta se rechaza indicando lo que falta.
          setErrorEstado(api.fieldErrors.estado?.[0] ?? api.message ?? text.statusError)
        },
      },
    )
  }

  // Desactivar pide confirmación; activar actúa de inmediato.
  const alCambiarEstado = (competencia: Competencia) => {
    setErrorEstado(undefined)
    if (competencia.estado === 'Activo') {
      setErrorDesactivar(undefined)
      setADesactivar(competencia)
    } else {
      aplicarCambio(competencia, 'Activo')
    }
  }

  const limpiarFiltros = () => {
    setBorrador(null)
    update({ q: '', tipo: '' })
  }

  const nuevaCompetencia = esAdmin && (
    <Button onClick={() => setCreando(true)}>
      <IconPlus size={18} />
      {text.newButton}
    </Button>
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
          onChange={(event) => update({ q, tipo: event.target.value as TipoCompetencia | '' })}
          options={[
            { value: '', label: text.allTypes },
            { value: 'Cardinal', label: competenciasText.types.Cardinal },
            { value: 'Especifica', label: competenciasText.types.Especifica },
          ]}
        />
        {hayFiltros && (
          <Button variant="secondary" onClick={limpiarFiltros}>
            {text.clearFilters}
          </Button>
        )}
      </div>

      {errorEstado && <Alert>{errorEstado}</Alert>}

      {consulta.isPending ? (
        <div role="status" aria-label={text.loadingLabel} className="grid grid-cols-[repeat(auto-fill,minmax(min(100%,26rem),1fr))] gap-3">
          {[0, 1, 2, 3].map((fila) => (
            <Skeleton key={fila} className="h-16" />
          ))}
        </div>
      ) : consulta.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={text.retry} onRetry={() => void consulta.refetch()} />
        </div>
      ) : pagina && pagina.data.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          {hayFiltros ? (
            <p className="px-5 py-6 text-[15px] text-muted">
              {text.noResults}{' '}
              <Button variant="secondary" className="ml-2" onClick={limpiarFiltros}>
                {text.clearFilters}
              </Button>
            </p>
          ) : (
            <EmptyState
              icon={<IconAward size={24} />}
              title={text.emptyTitle}
              description={esAdmin ? text.emptyAdminDescription : text.emptyDescription}
              action={nuevaCompetencia || undefined}
            />
          )}
        </div>
      ) : (
        pagina && (
          <>
            <div className="grid grid-cols-[repeat(auto-fill,minmax(min(100%,26rem),1fr))] items-start gap-3">
              {pagina.data.map((competencia) => {
                const abierta = abiertas.has(competencia.id)
                return (
                  <CompetenciaCard
                    key={competencia.id}
                    competencia={competencia}
                    abierta={abierta}
                    onToggle={() => alternar(competencia.id)}
                    esAdmin={esAdmin}
                    onCambiarEstado={() => alCambiarEstado(competencia)}
                    cambiando={cambiarEstado.isPending && cambiarEstado.variables?.id === competencia.id && aDesactivar === null}
                  >
                    <PestanasCompetencia
                      competenciaId={competencia.id}
                      nombre={competencia.nombre}
                      esAdmin={esAdmin}
                      onEditar={(seccion) => setEdicion({ id: competencia.id, seccion })}
                      onAviso={setAviso}
                    />
                  </CompetenciaCard>
                )
              })}
            </div>
            {pagina.meta.last_page > 1 && (
              <Pagination
                page={pagina.meta.current_page}
                lastPage={pagina.meta.last_page}
                total={pagina.meta.total}
                from={pagina.meta.from}
                to={pagina.meta.to}
                onPageChange={(siguiente) => update({ q, tipo, page: siguiente })}
              />
            )}
            <p aria-live="polite" className="text-sm text-muted">
              {text.count(pagina.meta.total)}
            </p>
          </>
        )
      )}

      {esAdmin && <FormularioCompetencia open={creando} onClose={() => setCreando(false)} onCreada={(mensaje) => { setCreando(false); setAviso(mensaje) }} />}

      {esAdmin && (
        <FormularioSeccion
          competenciaId={edicion?.id ?? null}
          seccion={edicion?.seccion ?? 'competencia'}
          onClose={() => setEdicion(null)}
          onGuardado={(mensaje) => {
            setEdicion(null)
            setAviso(mensaje)
          }}
        />
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

      <AvisoEmergente mensaje={aviso} onClose={() => setAviso(undefined)} />
    </div>
  )
}
