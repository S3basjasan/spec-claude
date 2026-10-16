import { useEffect, useMemo, useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText, IconPlus } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useCategoriasTodas } from '../../categorias'
import type { Documento, EstadoDocumento } from '../api'
import { mensajeDeApertura, useAbrirDocumento } from '../hooks/useAbrirDocumento'
import { useDocumentos } from '../hooks/useDocumentos'
import { useDocumentosParams } from '../hooks/useDocumentosParams'
import { useCambiarVisibilidad } from '../hooks/useGuardarDocumento'
import { documentosText } from '../text'
import { ConfirmarArchivo } from './ConfirmarArchivo'
import { DocumentosTabla, type FilaDeDocumento } from './DocumentosTabla'
import { FormularioDocumento } from './FormularioDocumento'

const RETARDO_BUSQUEDA_MS = 300
const text = documentosText

interface SeccionDocumentosProps {
  /** Ficha a mostrar; `null` = la propia (Mis documentos / Mi ficha), de solo lectura. */
  colaboradorId: number | null
}

type Dialogo = { tipo: 'subir' } | { tipo: 'archivar'; documento: Documento } | null

/**
 * Documentos de una ficha. Consulta su propio endpoint, así que un fallo muestra error con Reintentar sin ocultar el
 * resto de la ficha. El Administrador carga, cambia la visibilidad, archiva y ve los archivados; el Colaborador solo
 * abre y descarga lo suyo, visible y disponible.
 */
