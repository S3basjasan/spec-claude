import { useState } from 'react'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Tabs } from '../../../../shared/ui/Tabs'
import type { FilaAccion, FiltrosAcciones as FiltrosApi } from '../api'
import { CalendarioAcciones } from '../components/CalendarioAcciones'
import { DetalleAccionPanel } from '../components/DetalleAccionPanel'
import { FiltrosAcciones } from '../components/FiltrosAcciones'
import { ListaAcciones } from '../components/ListaAcciones'
import { semanasDelMes } from '../fecha'
import { useAcciones } from '../hooks/useAcciones'
import { mesAIso, useAccionesParams, type Vista } from '../hooks/useAccionesParams'
import { useOpcionesPlan } from '../hooks/useOpcionesPlan'
import { planText } from '../text'

const t = planText.acciones
const POR_PAGINA_LISTA = 15
const POR_PAGINA_CALENDARIO = 200

/** Acciones formativas de todos los planes (Administrador): pestañas Calendario y Lista con filtros comunes. */
export function AccionesPage() {
  const params = useAccionesParams()
  const opciones = useOpcionesPlan()
  const [seleccionada, setSeleccionada] = useState<FilaAccion | null>(null)

  const base: FiltrosApi = {
    estado: params.estado === '' ? undefined : params.estado,
    tipo: params.tipo === '' ? undefined : params.tipo,
    competencia_id: params.competencia === '' ? undefined : params.competencia,
  }
  const rangoInvalido = params.desde !== '' && params.hasta !== '' && params.desde > params.hasta

  // El calendario pide la rejilla visible (semanas completas), acotada por el rango de fechas elegido, si lo hay.
  const semanas = semanasDelMes(params.anioMes.anio, params.anioMes.mes)
  const inicioRejilla = semanas[0][0].iso
  const finRejilla = semanas[semanas.length - 1][6].iso
  const desdeCalendario = params.desde !== '' && params.desde > inicioRejilla ? params.desde : inicioRejilla
  const hastaCalendario = params.hasta !== '' && params.hasta < finRejilla ? params.hasta : finRejilla
  const calendarioVacio = desdeCalendario > hastaCalendario

  const filtros: FiltrosApi =
    params.vista === 'calendario'
      ? { ...base, fecha_desde: desdeCalendario, fecha_hasta: hastaCalendario, per_page: POR_PAGINA_CALENDARIO }
      : { ...base, fecha_desde: params.desde || undefined, fecha_hasta: params.hasta || undefined, page: params.page, per_page: POR_PAGINA_LISTA }
  const habilitado = !rangoInvalido && !(params.vista === 'calendario' && calendarioVacio)
  const acciones = useAcciones(filtros, habilitado)
  const meta = acciones.data?.meta

  const contenido = () => {
    if (!habilitado) return <p className="rounded-xl border border-border bg-surface px-4 py-8 text-center text-muted">{t.vacio.title}</p>
    if (acciones.isPending) {
      return (
        <div role="status" aria-label={t.tableLabel} className="flex flex-col gap-2">
          {[0, 1, 2, 3].map((fila) => (
            <Skeleton key={fila} className="h-12" />
          ))}
        </div>
      )
    }
    if (acciones.isError) {
      return (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState title={planText.error.title} description={planText.error.description} retryLabel={planText.error.retry} onRetry={() => void acciones.refetch()} />
        </div>
      )
    }
    if (params.vista === 'calendario') {
      return (
        <CalendarioAcciones
          anio={params.anioMes.anio}
          mes={params.anioMes.mes}
          acciones={acciones.data.data}
          hayMas={acciones.data.meta.total > POR_PAGINA_CALENDARIO}
          onMesChange={(anio, mes) => params.update({ mes: mesAIso(anio, mes) })}
          onSeleccionar={setSeleccionada}
        />
      )
    }
    if (acciones.data.data.length === 0) {
      return (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconFileText size={24} />} title={t.vacio.title} description={t.vacio.description} />
        </div>
      )
    }
    return (
      <>
        <ListaAcciones acciones={acciones.data.data} onSeleccionar={setSeleccionada} />
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
    )
  }

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <Breadcrumb label={t.title} items={[{ label: t.volver, to: '/plan-formacion' }, { label: t.title }]} />
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{t.title}</h1>
        <p className="mt-1.5 text-base text-muted">{t.subtitle}</p>
      </div>

      <FiltrosAcciones
        estado={params.estado}
        tipo={params.tipo}
        competencia={params.competencia}
        desde={params.desde}
        hasta={params.hasta}
        opciones={opciones.data}
        onChange={params.update}
        onLimpiar={params.limpiar}
      />
      {meta && habilitado && <p className="text-[13px] text-muted" role="status">{t.resumen(meta.total)}</p>}

      <Tabs
        idBase="acciones"
        label={t.tabsLabel}
        tabs={[
          { id: 'calendario', label: t.tabCalendario },
          { id: 'lista', label: t.tabLista },
        ]}
        active={params.vista}
        onChange={(id) => params.update({ vista: id as Vista })}
      >
        {contenido()}
      </Tabs>

      <DetalleAccionPanel accion={seleccionada} onClose={() => setSeleccionada(null)} />
    </div>
  )
}
