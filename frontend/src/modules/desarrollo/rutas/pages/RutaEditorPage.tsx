import { useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconArrowLeft, IconPencil, IconX } from '../../../../shared/ui/icons'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Tabs } from '../../../../shared/ui/Tabs'
import type { EstadoRuta, PestanaRuta } from '../api'
import { AvisoDeFuentes } from '../components/AvisoDeFuentes'
// import { AvisoGuia } from '../components/AvisoGuia'
import { BadgeEstadoRuta, EtiquetaTipo } from '../components/Etiquetas'
import { FormularioRutaModal } from '../components/FormularioRutaModal'
import { HistorialDeRuta } from '../components/HistorialDeRuta'
import { PestanaEscalera } from '../components/PestanaEscalera'
import { PestanaParticipantes } from '../components/PestanaParticipantes'
import { useParamsEditor } from '../hooks/useFiltrosRutas'
import { useCambiarEstadoRuta } from '../hooks/useMutacionesRutas'
import { rutasKey } from '../hooks/claves'
import { useOpcionesRutas, usePermisosRutas, useRuta } from '../hooks/useRutas'
import { PERMISOS, tienePermiso } from '../permisos'
import { rutasText } from '../text'

const t = rutasText.editor

/** Estados a los que puede pasar una ruta (más el actual): Borrador→Vigente, Vigente→Archivada y Archivada→Vigente. */
function estadosPosibles(actual: EstadoRuta): EstadoRuta[] {
  if (actual === 'Borrador') return ['Borrador', 'Vigente']
  if (actual === 'Vigente') return ['Vigente', 'Archivada']
  return ['Archivada', 'Vigente']
}

/**
 * Editor de una ruta (RutasAdmin.dc.html): encabezado con estado, aviso «guía, no promesa» y pestañas Escalera de niveles,
 * Participantes e Historial. Cada sección se muestra según las claves de permiso del usuario.
 */
