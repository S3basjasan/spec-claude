import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText, IconX } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Tabs } from '../../../../shared/ui/Tabs'
import type { FiltrosActividades as FiltrosApi, VistaAdmin } from '../api'
import { useActividades, useOpcionesActividades } from '../hooks/useActividades'
import { useActividadesParams } from '../hooks/useActividadesParams'
import { actividadesText } from '../text'
import { FiltrosActividades } from './FiltrosActividades'
import { FormularioActividadModal } from './FormularioActividadModal'
import { TablaActividades } from './TablaActividades'
import { VistaCalificaciones } from './VistaCalificaciones'

const t = actividadesText.admin
const POR_PAGINA = 10

/** Entrada del Administrador: pestañas «Actividades» y «Calificaciones», botón «+ Nueva Actividad» y formulario en modal. */
export function VistaAdministrador() {
  const params = useActividadesParams()
  const opciones = useOpcionesActividades()
  const [formulario, setFormulario] = useState<{ id: number | null } | null>(null)
  const [aviso, setAviso] = useState('')

  const filtros: FiltrosApi = {
    q: params.q || undefined,
    tipo: params.tipo || undefined,
    estado: params.estado || undefined,
    departamento_id: params.departamento === '' ? undefined : params.departamento,
    competencia_id: params.competencia === '' ? undefined : params.competencia,
    plan_id: params.plan === '' ? undefined : params.plan,
    fecha_desde: params.desde,
    fecha_hasta: params.hasta,
    page: params.page,
    per_page: POR_PAGINA,
  }
  const lista = useActividades(filtros)
  const meta = lista.data?.meta
  const hayFiltros = params.hayFiltros

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{t.title}</h1>
          <p className="mt-1.5 text-base text-muted">{t.subtitle}</p>
        </div>
        <Button onClick={() => setFormulario({ id: null })}>{t.nueva}</Button>
      </div>

      {aviso && (
        <div role="status" className="flex items-center justify-between gap-3 rounded-lg border border-border bg-soft px-4 py-3 text-[15px]">
          <span>{aviso}</span>
          <button type="button" aria-label={actividadesText.formulario.cerrar} onClick={() => setAviso('')} className="rounded p-1 text-accent hover:bg-surface">
            <IconX size={16} />
          </button>
        </div>
      )}

      <Tabs
        label={t.vistas}
        idBase="actividades-vistas"
        tabs={[
          { id: 'actividades', label: t.pestanas.actividades },
          { id: 'calificaciones', label: t.pestanas.calificaciones },
        ]}
        active={params.vista}
        onChange={(id) => params.cambiarVista(id as VistaAdmin)}
      >
        {params.vista === 'calificaciones' ? (
          <VistaCalificaciones opciones={opciones.data} />
        ) : (
          <div className="flex flex-col gap-5">
            <FiltrosActividades params={params} opciones={opciones.data} resumen={meta ? t.filtros.resumen(lista.data?.data.length ?? 0, meta.total) : ''} />
            {opciones.isError && <Alert>{actividadesText.error.description}</Alert>}

            {lista.isPending ? (
              <div role="status" aria-label={t.tableLabel} className="flex flex-col gap-2">
                {[0, 1, 2, 3].map((fila) => (
                  <Skeleton key={fila} className="h-12" />
                ))}
              </div>
            ) : lista.isError ? (
              <div className="rounded-xl border border-border bg-surface">
                <ErrorState title={actividadesText.error.title} description={actividadesText.error.description} retryLabel={actividadesText.error.retry} onRetry={() => void lista.refetch()} />
              </div>
            ) : lista.data.data.length === 0 ? (
              <div className="rounded-xl border border-border bg-surface">
                <EmptyState icon={<IconFileText size={24} />} title={hayFiltros ? t.sinCoincidencias : t.vacio.title} description={hayFiltros ? '' : t.vacio.description} />
              </div>
            ) : (
              <>
                <TablaActividades actividades={lista.data.data} onEditar={(id) => setFormulario({ id })} />
                {meta && (
                  <Pagination
                    page={meta.page}
                    lastPage={meta.last_page}
                    total={meta.total}
                    from={(meta.page - 1) * meta.per_page + 1}
                    to={Math.min(meta.page * meta.per_page, meta.total)}
                    onPageChange={(page) => params.update({ page })}
                  />
                )}
              </>
            )}
          </div>
        )}
      </Tabs>

      {formulario && (
        <FormularioActividadModal
          key={formulario.id ?? 'nueva'}
          actividadId={formulario.id}
          onCerrar={() => setFormulario(null)}
          onGuardado={(actividad, creada) => {
            setFormulario(null)
            setAviso(creada ? t.creada(actividad.codigo) : t.actualizada(actividad.codigo))
          }}
        />
      )}
    </div>
  )
}
