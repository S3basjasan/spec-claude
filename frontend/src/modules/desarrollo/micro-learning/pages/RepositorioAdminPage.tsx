import { useEffect, useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconPlayerPlay } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { FilaMicro } from '../api'
import { AvisoConocimientos } from '../components/AvisoConocimientos'
import { FiltrosRepositorio } from '../components/FiltrosRepositorio'
import { FormularioMicro } from '../components/FormularioMicro'
import { HistorialMicro } from '../components/HistorialMicro'
import { TarjetaMicro } from '../components/TarjetaMicro'
import { VistaPreviaMicro } from '../components/VistaPreviaMicro'
import { useListadoParams } from '../hooks/useListadoParams'
import { useCambiarEstado, useEliminarMicro } from '../hooks/useMutacionesMicro'
import { useOpcionesMicro, useRepositorio } from '../hooks/useMicros'
import { microText } from '../text'

const RETARDO_BUSQUEDA_MS = 300
const text = microText.admin

/** Mensaje de un rechazo del servidor al publicar, archivar o eliminar (con los datos que faltan si los informa). */
function mensajeDe(error: unknown, porDefecto: string): string {
  const api = toApiError(error)
  const faltantes = Array.isArray(api.data?.faltantes) ? (api.data.faltantes as string[]) : []
  if (faltantes.length > 0) {
    return `${microText.card.missing} ${faltantes.map((f) => microText.card.missingItems[f] ?? f).join(', ')}.`
  }
  return api.message ?? porDefecto
}