export function SeccionDocumentos({ colaboradorId }: SeccionDocumentosProps) {
  const administrador = colaboradorId !== null
  const { q, categoriaId, estado, page, update } = useDocumentosParams()
  const archivados = administrador && estado === 'Archivado'
  const consulta = useDocumentos(colaboradorId, { q, categoria_id: categoriaId ?? undefined, estado: administrador ? estado : undefined, page })
  const categorias = useCategoriasTodas(administrador)
  const abrir = useAbrirDocumento(colaboradorId)
  const visibilidad = useCambiarVisibilidad(colaboradorId ?? 0)

  const [borrador, setBorrador] = useState<string | null>(null)
  const [dialogo, setDialogo] = useState<Dialogo>(null)
  const [aviso, setAviso] = useState<string | null>(null)
  const [fallo, setFallo] = useState<string | null>(null)
  const [ocupadoId, setOcupadoId] = useState<number | null>(null)
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

  const datos = consulta.data?.data
  const filas: FilaDeDocumento[] = useMemo(() => datos ?? [], [datos])

  // Opciones de categoría: el catálogo completo para el Administrador; para el Colaborador, las de sus documentos.
  const opcionesDeCategoria = useMemo(() => {
    const mapa = new Map<number, string>()
    if (administrador) for (const categoria of categorias.data ?? []) mapa.set(categoria.id, categoria.nombre)
    else for (const fila of filas) if (fila.categoria) mapa.set(fila.categoria.id, fila.categoria.nombre)
    return [...mapa.entries()].map(([id, nombre]) => ({ value: String(id), label: nombre }))
  }, [administrador, categorias.data, filas])

  const hayFiltros = q !== '' || categoriaId !== null
  const limpiarFiltros = () => {
    setBorrador(null)
    update({ q: '', categoriaId: null })
  }

  const alAbrir = (fila: FilaDeDocumento, descargar: boolean) => {
    setFallo(null)
    setAviso(null)
    setOcupadoId(fila.id)
    abrir.mutate(
      { id: fila.id, titulo: fila.titulo, descargar },
      { onError: (error) => setFallo(mensajeDeApertura(error)), onSettled: () => setOcupadoId(null) },
    )
  }

  const alCambiarVisibilidad = (documento: Documento) => {
    setFallo(null)
    setAviso(null)
    setOcupadoId(documento.id)
    visibilidad.mutate(
      { id: documento.id, visible: !documento.visible_colaborador, version: documento.version },
      {
        onSuccess: () => setAviso(text.actions.visibilityChanged),
        onError: (error) => {
          setFallo(toApiError(error).status === 409 ? text.actions.conflict : text.actions.visibilityFailed)
          void consulta.refetch()
        },
        onSettled: () => setOcupadoId(null),
      },
    )
  }

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={text.states.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  }

  if (consulta.isError) {
    const api = toApiError(consulta.error)
    const sinFicha = api.code === 'CUENTA_SIN_FICHA'
    const sinPermiso = api.code === 'SIN_PERMISO_DOCUMENTOS'
    return (
      <div className="rounded-xl border border-border bg-surface">
        {sinFicha || sinPermiso ? (
          <EmptyState
            icon={<IconFileText size={24} />}
            title={sinFicha ? text.mine.noRecordTitle : text.mine.noPermissionTitle}
            description={sinFicha ? text.mine.noRecordDescription : text.mine.noPermissionDescription}
          />
        ) : (
          <ErrorState title={text.states.errorTitle} description={text.states.errorDescription} retryLabel={text.retry} onRetry={() => void consulta.refetch()} />
        )}
      </div>
    )
  }

  const subir = administrador && (
    <Button onClick={() => { setAviso(null); setDialogo({ tipo: 'subir' }) }}>
      <IconPlus size={18} />
      {text.uploadButton}
    </Button>
  )

  const cambiarEstado = (siguiente: EstadoDocumento) => update({ estado: siguiente, page: 1 })

  let contenido
  if (filas.length === 0) {
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        {hayFiltros ? (
          <EmptyState icon={<IconFileText size={24} />} title={text.states.noResultsTitle} description={text.states.noResultsDescription} action={<Button variant="secondary" onClick={limpiarFiltros}>{text.filters.clear}</Button>} />
        ) : (
          <EmptyState
            icon={<IconFileText size={24} />}
            title={archivados ? text.states.emptyArchivedTitle : text.states.emptyTitle}
            description={text.states.emptyDescription}
            action={archivados ? undefined : subir || undefined}
          />
        )}
      </div>
    )
  } else {
    contenido = (
      <>
        <DocumentosTabla
          filas={filas}
          administrador={administrador}
          archivados={archivados}
          ocupadoId={ocupadoId}
          onAbrir={(fila) => alAbrir(fila, false)}
          onDescargar={(fila) => alAbrir(fila, true)}
          onCambiarVisibilidad={alCambiarVisibilidad}
          onArchivar={(documento) => { setAviso(null); setDialogo({ tipo: 'archivar', documento }) }}
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

  // El documento del diálogo de archivar se toma de los datos vigentes: tras «actualizar» usa la versión nueva.
  const aArchivar = dialogo?.tipo === 'archivar' ? ((filas.find((f) => f.id === dialogo.documento.id) as Documento | undefined) ?? dialogo.documento) : null

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-base font-bold">{text.sectionTitle}</h2>
        {subir}
      </div>

      {aviso && (
        <div role="status">
          <Alert tone="neutral">{aviso}</Alert>
        </div>
      )}
      {abrir.isPending && (
        <div role="status">
          <Alert tone="neutral">{text.actions.opening}</Alert>
        </div>
      )}
      {fallo && <Alert>{fallo}</Alert>}

      {administrador && (
        <div role="group" aria-label={text.filters.statusGroup} className="flex gap-2">
          <Button variant="secondary" aria-pressed={!archivados} onClick={() => cambiarEstado('Disponible')}>
            {text.filters.available}
          </Button>
          <Button variant="secondary" aria-pressed={archivados} onClick={() => cambiarEstado('Archivado')}>
            {text.filters.archived}
          </Button>
        </div>
      )}

      <div className="flex flex-wrap items-end gap-3">
        <SearchField
          id="busqueda-documento"
          label={text.filters.searchLabel}
          placeholder={text.filters.searchPlaceholder}
          value={busqueda}
          onChange={(evento) => setBorrador(evento.target.value)}
          maxLength={50}
          className="min-w-60 flex-1"
        />
        <SelectField
          id="filtro-categoria-documento"
          label={text.filters.categoryLabel}
          hideLabel
          value={categoriaId === null ? '' : String(categoriaId)}
          onChange={(evento) => update({ categoriaId: evento.target.value === '' ? null : Number(evento.target.value) })}
          options={[{ value: '', label: text.filters.categoryAll }, ...opcionesDeCategoria]}
        />
      </div>

      {contenido}

      {administrador && dialogo?.tipo === 'subir' && (
        <FormularioDocumento
          colaboradorId={colaboradorId}
          onCerrar={() => setDialogo(null)}
          onCargado={() => {
            setDialogo(null)
            setAviso(text.actions.uploaded)
          }}
        />
      )}
      {administrador && aArchivar && (
        <ConfirmarArchivo
          colaboradorId={colaboradorId}
          documento={aArchivar}
          onCancelar={() => setDialogo(null)}
          onArchivado={() => {
            setDialogo(null)
            setAviso(text.actions.archived)
          }}
          onActualizar={() => void consulta.refetch()}
        />
      )}
    </div>
  )
}