export function RutaEditorPage() {
  const { id } = useParams()
  const rutaId = Number(id)
  const valido = Number.isInteger(rutaId) && rutaId > 0
  const location = useLocation()
  const queryClient = useQueryClient()
  const params = useParamsEditor()
  const permisos = usePermisosRutas()
  const puedeGestionar = tienePermiso(permisos.data, PERMISOS.gestionar)
  const puedeInscribir = tienePermiso(permisos.data, PERMISOS.inscribir)
  const veParticipantes = tienePermiso(permisos.data, PERMISOS.inscripciones)
  const veHistorial = tienePermiso(permisos.data, PERMISOS.historial)

  const ruta = useRuta(valido ? rutaId : null)
  const opciones = useOpcionesRutas(puedeGestionar)
  const cambiarEstado = useCambiarEstadoRuta(valido ? rutaId : 0)

  const [aviso, setAviso] = useState((location.state as { aviso?: string } | null)?.aviso ?? '')
  const [editando, setEditando] = useState(false)
  const [conflicto, setConflicto] = useState(false)
  const [errorEstado, setErrorEstado] = useState<string | undefined>()
  const [archivar, setArchivar] = useState<{ inscritos: number } | null>(null)

  const recargar = () => {
    setConflicto(false)
    void queryClient.invalidateQueries({ queryKey: [...rutasKey, 'detalle', rutaId] })
  }

  if (!valido) {
    return <ErrorState title={rutasText.error.title} description={rutasText.error.description} retryLabel={rutasText.error.retry} onRetry={() => window.history.back()} />
  }

  if (ruta.isPending) {
    return (
      <div role="status" aria-label={rutasText.loading} className="mx-auto flex max-w-282 flex-col gap-4">
        <Skeleton className="h-10 w-72" />
        <Skeleton className="h-16" />
        <Skeleton className="h-64" />
      </div>
    )
  }
  if (ruta.isError) {
    return <ErrorState title={rutasText.error.title} description={rutasText.error.description} retryLabel={rutasText.error.retry} onRetry={() => void ruta.refetch()} />
  }

  const datos = ruta.data
  const solicitarEstado = (destino: EstadoRuta) => {
    if (destino === datos.estado || destino === 'Borrador') return
    setErrorEstado(undefined)
    if (destino === 'Archivada' && datos.inscritos_count > 0) {
      setArchivar({ inscritos: datos.inscritos_count })
      return
    }
    aplicarEstado(destino, false)
  }

  function aplicarEstado(destino: Exclude<EstadoRuta, 'Borrador'>, confirmar: boolean) {
    cambiarEstado.mutate(
      { version: datos.version, estado: destino, confirmarInscritos: confirmar },
      {
        onSuccess: () => {
          setAviso(t.estadoCambiado(destino))
          setArchivar(null)
          setErrorEstado(undefined)
        },
        onError: (e) => {
          const api = toApiError(e)
          if (api.status === 409) {
            setArchivar(null)
            setConflicto(true)
            return
          }
          if (api.code === 'requiere_confirmacion') {
            setArchivar({ inscritos: Number(api.data?.inscritos ?? datos.inscritos_count) })
            return
          }
          setArchivar(null)
          setErrorEstado(api.message ?? rutasText.error.description)
        },
      },
    )
  }

  const pestanas = [
    { id: 'escalera', label: t.pestanas(datos.inscritos_count).escalera },
    ...(veParticipantes ? [{ id: 'participantes', label: t.pestanas(datos.inscritos_count).participantes }] : []),
    ...(veHistorial ? [{ id: 'historial', label: t.pestanas(datos.inscritos_count).historial }] : []),
  ]
  const pestanaActiva: PestanaRuta = pestanas.some((p) => p.id === params.pestana) ? params.pestana : 'escalera'

  return (
    <div className="mx-auto flex max-w-282 flex-col gap-5">
      <div>
        <Link to="/rutas" className="inline-flex h-11 items-center gap-2 rounded-lg border border-border bg-surface px-4 text-[15px] font-semibold text-text no-underline hover:bg-soft">
          <IconArrowLeft size={18} />
          {t.volver}
        </Link>
      </div>

      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2.5">
            <h1 className="m-0 text-[26px] font-bold leading-tight tracking-tight">{datos.nombre}</h1>
            <EtiquetaTipo tipo={datos.tipo} />
            <BadgeEstadoRuta estado={datos.estado} />
          </div>
          <p className="mt-1.5 text-[15px] text-muted">
            {datos.codigo} · {t.meta(datos.departamento, datos.niveles.length, datos.inscritos_count)}
          </p>
          <p className="mt-1 text-[13px] text-muted">{t.auditoria(datos.creada_por, datos.creada_at, datos.modificada_por, datos.modificada_at)}</p>
        </div>
        {puedeGestionar && (
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-50">
              <SelectField
                id="ruta-estado"
                label={t.estadoLabel}
                value={datos.estado}
                disabled={cambiarEstado.isPending}
                onChange={(e) => solicitarEstado(e.target.value as EstadoRuta)}
                options={estadosPosibles(datos.estado).map((e) => ({ value: e, label: rutasText.estados[e] }))}
              />
            </div>
            <Button variant="secondary" onClick={() => setEditando(true)}>
              <IconPencil size={16} />
              {t.editar}
            </Button>
          </div>
        )}
      </div>

      {/* <AvisoGuia /> */}

      {aviso && (
        <div role="status" aria-live="polite" className="flex items-center justify-between gap-3 rounded-[10px] border border-border bg-soft px-4 py-2.5 text-sm font-semibold text-accent">
          <span>{aviso}</span>
          <button type="button" aria-label={rutasText.cerrar} onClick={() => setAviso('')} className="rounded p-1 text-accent hover:bg-surface">
            <IconX size={16} />
          </button>
        </div>
      )}
      {errorEstado && <Alert>{errorEstado}</Alert>}
      {conflicto && (
        <Alert>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <span>{rutasText.conflicto.mensaje}</span>
            <Button variant="secondary" onClick={recargar}>
              {rutasText.conflicto.recargar}
            </Button>
          </div>
        </Alert>
      )}
      <AvisoDeFuentes fuentes={datos.fuentes_no_disponibles} onReintentar={() => void ruta.refetch()} />

      <Tabs label={t.seccion} idBase="ruta-editor" tabs={pestanas} active={pestanaActiva} onChange={(idPestana) => params.actualizar({ pestana: idPestana as PestanaRuta })}>
        {pestanaActiva === 'escalera' && (
          <PestanaEscalera
            ruta={datos}
            opciones={opciones.data}
            puedeGestionar={puedeGestionar}
            nivelSeleccionado={params.nivel}
            onSeleccionar={(nivelId) => params.actualizar({ nivel: nivelId })}
            onAviso={setAviso}
            onConflicto={() => setConflicto(true)}
          />
        )}
        {pestanaActiva === 'participantes' && (
          <PestanaParticipantes
            ruta={datos}
            puedeInscribir={puedeInscribir}
            estado={params.estadoInscripcion}
            pagina={params.paginaParticipantes}
            onFiltro={(estado) => params.actualizar({ estadoInscripcion: estado })}
            onPagina={(pagina) => params.actualizar({ paginaParticipantes: pagina })}
            onAviso={setAviso}
          />
        )}
        {pestanaActiva === 'historial' && <HistorialDeRuta rutaId={datos.id} pagina={params.paginaHistorial} onPagina={(pagina) => params.actualizar({ paginaHistorial: pagina })} />}
      </Tabs>

      {editando && (
        <FormularioRutaModal
          ruta={datos}
          onCerrar={() => setEditando(false)}
          onGuardado={() => {
            setEditando(false)
            setAviso(rutasText.formulario.actualizada)
          }}
          onConflicto={() => {
            setEditando(false)
            setConflicto(true)
          }}
        />
      )}

      <ConfirmDialog
        open={archivar !== null}
        title={t.archivar.title}
        description={t.archivar.description(archivar?.inscritos ?? 0)}
        confirmLabel={t.archivar.confirmar}
        cancelLabel={rutasText.cancelar}
        busy={cambiarEstado.isPending}
        onCancel={() => setArchivar(null)}
        onConfirm={() => aplicarEstado('Archivada', true)}
      />
    </div>
  )
}
