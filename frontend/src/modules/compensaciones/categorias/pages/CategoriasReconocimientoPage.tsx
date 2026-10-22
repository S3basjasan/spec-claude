import { useEffect, useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconAward, IconPlus, IconX } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField, type SelectOption } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useCurrentUser } from '../../../auth/login'
import { ESTADO_INICIAL, LIMITE_BUSQUEDA, type Categoria, type FiltroEstadoCategoria } from '../api'
import { CategoriaFormModal } from '../components/CategoriaFormModal'
import { CategoriasTabla } from '../components/CategoriasTabla'
import { ConfirmarDesactivacion } from '../components/ConfirmarDesactivacion'
import { DetalleCategoria } from '../components/DetalleCategoria'
import { EditarCategoria } from '../components/EditarCategoria'
import { useCategorias, useCompetenciasFiltro } from '../hooks/useCategorias'
import { useCambiarEstadoCategoria } from '../hooks/useGuardarCategoria'
import { useListadoParams } from '../hooks/useListadoParams'
import { categoriasReconocimientoText } from '../text'

const RETARDO_BUSQUEDA_MS = 300
const text = categoriasReconocimientoText

type Dialogo = { tipo: 'detalle'; id: number } | { tipo: 'crear' } | { tipo: 'editar'; id: number } | { tipo: 'desactivar'; id: number } | null
type Aviso = { tipo: 'ok' | 'error'; texto: string } | null

const OPCIONES_ESTADO: SelectOption[] = (['Todos', 'Activo', 'Inactivo'] as const).map((valor) => ({
  value: valor,
  label: text.filtros.estados[valor],
}))

