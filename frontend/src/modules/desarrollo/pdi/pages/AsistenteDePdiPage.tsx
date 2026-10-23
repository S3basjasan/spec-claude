import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { TipoPdi } from '../api'
import { datosDesdePdi, datosVacios } from '../asistente'
import { AsistenteDePdi } from '../components/AsistenteDePdi'
import { useOpciones, usePdi } from '../hooks/usePdi'
import { useUsuarioActual } from '../hooks/useUsuarioActual'
import { pdiText } from '../text'

const t = pdiText.asistente

function tipoDeUrl(valor: string | null): TipoPdi {
  return valor === 'Carrera' || valor === 'Talento' ? valor : 'Mejora'
}

function enteroDeUrl(valor: string | null): number | null {
  const n = Math.floor(Number(valor))

  return Number.isFinite(n) && n > 0 ? n : null
}

/** Crea un plan (`/pdi/nuevo`, con colaborador, tipo y puesto objetivo opcionales en la URL) o edita un Borrador (`/pdi/:id/editar`). */
export function AsistenteDePdiPage() {
  const { id } = useParams()
  const [params] = useSearchParams()
  const navegar = useNavigate()
  const planId = id === undefined ? null : Number(id)
  const plan = usePdi(planId)
  const opciones = useOpciones('')
  const usuarioId = useUsuarioActual()

  const titulo = planId === null ? t.titulo : t.tituloEditar
  const migas = <Breadcrumb label={titulo} items={[{ label: t.breadcrumbInicio, to: '/pdi' }, { label: titulo }]} />

  if ((planId !== null && plan.isPending) || opciones.isPending) {
    return (
      <div className="mx-auto flex max-w-[1100px] flex-col gap-6">
        {migas}
        <div role="status" aria-label={pdiText.loading} className="flex flex-col gap-2">
          <Skeleton className="h-14" />
          <Skeleton className="h-14" />
          <Skeleton className="h-14" />
        </div>
      </div>
    )
  }

  if (planId !== null && plan.isError) {
    const noExiste = toApiError(plan.error).status === 404

    return (
      <div className="mx-auto flex max-w-[1100px] flex-col gap-6">
        {migas}
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState
            title={noExiste ? pdiText.detalle.noEncontrado : pdiText.detalle.error.titulo}
            description={noExiste ? '' : pdiText.detalle.error.descripcion}
            retryLabel={pdiText.detalle.error.reintentar}
            onRetry={() => void plan.refetch()}
          />
        </div>
      </div>
    )
  }

  const existente = plan.data
  if (existente !== undefined && !existente.acciones_permitidas.editar) {
    return (
      <div className="mx-auto flex max-w-[1100px] flex-col gap-6">
        {migas}
        <Alert tone="neutral">{t.soloBorrador}</Alert>
      </div>
    )
  }

  const colaboradorId = enteroDeUrl(params.get('colaborador'))
  const colaborador = opciones.data?.colaboradores.find((c) => c.id === colaboradorId)
  const inicial =
    existente !== undefined
      ? datosDesdePdi(existente)
      : datosVacios(usuarioId, {
          colaborador_id: colaborador?.id ?? null,
          colaborador_nombre: colaborador?.nombre ?? '',
          tipo: tipoDeUrl(params.get('tipo')),
          puesto_objetivo_id: tipoDeUrl(params.get('tipo')) === 'Mejora' ? null : enteroDeUrl(params.get('puesto_objetivo')),
        })

  return (
    <div className="mx-auto flex max-w-[1100px] flex-col gap-6">
      {migas}
      <h1 className="m-0 text-[28px] font-bold leading-tight tracking-tight">{titulo}</h1>
      <AsistenteDePdi
        key={existente ? `${existente.id}-${existente.version}` : 'nuevo'}
        existente={existente}
        inicial={inicial}
        onTerminar={(creado) => navegar(`/pdi/${creado.id}`)}
        onRecargar={() => void plan.refetch()}
      />
    </div>
  )
}
