import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconUsers } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { ESTADOS_INSCRIPCION, type EstadoInscripcion, type Inscripcion, type RutaDetalle } from '../api'
import { useAlcanzarNivel } from '../hooks/useMutacionesRutas'
import { useInscripciones } from '../hooks/useRutas'
import { rutasText } from '../text'
import { CambiarNivelesModal } from './CambiarNivelesModal'
import { CanteraPorNivel } from './CanteraPorNivel'
import { FormularioInscripcion } from './FormularioInscripcion'
import { RequirePermiso } from './RequirePermiso'
import { RetirarModal } from './RetirarModal'
import { TablaDeParticipantes } from './TablaDeParticipantes'
import { PERMISOS } from '../permisos'

const t = rutasText.participantes
const POR_PAGINA = 10

interface PestanaParticipantesProps {
  ruta: RutaDetalle
  puedeInscribir: boolean
  estado: EstadoInscripcion | ''
  pagina: number
  onFiltro: (estado: EstadoInscripcion | '') => void
  onPagina: (pagina: number) => void
  onAviso: (mensaje: string) => void
}

/** Pestaña «Participantes» (RutasAdmin.dc.html): tabla con acciones a la izquierda; cantera e inscripción a la derecha. */
export function PestanaParticipantes({ ruta, puedeInscribir, estado, pagina, onFiltro, onPagina, onAviso }: PestanaParticipantesProps) {
  const inscripciones = useInscripciones({ ruta_id: ruta.id, estado: estado || undefined, page: pagina, per_page: POR_PAGINA })
  const alcanzar = useAlcanzarNivel(ruta.id)
  const [cambiando, setCambiando] = useState<Inscripcion | null>(null)
  const [retirando, setRetirando] = useState<Inscripcion | null>(null)
  const [alcanzando, setAlcanzando] = useState<Inscripcion | null>(null)
  const [errorAlcanzado, setErrorAlcanzado] = useState<string | undefined>()

  const confirmarAlcanzado = () => {
    if (!alcanzando) return
    const objetivo = alcanzando
    setErrorAlcanzado(undefined)
    alcanzar.mutate(objetivo.id, {
      onSuccess: ({ inscripcion }) => {
        setAlcanzando(null)
        onAviso(rutasText.alcanzado.exito(objetivo.colaborador.nombre, inscripcion.estado === 'Finalizada'))
      },
      onError: (e) => setErrorAlcanzado(toApiError(e).message ?? rutasText.alcanzado.errorGuardar),
    })
  }

  const meta = inscripciones.data?.meta

  return (
    <div className="grid grid-cols-[repeat(auto-fit,minmax(340px,1fr))] items-start gap-6">
      <section aria-labelledby="participantes-titulo" className="flex flex-col gap-3.5">
        <h2 id="participantes-titulo" className="m-0 text-lg">
          {t.title}
        </h2>
        <div className="w-44 max-w-full">
          <SelectField
            id="participantes-estado"
            label={t.filtroEstado}
            value={estado}
            onChange={(e) => onFiltro(e.target.value as EstadoInscripcion | '')}
            options={[{ value: '', label: t.todos }, ...ESTADOS_INSCRIPCION.map((e) => ({ value: e, label: rutasText.estadosInscripcion[e] }))]}
          />
        </div>

        {inscripciones.isPending ? (
          <div role="status" aria-label={rutasText.loading} className="flex flex-col gap-2">
            {[0, 1, 2].map((i) => (
              <Skeleton key={i} className="h-12" />
            ))}
          </div>
        ) : inscripciones.isError ? (
          <ErrorState title={rutasText.error.title} description={rutasText.error.description} retryLabel={rutasText.error.retry} onRetry={() => void inscripciones.refetch()} />
        ) : inscripciones.data.data.length === 0 ? (
          <EmptyState icon={<IconUsers size={24} />} title={estado === '' ? t.vacio : t.vacioFiltro} description={rutasText.aviso} />
        ) : (
          <>
            <TablaDeParticipantes
              inscripciones={inscripciones.data.data}
              puedeInscribir={puedeInscribir}
              ocupado={alcanzar.isPending}
              onCambiar={setCambiando}
              onAlcanzar={(i) => {
                setErrorAlcanzado(undefined)
                setAlcanzando(i)
              }}
              onRetirar={setRetirando}
            />
            {meta && (
              <Pagination
                page={meta.page}
                lastPage={meta.last_page}
                total={meta.total}
                from={(meta.page - 1) * meta.per_page + 1}
                to={Math.min(meta.page * meta.per_page, meta.total)}
                onPageChange={onPagina}
              />
            )}
          </>
        )}
        {errorAlcanzado && alcanzando === null && <Alert>{errorAlcanzado}</Alert>}
      </section>

      <div className="flex flex-col gap-5">
        <CanteraPorNivel niveles={ruta.niveles} sinNivel={ruta.sin_nivel} />
        <RequirePermiso clave={PERMISOS.inscribir}>
          <FormularioInscripcion ruta={ruta} onInscrito={onAviso} />
        </RequirePermiso>
      </div>

      {cambiando && (
        <CambiarNivelesModal
          inscripcion={cambiando}
          ruta={ruta}
          onCerrar={() => setCambiando(null)}
          onGuardado={(mensaje) => {
            setCambiando(null)
            onAviso(mensaje)
          }}
        />
      )}
      {retirando && (
        <RetirarModal
          inscripcion={retirando}
          rutaId={ruta.id}
          onCerrar={() => setRetirando(null)}
          onRetirado={(mensaje) => {
            setRetirando(null)
            onAviso(mensaje)
          }}
        />
      )}
      <ConfirmDialog
        open={alcanzando !== null}
        tone="primary"
        title={rutasText.alcanzado.title}
        description={alcanzando ? rutasText.alcanzado.description(alcanzando.colaborador.nombre, alcanzando.nivel_objetivo?.nombre ?? '') : ''}
        confirmLabel={rutasText.alcanzado.confirmar}
        cancelLabel={rutasText.cancelar}
        busy={alcanzar.isPending}
        error={errorAlcanzado}
        onCancel={() => setAlcanzando(null)}
        onConfirm={confirmarAlcanzado}
      />
    </div>
  )
}
