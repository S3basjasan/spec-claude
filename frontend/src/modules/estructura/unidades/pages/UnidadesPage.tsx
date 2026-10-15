import { useMemo, useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconPlus, IconSitemap, IconX } from '../../../../shared/ui/icons'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useCurrentUser } from '../../../auth/login'
import type { FiltroEstado, UnidadResumen } from '../api'
import { conAncestras, filasVisibles, idsExpandibles } from '../arbol'
import { CadenaDeTipos } from '../components/CadenaDeTipos'
import { DesactivarModal } from '../components/DesactivarModal'
import { EditarTiposModal } from '../components/EditarTiposModal'
import { NuevoTipoModal } from '../components/NuevoTipoModal'
import { RequireAdmin } from '../components/RequireAdmin'
import { UnidadesTree } from '../components/UnidadesTree'
import { UnidadModal, type ModoDeUnidad } from '../components/UnidadModal'
import { useCambiarEstadoUnidad } from '../hooks/useCambiarEstadoUnidad'
import { useExpansion } from '../hooks/useExpansion'
import { useListadoParams } from '../hooks/useListadoParams'
import { useTiposUnidad } from '../hooks/useTiposUnidad'
import { useUnidades } from '../hooks/useUnidades'
import { unidadesText } from '../text'

const text = unidadesText.list

type Modal =
  | { tipo: 'unidad'; modo: ModoDeUnidad }
  | { tipo: 'desactivar'; unidad: UnidadResumen }
  | { tipo: 'tipo-nuevo' }
  | { tipo: 'tipos' }
  | null
type Aviso = { tipo: 'ok' | 'error'; texto: string } | null