/** Categorías de reconocimiento: todos consultan; el Administrador crea, edita, activa y desactiva. */
export function CategoriasReconocimientoPage() {
  const { data: usuario } = useCurrentUser()
  const esAdmin = usuario?.role === 'admin'
  const { q, competencia, estado, page, update } = useListadoParams()

  // Quien no administra el catálogo siempre ve solo las activas, aunque la URL pida otro estado.
  const estadoEfectivo: FiltroEstadoCategoria = esAdmin ? estado : ESTADO_INICIAL
  const consulta = useCategorias({ q, competencia, estado: estadoEfectivo, page })
  const competencias = useCompetenciasFiltro()
  const cambiarEstado = useCambiarEstadoCategoria()

  const [borrador, setBorrador] = useState<string | null>(null)
  const [dialogo, setDialogo] = useState<Dialogo>(null)
  const [aviso, setAviso] = useState<Aviso>(null)
  const busqueda = borrador ?? q

  // La búsqueda se escribe sin demora y se aplica a la URL tras una breve pausa (300 ms).
  useEffect(() => {
    if (borrador === null) return
    const espera = setTimeout(() => {
      update({ q: borrador.trim() })
      setBorrador(null)
    }, RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [borrador, update])

  const filas = consulta.data?.data ?? []
  const hayFiltros = q !== '' || competencia !== undefined || (esAdmin && estado !== ESTADO_INICIAL)
  const opcionesCompetencia: SelectOption[] = [
    { value: '', label: text.filtros.todasLasCompetencias },
    ...(competencias.data ?? []).map((c) => ({
      value: String(c.id),
      label: c.estado === 'Inactivo' ? text.competenciaInactivaOpcion(c.nombre) : c.nombre,
    })),
  ]

  // La categoría de un diálogo se toma de los datos vigentes del listado.
  const vigente = (id: number): Categoria | undefined => filas.find((fila) => fila.id === id)

  const abrir = (siguiente: Dialogo) => {
    setAviso(null)
    setDialogo(siguiente)
  }
  const cerrar = () => setDialogo(null)
  const exito = (texto: string) => {
    setDialogo(null)
    setAviso({ tipo: 'ok', texto })
  }
  const actualizar = () => void consulta.refetch()

  const activar = (categoria: Categoria) => {
    setAviso(null)
    cambiarEstado.mutate(
      { id: categoria.id, version: categoria.updated_at, estado: 'Activo' },
      {
        onSuccess: (guardada) => exito(text.success.activated(guardada.nombre)),
        onError: (e) => {
          const conflicto = toApiError(e).status === 409
          setAviso({ tipo: 'error', texto: conflicto ? text.errors.changed : text.errors.stateFailed })
          if (conflicto) actualizar()
        },
      },
    )
  }

  const alternarEstado = (categoria: Categoria) => {
    if (categoria.estado === 'Activo') abrir({ tipo: 'desactivar', id: categoria.id })
    else activar(categoria)
  }

  const nueva = esAdmin ? (
    <Button onClick={() => abrir({ tipo: 'crear' })}>
      <IconPlus size={18} />
      {text.newButton}
    </Button>
  ) : null

  let contenido
  if (consulta.isPending) {
    contenido = (
      <div role="status" aria-label={text.states.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2, 3, 4, 5].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  } else if (consulta.isError) {
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        <ErrorState
          title={text.states.errorTitle}
          description={text.states.errorDescription}
          retryLabel={text.retry}
          onRetry={() => void consulta.refetch()}
        />
      </div>
    )
  } else if (filas.length === 0) {
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        {hayFiltros ? (
          <EmptyState icon={<IconAward size={24} />} title={text.states.noResultsTitle} description={text.states.noResultsDescription} />
        ) : (
          <EmptyState
            icon={<IconAward size={24} />}
            title={text.states.emptyTitle}
            description={esAdmin ? text.states.emptyAdmin : text.states.emptyUsuario}
            action={nueva ?? undefined}
          />
        )}
      </div>
    )
  } else {
    contenido = (
      <>
        <CategoriasTabla
          categorias={filas}
          esAdmin={esAdmin}
          onVer={(categoria) => abrir({ tipo: 'detalle', id: categoria.id })}
          onEditar={(categoria) => abrir({ tipo: 'editar', id: categoria.id })}
          onCambiarEstado={alternarEstado}
        />
        <Pagination
          page={consulta.data.meta.page}
          lastPage={consulta.data.meta.last_page}
          total={consulta.data.meta.total}
          from={consulta.data.meta.from}
          to={consulta.data.meta.to}
          onPageChange={(siguiente) => update({ page: siguiente })}
        />
      </>
    )
  }

  const desactivando = dialogo?.tipo === 'desactivar' ? vigente(dialogo.id) : undefined

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{esAdmin ? text.subtitle.admin : text.subtitle.usuario}</p>
        </div>
        {nueva}
      </div>

      <div role="status" aria-live="polite">
        {aviso?.tipo === 'ok' && (
          <Alert tone="neutral">
            <span className="flex items-start justify-between gap-3">
              <span>{aviso.texto}</span>
              <button type="button" aria-label={text.errors.closeNotice} onClick={() => setAviso(null)} className="cursor-pointer text-accent">
                <IconX size={18} aria-hidden="true" />
              </button>
            </span>
          </Alert>
        )}
      </div>
      {aviso?.tipo === 'error' && (
        <Alert>
          <span className="flex items-start justify-between gap-3">
            <span>{aviso.texto}</span>
            <button type="button" aria-label={text.errors.closeNotice} onClick={() => setAviso(null)} className="cursor-pointer text-danger">
              <IconX size={18} aria-hidden="true" />
            </button>
          </span>
        </Alert>
      )}

      <div className="flex flex-wrap items-end gap-3">
        <SearchField
          id="busqueda-categoria-reconocimiento"
          label={text.filtros.searchLabel}
          placeholder={text.filtros.searchPlaceholder}
          value={busqueda}
          onChange={(event) => setBorrador(event.target.value.slice(0, LIMITE_BUSQUEDA))}
          maxLength={LIMITE_BUSQUEDA}
          className="min-w-60 max-w-95 flex-1"
        />
        <SelectField
          id="filtro-competencia-categoria"
          label={text.filtros.competenciaLabel}
          value={competencia === undefined ? '' : String(competencia)}
          onChange={(event) => update({ competencia: event.target.value === '' ? null : Number(event.target.value) })}
          options={opcionesCompetencia}
        />
        {esAdmin && (
          <SelectField
            id="filtro-estado-categoria-reconocimiento"
            label={text.filtros.estadoLabel}
            value={estado}
            onChange={(event) => update({ estado: event.target.value as FiltroEstadoCategoria })}
            options={OPCIONES_ESTADO}
          />
        )}
        {consulta.data && consulta.data.meta.total > 0 && (
          <span className="ml-auto pb-3 text-sm text-muted">{text.filtros.count(consulta.data.meta.total)}</span>
        )}
      </div>

      {contenido}

      <p className="text-sm text-muted">{text.pointsNote}</p>

      {dialogo?.tipo === 'detalle' && (
        <DetalleCategoria
          id={dialogo.id}
          esAdmin={esAdmin}
          onCerrar={cerrar}
          onEditar={(categoria) => abrir({ tipo: 'editar', id: categoria.id })}
        />
      )}
      {dialogo?.tipo === 'crear' && (
        <CategoriaFormModal onCerrar={cerrar} onGuardado={(guardada) => exito(text.success.created(guardada.nombre, guardada.codigo))} />
      )}
      {dialogo?.tipo === 'editar' && (
        <EditarCategoria id={dialogo.id} onCerrar={cerrar} onGuardado={(guardada) => exito(text.success.updated(guardada.nombre))} />
      )}
      {desactivando && (
        <ConfirmarDesactivacion
          categoria={desactivando}
          onCancelar={cerrar}
          onConfirmado={(guardada) => exito(text.success.deactivated(guardada.nombre))}
          onActualizar={actualizar}
        />
      )}
    </div>
  )
}
