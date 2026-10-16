import { useEffect, useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFolder, IconPlus } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { ESTADO_INICIAL, type Categoria, type FiltroEstadoCategoria } from '../api'
import { CategoriasTabla } from '../components/CategoriasTabla'
import { ConfirmarEstadoCategoria } from '../components/ConfirmarEstadoCategoria'
import { FormularioCategoria } from '../components/FormularioCategoria'
import { useCategorias } from '../hooks/useCategorias'
import { useCategoriasParams } from '../hooks/useCategoriasParams'
import { categoriasText } from '../text'

const RETARDO_BUSQUEDA_MS = 300
const text = categoriasText

type Dialogo =
  | { tipo: 'formulario'; categoria: Categoria | null }
  | { tipo: 'estado'; categoria: Categoria }
  | null

const OPCIONES_ESTADO = (['Activa', 'Inactiva', 'Todas'] as const).map((valor) => ({ value: valor, label: text.filtros.options[valor] }))

/** Categorías documentales del Administrador: listado con búsqueda, filtro de estado y paginación. */
export function CategoriasPage() {
  const { q, estado, page, update } = useCategoriasParams()
  const consulta = useCategorias({ q, estado, page })
  const [borrador, setBorrador] = useState<string | null>(null)
  const [dialogo, setDialogo] = useState<Dialogo>(null)
  const [aviso, setAviso] = useState<string | null>(null)
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

  const hayFiltros = q !== '' || estado !== ESTADO_INICIAL
  const filas = consulta.data?.data ?? []

  // La categoría de un diálogo se toma de los datos vigentes: tras «Actualizar datos» usa la versión nueva sin perder lo escrito.
  const vigente = (categoria: Categoria | null): Categoria | undefined =>
    categoria === null ? undefined : (filas.find((fila) => fila.id === categoria.id) ?? categoria)

  const abrir = (siguiente: Dialogo) => {
    setAviso(null)
    setDialogo(siguiente)
  }
  const cerrar = () => setDialogo(null)
  const exito = (mensaje: string) => {
    setDialogo(null)
    setAviso(mensaje)
  }
  const actualizar = () => void consulta.refetch()

  const nueva = (
    <Button onClick={() => abrir({ tipo: 'formulario', categoria: null })}>
      <IconPlus size={18} />
      {text.newButton}
    </Button>
  )

  const limpiarFiltros = (
    <Button
      variant="secondary"
      onClick={() => {
        setBorrador(null)
        update({ q: '', estado: ESTADO_INICIAL })
      }}
    >
      {text.filtros.clear}
    </Button>
  )

  let contenido
  if (consulta.isPending) {
    contenido = (
      <div role="status" aria-label={text.states.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2, 3].map((fila) => (
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
          <EmptyState
            icon={<IconFolder size={24} />}
            title={text.states.noResultsTitle}
            description={text.states.noResultsDescription}
            action={limpiarFiltros}
          />
        ) : (
          <EmptyState icon={<IconFolder size={24} />} title={text.states.emptyTitle} description={text.states.emptyDescription} action={nueva} />
        )}
      </div>
    )
  } else {
    contenido = (
      <>
        <CategoriasTabla
          categorias={filas}
          onEditar={(categoria) => abrir({ tipo: 'formulario', categoria })}
          onCambiarEstado={(categoria) => abrir({ tipo: 'estado', categoria })}
        />
        <Pagination
          page={consulta.data.meta.current_page}
          lastPage={consulta.data.meta.last_page}
          total={consulta.data.meta.total}
          from={consulta.data.meta.from}
          to={consulta.data.meta.to}
          onPageChange={(siguiente) => update({ page: siguiente })}
        />
      </>
    )
  }

  const categoriaDelDialogo = dialogo === null ? undefined : vigente(dialogo.categoria)

  return (
    <div className="mx-auto flex max-w-225 flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
        </div>
        {nueva}
      </div>

      {aviso && (
        <div role="status">
          <Alert tone="neutral">{aviso}</Alert>
        </div>
      )}

      <div className="flex flex-wrap items-end gap-3">
        <SearchField
          id="busqueda-categoria"
          label={text.filtros.searchLabel}
          placeholder={text.filtros.searchPlaceholder}
          value={busqueda}
          onChange={(event) => setBorrador(event.target.value)}
          maxLength={50}
          className="min-w-60 flex-1"
        />
        <SelectField
          id="filtro-estado-categoria"
          label={text.filtros.statusLabel}
          hideLabel
          value={estado}
          onChange={(event) => update({ estado: event.target.value as FiltroEstadoCategoria })}
          options={OPCIONES_ESTADO}
        />
      </div>

      {contenido}

      {dialogo?.tipo === 'formulario' && (
        <FormularioCategoria
          categoria={categoriaDelDialogo}
          onCerrar={cerrar}
          onGuardado={() => exito(dialogo.categoria === null ? text.success.created : text.success.updated)}
          onActualizar={actualizar}
        />
      )}
      {dialogo?.tipo === 'estado' && categoriaDelDialogo && (
        <ConfirmarEstadoCategoria
          categoria={categoriaDelDialogo}
          onCancelar={cerrar}
          onConfirmado={(guardada) => exito(guardada.estado === 'Inactiva' ? text.success.deactivated : text.success.reactivated)}
          onActualizar={actualizar}
        />
      )}
    </div>
  )
}
