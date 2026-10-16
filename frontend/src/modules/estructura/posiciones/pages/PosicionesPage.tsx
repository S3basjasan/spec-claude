import { useEffect, useMemo, useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconPlus, IconUsers } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField, type SelectOption } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useCurrentUser } from '../../../auth/login'
import type { FiltroEstado, PosicionDetalle } from '../api'
import { PosicionesList } from '../components/PosicionesList'
import { PosicionModal, type ModoDePosicion } from '../components/PosicionModal'
import { RequireAdmin } from '../components/RequireAdmin'
import { SuprimirDialog } from '../components/SuprimirDialog'
import { useListadoParams } from '../hooks/useListadoParams'
import { usePosiciones, useUnidadesParaFiltro } from '../hooks/usePosiciones'
import { posicionesText } from '../text'

const RETARDO_BUSQUEDA_MS = 300
const text = posicionesText.list

type Modal = { tipo: 'posicion'; modo: ModoDePosicion } | { tipo: 'suprimir'; posicion: PosicionDetalle } | null

/** Sangría de texto de una opción según su nivel (los `<option>` no admiten estilos de sangría fiables). */
const sangria = (nivel: number) => '   '.repeat(nivel)

export function PosicionesPage() {
  const { data: usuario } = useCurrentUser()
  const esAdmin = usuario?.role === 'admin'
  const { q, estado: estadoUrl, unidad, page, update } = useListadoParams()
  // Suprimida y Todas son solo del Administrador: para el Usuario se tratan como Vigentes.
  const estado: FiltroEstado = esAdmin || estadoUrl === 'Ocupada' || estadoUrl === 'Vacante' ? estadoUrl : 'Vigentes'
  const consulta = usePosiciones({
    q,
    estado: estado === 'Vigentes' ? undefined : estado,
    unidad_organizacional_id: unidad ?? undefined,
    page,
  })
  const unidades = useUnidadesParaFiltro()

  // Lo que la persona escribe y aún no se aplicó a la URL; null = mostrar lo que dice la URL.
  const [borrador, setBorrador] = useState<string | null>(null)
  const busqueda = borrador ?? q
  const [modal, setModal] = useState<Modal>(null)
  const [aviso, setAviso] = useState<string | null>(null)

  // La búsqueda se escribe sin demora y se aplica a la URL tras una breve pausa.
  useEffect(() => {
    if (borrador === null) return
    const espera = setTimeout(() => {
      update({ q: borrador.trim(), estado: estadoUrl, unidad })
      setBorrador(null)
    }, RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [borrador, estadoUrl, unidad, update])

  const opcionesEstado: SelectOption[] = useMemo(() => {
    const valores: FiltroEstado[] = esAdmin ? ['Vigentes', 'Ocupada', 'Vacante', 'Suprimida', 'Todas'] : ['Vigentes', 'Ocupada', 'Vacante']
    return valores.map((valor) => ({ value: valor, label: posicionesText.statusFilters[valor] }))
  }, [esAdmin])

  const opcionesUnidad: SelectOption[] = useMemo(
    () => [
      { value: '', label: text.allUnits },
      ...(unidades.data ?? []).map((u) => ({ value: String(u.id), label: `${sangria(u.nivel_arbol)}${u.nombre} (${u.codigo})` })),
    ],
    [unidades.data],
  )

  const hayFiltros = q !== '' || estado !== 'Vigentes' || unidad !== null
  const cerrarModal = () => setModal(null)
  const avisar = (texto: string) => setAviso(texto)

  const abrirPosicion = (modo: ModoDePosicion) => {
    setAviso(null)
    setModal({ tipo: 'posicion', modo })
  }

  const nuevaPosicion = (
    <RequireAdmin>
      <Button onClick={() => abrirPosicion({ modo: 'crear' })}>
        <IconPlus size={18} />
        {text.newButton}
      </Button>
    </RequireAdmin>
  )

  const limpiarFiltros = (
    <Button
      variant="secondary"
      onClick={() => {
        setBorrador(null)
        update({ q: '', estado: 'Vigentes', unidad: null })
      }}
    >
      {text.clearFilters}
    </Button>
  )

  const vacio = () => {
    if (hayFiltros) {
      return (
        <EmptyState icon={<IconUsers size={24} />} title={text.noResults} description={text.noResultsDescription} action={limpiarFiltros} />
      )
    }
    return (
      <EmptyState
        icon={<IconUsers size={24} />}
        title={text.emptyTitle}
        description={esAdmin ? text.emptyAdminDescription : text.emptyDescription}
        action={esAdmin ? nuevaPosicion : undefined}
      />
    )
  }

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
        </div>
        {nuevaPosicion}
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
          value={estado}
          onChange={(event) => update({ q, estado: event.target.value as FiltroEstado, unidad })}
          options={opcionesEstado}
        />
        <SelectField
          id="filtro-unidad"
          label={text.unitFilterLabel}
          value={unidad === null ? '' : String(unidad)}
          onChange={(event) => update({ q, estado: estadoUrl, unidad: event.target.value === '' ? null : Number(event.target.value) })}
          options={opcionesUnidad}
          className="max-w-80"
        />
        {hayFiltros && limpiarFiltros}
      </div>

      {consulta.data && (
        <p role="status" aria-live="polite" className="-mt-2 text-sm text-muted">
          {text.count(consulta.data.meta.total)}
        </p>
      )}

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
        <div className="rounded-xl border border-border bg-surface">{vacio()}</div>
      ) : (
        <>
          <PosicionesList
            items={consulta.data.data}
            esAdmin={esAdmin}
            onEditar={(id) => abrirPosicion({ modo: 'editar', id })}
            onSuprimir={(posicion) => {
              setAviso(null)
              setModal({ tipo: 'suprimir', posicion })
            }}
          />
          <Pagination
            page={consulta.data.meta.current_page}
            lastPage={consulta.data.meta.last_page}
            total={consulta.data.meta.total}
            from={consulta.data.meta.from}
            to={consulta.data.meta.to}
            onPageChange={(siguiente) => update({ q, estado: estadoUrl, unidad, page: siguiente })}
          />
        </>
      )}

      {esAdmin && modal?.tipo === 'posicion' && (
        <PosicionModal
          modo={modal.modo}
          onCerrar={cerrarModal}
          onGuardada={(_, editando) => {
            cerrarModal()
            avisar(editando ? text.updated : text.created)
          }}
          onNoExiste={() => {
            cerrarModal()
            avisar(posicionesText.modal.notFound)
          }}
        />
      )}
      {esAdmin && modal?.tipo === 'suprimir' && (
        <SuprimirDialog
          posicion={modal.posicion}
          onCerrar={cerrarModal}
          onSuprimida={() => {
            cerrarModal()
            avisar(text.suppressed)
          }}
        />
      )}
    </div>
  )
}