/** Repositorio del Administrador: cuadrícula de tarjetas con búsqueda, filtros y el ciclo de vida de cada contenido. */
export function RepositorioAdminPage() {
  const { q, tipo, estado, competencia, page, update } = useListadoParams()
  const consulta = useRepositorio({ q, tipo: tipo || undefined, estado, competencia_id: competencia || undefined, page })
  const opciones = useOpcionesMicro()
  const cambiarEstado = useCambiarEstado()
  const eliminar = useEliminarMicro()

  const [borrador, setBorrador] = useState<string | null>(null)
  const [formulario, setFormulario] = useState<{ id: number | null } | null>(null)
  const [previa, setPrevia] = useState<FilaMicro | null>(null)
  const [historial, setHistorial] = useState<FilaMicro | null>(null)
  const [archivando, setArchivando] = useState<FilaMicro | null>(null)
  const [eliminando, setEliminando] = useState<FilaMicro | null>(null)
  const [aviso, setAviso] = useState<{ tono: 'ok' | 'error'; texto: string } | null>(null)
  const [errorDialogo, setErrorDialogo] = useState<string>()
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

  const hayFiltros = q !== '' || tipo !== '' || estado !== 'Activos' || competencia !== ''
  const ocupado = cambiarEstado.isPending || eliminar.isPending

  const publicar = (micro: FilaMicro) => {
    setAviso(null)
    cambiarEstado.mutate(
      { id: micro.id, estado: 'Publicado' },
      {
        onSuccess: () => setAviso({ tono: 'ok', texto: `«${micro.titulo}»: ${microText.card.published}` }),
        onError: (e) => setAviso({ tono: 'error', texto: `«${micro.titulo}»: ${mensajeDe(e, microText.card.publishFailed)}` }),
      },
    )
  }

  const confirmarArchivar = () => {
    if (!archivando) return
    setErrorDialogo(undefined)
    cambiarEstado.mutate(
      { id: archivando.id, estado: 'Archivado' },
      {
        onSuccess: () => setArchivando(null),
        onError: (e) => setErrorDialogo(mensajeDe(e, microText.archiveDialog.failed)),
      },
    )
  }

  const confirmarEliminar = () => {
    if (!eliminando) return
    setErrorDialogo(undefined)
    eliminar.mutate(eliminando.id, {
      onSuccess: () => setEliminando(null),
      onError: (e) => setErrorDialogo(toApiError(e).code === 'con_progreso' ? microText.deleteDialog.hasProgress : mensajeDe(e, microText.deleteDialog.failed)),
    })
  }

  const limpiar = (
    <Button
      variant="secondary"
      onClick={() => {
        setBorrador(null)
        update({ q: '', tipo: '', estado: 'Activos', competencia: '' })
      }}
    >
      {text.clearFilters}
    </Button>
  )

  return (
    <div className="mx-auto flex max-w-300 flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
        </div>
        <Button onClick={() => setFormulario({ id: null })}>{text.newContent}</Button>
      </div>

      {aviso && (aviso.tono === 'error' ? <Alert>{aviso.texto}</Alert> : <Alert tone="neutral">{aviso.texto}</Alert>)}

      <FiltrosRepositorio
        busqueda={busqueda}
        tipo={tipo}
        estado={estado}
        competencia={competencia}
        competencias={opciones.data?.competencias ?? []}
        hayFiltros={hayFiltros}
        onBusqueda={setBorrador}
        onTipo={(valor) => update({ tipo: valor })}
        onEstado={(valor) => update({ estado: valor })}
        onCompetencia={(valor) => update({ competencia: valor })}
        onLimpiar={() => {
          setBorrador(null)
          update({ q: '', tipo: '', estado: 'Activos', competencia: '' })
        }}
      />

      {consulta.isPending ? (
        <div role="status" aria-label={microText.loadingLabel} className="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(280px,1fr))]">
          {[0, 1, 2].map((n) => (
            <Skeleton key={n} className="h-72" />
          ))}
        </div>
      ) : consulta.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={microText.errorTitle} description={microText.errorDescription} retryLabel={microText.retry} onRetry={() => void consulta.refetch()} />
        </div>
      ) : consulta.data.data.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          {hayFiltros ? (
            <EmptyState icon={<IconPlayerPlay size={24} />} title={text.noResults} description={text.noResultsDescription} action={limpiar} />
          ) : (
            <EmptyState icon={<IconPlayerPlay size={24} />} title={text.emptyTitle} description={text.emptyDescription} />
          )}
        </div>
      ) : (
        <>
          <ul aria-label={text.listLabel} className="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(280px,1fr))]">
            {consulta.data.data.map((micro) => (
              <li key={micro.id} className="flex">
                <div className="flex w-full">
                  <TarjetaMicro
                    micro={micro}
                    ocupado={ocupado}
                    onVistaPrevia={setPrevia}
                    onEditar={(m) => setFormulario({ id: m.id })}
                    onPublicar={publicar}
                    onReactivar={publicar}
                    onArchivar={(m) => {
                      setErrorDialogo(undefined)
                      setArchivando(m)
                    }}
                    onEliminar={(m) => {
                      setErrorDialogo(undefined)
                      setEliminando(m)
                    }}
                    onHistorial={setHistorial}
                  />
                </div>
              </li>
            ))}
          </ul>
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

      {formulario && <FormularioMicro key={formulario.id ?? 'nuevo'} id={formulario.id} onCerrar={() => setFormulario(null)} />}
      {previa && <VistaPreviaMicro id={previa.id} onCerrar={() => setPrevia(null)} />}
      {historial && <HistorialMicro id={historial.id} titulo={historial.titulo} onCerrar={() => setHistorial(null)} />}

      <ConfirmDialog
        open={archivando !== null}
        title={microText.archiveDialog.title(archivando?.titulo ?? '')}
        description={microText.archiveDialog.description}
        confirmLabel={microText.archiveDialog.confirm}
        cancelLabel={microText.archiveDialog.cancel}
        tone="primary"
        busy={cambiarEstado.isPending}
        error={errorDialogo}
        onConfirm={confirmarArchivar}
        onCancel={() => setArchivando(null)}
      />
      <ConfirmDialog
        open={eliminando !== null}
        title={microText.deleteDialog.title(eliminando?.titulo ?? '')}
        description={microText.deleteDialog.description}
        confirmLabel={microText.deleteDialog.confirm}
        cancelLabel={microText.deleteDialog.cancel}
        busy={eliminar.isPending}
        error={errorDialogo}
        onConfirm={confirmarEliminar}
        onCancel={() => setEliminando(null)}
      />
    </div>
  )
}