export function UnidadesPage() {
  const { data: usuario } = useCurrentUser()
  const esAdmin = usuario?.role === 'admin'
  const { q, estado, busqueda, escribir, cambiarEstado, limpiar, mostrarUnidad } = useListadoParams()
  // El Usuario nunca envía ni ve el filtro de estado: el backend solo le devuelve las activas.
  const consulta = useUnidades({ q, estado: esAdmin ? estado : undefined })
  const tipos = useTiposUnidad()
  const reactivacion = useCambiarEstadoUnidad()
  const { expandidas, alternar, expandir, expandirTodo, contraerTodo } = useExpansion()

  const [modal, setModal] = useState<Modal>(null)
  const [aviso, setAviso] = useState<Aviso>(null)

  const unidades = useMemo(() => consulta.data?.data ?? [], [consulta.data])
  // La búsqueda y el filtro de inactivas muestran todo expandido; las ramas no se pueden contraer.
  const expansionForzada = q !== '' || (esAdmin && estado === 'Inactiva')
  const visibles = useMemo(() => filasVisibles(unidades, expandidas, expansionForzada), [unidades, expandidas, expansionForzada])

  const advertencias = useMemo(() => {
    const porId = new Map(unidades.map((unidad) => [unidad.id, unidad]))
    const porNivel = new Map((tipos.data?.tipos ?? []).map((tipo) => [tipo.nivel, tipo]))
    const resultado = new Map<number, string>()
    for (const unidad of unidades) {
      const padre = unidad.unidad_padre ? porId.get(unidad.unidad_padre.id) : undefined
      if (!unidad.sigue_cadena && padre) {
        resultado.set(unidad.id, unidadesText.warning.notFollows(padre.tipo.nombre, porNivel.get(padre.tipo.nivel + 1)?.nombre ?? '—'))
      }
    }

    return resultado
  }, [unidades, tipos.data])

  const filtraEstado = esAdmin && estado !== 'Activa'
  const cerrarModal = () => setModal(null)
  const avisar = (texto: string) => setAviso({ tipo: 'ok', texto })

  const alGuardarUnidad = (padreId: number | null, editando: boolean) => {
    cerrarModal()
    // Al crear se expanden el padre y sus ancestras para que la unidad nueva se vea.
    if (padreId !== null) expandir(conAncestras(unidades, padreId))
    avisar(editando ? text.updated : text.created)
  }

  const reactivar = (unidad: UnidadResumen) => {
    if (reactivacion.isPending) return
    setAviso(null)
    reactivacion.mutate(
      { id: unidad.id, estado: 'Activa' },
      {
        onSuccess: () => avisar(text.reactivated),
        onError: (fallo) => {
          const api = toApiError(fallo)
          const mensaje = api.isNetworkError
            ? unidadesText.deactivate.networkError
            : api.status === 404
              ? text.notFound
              : api.status === 422
                ? (api.fieldErrors.estado?.[0] ?? api.message ?? unidadesText.deactivate.reactivateError)
                : unidadesText.deactivate.reactivateError
          setAviso({ tipo: 'error', texto: mensaje })
        },
      },
    )
  }

  const abrirUnidad = (modo: ModoDeUnidad) => {
    setAviso(null)
    setModal({ tipo: 'unidad', modo })
  }

  const verEnArbol = (codigo: string) => {
    cerrarModal()
    mostrarUnidad(codigo)
  }

  const crearUnidad = (
    <RequireAdmin>
      <Button onClick={() => abrirUnidad({ modo: 'crear' })}>
        <IconPlus size={18} />
        {text.newUnit}
      </Button>
    </RequireAdmin>
  )

  const limpiarFiltros = (
    <Button variant="secondary" onClick={limpiar}>
      {text.clearFilters}
    </Button>
  )

  const vacio = () => {
    if (q !== '') {
      return <EmptyState icon={<IconSitemap size={24} />} title={text.noResults} description={text.noResultsDescription} action={limpiarFiltros} />
    }
    if (filtraEstado) {
      return (
        <EmptyState icon={<IconSitemap size={24} />} title={text.emptyFilteredTitle} description={text.emptyFilteredDescription} action={limpiarFiltros} />
      )
    }

    return <EmptyState icon={<IconSitemap size={24} />} title={text.emptyTitle} description={text.emptyDescription} />
  }

  const resumen = consulta.data
    ? q !== ''
      ? text.summaryMatches(consulta.data.meta.coincidencias)
      : text.summary(unidades.length, unidades.filter((unidad) => unidad.estado === 'Activa').length)
    : null

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
        </div>
        <RequireAdmin>
          <div className="flex flex-wrap gap-3">
            <Button variant="secondary" onClick={() => setModal({ tipo: 'tipo-nuevo' })}>
              <IconPlus size={18} />
              {text.newType}
            </Button>
            {crearUnidad}
          </div>
        </RequireAdmin>
      </div>

      {aviso &&
        (aviso.tipo === 'ok' ? (
          <div role="status" className="flex items-center justify-between gap-3 rounded-lg bg-soft px-3.5 py-2 text-sm font-semibold text-accent">
            <span>{aviso.texto}</span>
            <button
              type="button"
              aria-label={text.closeNotice}
              onClick={() => setAviso(null)}
              className="inline-flex size-11 cursor-pointer items-center justify-center rounded-lg hover:bg-surface"
            >
              <IconX size={16} />
            </button>
          </div>
        ) : (
          <Alert>{aviso.texto}</Alert>
        ))}

      {tipos.data ? (
        <CadenaDeTipos tipos={tipos.data.tipos} esAdmin={esAdmin} onEditar={() => setModal({ tipo: 'tipos' })} />
      ) : tipos.isError ? (
        <Alert>{unidadesText.chain.loadErrorTitle}</Alert>
      ) : (
        <Skeleton className="h-24 rounded-xl" />
      )}

      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="flex min-w-70 flex-[1_1_480px] flex-wrap items-end gap-3">
          <SearchField
            id="busqueda"
            label={text.searchLabel}
            placeholder={text.searchPlaceholder}
            value={busqueda}
            onChange={(event) => escribir(event.target.value)}
            maxLength={50}
            className="min-w-70 max-w-90 flex-[1_1_280px]"
          />
          {esAdmin && (
            <SelectField
              id="filtro-estado"
              label={text.statusFilterLabel}
              hideLabel
              value={estado}
              onChange={(event) => cambiarEstado(event.target.value as FiltroEstado)}
              options={[
                { value: 'Activa', label: text.statusFilters.Activa },
                { value: 'Inactiva', label: text.statusFilters.Inactiva },
                { value: 'Todas', label: text.statusFilters.Todas },
              ]}
            />
          )}
          <Button variant="secondary" disabled={expansionForzada || unidades.length === 0} onClick={() => expandirTodo(idsExpandibles(unidades))}>
            {text.expandAll}
          </Button>
          <Button variant="secondary" disabled={expansionForzada || unidades.length === 0} onClick={contraerTodo}>
            {text.collapseAll}
          </Button>
        </div>
        {resumen && (
          <p role="status" aria-live="polite" className="text-sm text-muted">
            {resumen}
          </p>
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
          <ErrorState title={text.errorTitle} description={text.errorDescription} retryLabel={text.retry} onRetry={() => void consulta.refetch()} />
        </div>
      ) : unidades.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">{vacio()}</div>
      ) : (
        <UnidadesTree
          unidades={visibles}
          esAdmin={esAdmin}
          expandidas={expandidas}
          expansionForzada={expansionForzada}
          advertencias={advertencias}
          ocupadaId={reactivacion.isPending ? reactivacion.variables.id : undefined}
          onAlternar={(unidad) => alternar(unidad.id)}
          onEditar={(unidad) => abrirUnidad({ modo: 'editar', id: unidad.id })}
          onAgregar={(unidad) => abrirUnidad({ modo: 'crear', padreId: unidad.id })}
          onDesactivar={(unidad) => {
            setAviso(null)
            setModal({ tipo: 'desactivar', unidad })
          }}
          onReactivar={reactivar}
        />
      )}

      {modal?.tipo === 'unidad' && (
        <RequireAdmin>
          <UnidadModal
            key={modal.modo.modo === 'editar' ? `editar-${modal.modo.id}` : `crear-${modal.modo.padreId ?? 'nueva'}`}
            modo={modal.modo}
            onCerrar={cerrarModal}
            onGuardada={(guardada, editando) => alGuardarUnidad(guardada.unidad_padre?.id ?? null, editando)}
            onNoExiste={() => {
              cerrarModal()
              setAviso({ tipo: 'error', texto: text.notFound })
            }}
          />
        </RequireAdmin>
      )}
      {modal?.tipo === 'desactivar' && (
        <RequireAdmin>
          <DesactivarModal
            unidad={modal.unidad}
            onCerrar={cerrarModal}
            onDesactivada={(cantidad) => {
              cerrarModal()
              avisar(text.deactivated(cantidad))
            }}
          />
        </RequireAdmin>
      )}
      {modal?.tipo === 'tipo-nuevo' && (
        <RequireAdmin>
          <NuevoTipoModal
            nombresActuales={(tipos.data?.tipos ?? []).map((tipo) => tipo.nombre)}
            onCerrar={cerrarModal}
            onCreado={() => {
              cerrarModal()
              avisar(text.typeCreated)
            }}
          />
        </RequireAdmin>
      )}
      {modal?.tipo === 'tipos' && (
        <RequireAdmin>
          <EditarTiposModal
            onCerrar={cerrarModal}
            onGuardada={() => {
              cerrarModal()
              avisar(text.chainSaved)
            }}
            onVerEnArbol={verEnArbol}
          />
        </RequireAdmin>
      )}
    </div>
  )
}
