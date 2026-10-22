import { useState, type ReactNode } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconChartBar, IconX } from '../../../../shared/ui/icons'
import { Pagination } from '../../../../shared/ui/Pagination'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Tabs } from '../../../../shared/ui/Tabs'
import type { FiltroEvaluado, FilaDetalle, FilaImpacto, Meta, VistaReportes } from '../api'
import { useFiltrosReportes } from '../hooks/useFiltrosReportes'
import { useDashboard, useDetalle, useImpactos, useOpcionesReportes, useSeguimiento } from '../hooks/useReportes'
import { fecha } from '../formato'
import { reportesText } from '../text'
import { BarraDeFiltros } from './BarraDeFiltros'
import { BotonesExportar } from './BotonesExportar'
import { ComentariosDialog } from './ComentariosDialog'
import { FormularioImpactoModal } from './FormularioImpactoModal'
import { GraficoBarrasHoras } from './GraficoBarrasHoras'
import { GraficoLineaBrechas } from './GraficoLineaBrechas'
import { GraficoPastelTipos } from './GraficoPastelTipos'
import { PanelIndicadores } from './PanelIndicadores'
import { TablaDetalle } from './TablaDetalle'
import { TablaImpactos } from './TablaImpactos'
import { TablaSeguimiento } from './TablaSeguimiento'

const t = reportesText.admin
const POR_PAGINA = 10

/** Estados loading, error con «Reintentar» y vacío comunes a las vistas de datos. */
function Estados({ cargando, error, vacio, onReintentar, etiqueta, children }: { cargando: boolean; error: boolean; vacio: { titulo: string; descripcion: string } | null; onReintentar: () => void; etiqueta: string; children: ReactNode }) {
  if (cargando) {
    return (
      <div role="status" aria-label={etiqueta} className="flex flex-col gap-2">
        {[0, 1, 2, 3].map((fila) => (
          <Skeleton key={fila} className="h-12" />
        ))}
      </div>
    )
  }
  if (error) {
    return (
      <div className="rounded-xl border border-border bg-surface">
        <ErrorState title={reportesText.error.title} description={reportesText.error.description} retryLabel={reportesText.error.retry} onRetry={onReintentar} />
      </div>
    )
  }
  if (vacio !== null) {
    return (
      <div className="rounded-xl border border-border bg-surface">
        <EmptyState icon={<IconChartBar size={24} />} title={vacio.titulo} description={vacio.descripcion} />
      </div>
    )
  }
  return <>{children}</>
}

function Paginacion({ meta, onCambio }: { meta: Meta; onCambio: (pagina: number) => void }) {
  const desde = meta.total === 0 ? null : (meta.page - 1) * meta.per_page + 1
  const hasta = meta.total === 0 ? null : Math.min(meta.total, meta.page * meta.per_page)

  return <Pagination page={meta.page} lastPage={meta.last_page} total={meta.total} from={desde} to={hasta} onPageChange={onCambio} />
}

function AvisoDeFuentes({ fuentes }: { fuentes: string[] | undefined }) {
  if (fuentes === undefined || fuentes.length === 0) return null

  return (
    <Alert tone="neutral">
      <strong>{reportesText.fuentesCaidas.titulo(fuentes)}</strong> {reportesText.fuentesCaidas.descripcion}
    </Alert>
  )
}

/** Vista del Administrador: filtros, pestañas (indicadores, detalle, impacto 30-60-90 y evaluación de impacto) y exportación. */
export function VistaAdministrador() {
  const filtros = useFiltrosReportes()
  const opciones = useOpcionesReportes()
  const [comentarios, setComentarios] = useState<FilaDetalle | null>(null)
  const [impacto, setImpacto] = useState<FilaImpacto | null>(null)
  const [aviso, setAviso] = useState('')

  const dashboard = useDashboard(filtros.filtros, filtros.vista === 'indicadores' && filtros.rangoValido)
  const detalle = useDetalle({ ...filtros.filtros, q: filtros.q || undefined, page: filtros.page, per_page: POR_PAGINA }, filtros.vista === 'detalle' && filtros.rangoValido)
  const seguimiento = useSeguimiento({ ...filtros.filtros, page: filtros.page, per_page: POR_PAGINA }, filtros.vista === 'seguimiento' && filtros.rangoValido)
  const impactos = useImpactos({ plan_id: filtros.filtros.plan_id, evaluado: filtros.evaluado || undefined, page: filtros.page, per_page: POR_PAGINA }, filtros.vista === 'impactos')

  return (
    <div className="mx-auto flex max-w-300 flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="m-0 text-[28px] font-bold leading-tight tracking-tight">{t.title}</h1>
          <p className="mt-1.5 text-base text-muted">{t.subtitle(fecha(filtros.desde), fecha(filtros.hasta))}</p>
        </div>
        <BotonesExportar filtros={filtros.filtros} deshabilitado={!filtros.rangoValido} />
      </div>

      {aviso && (
        <div role="status" className="flex items-center justify-between gap-3 rounded-lg border border-border bg-soft px-4 py-3 text-[15px]">
          <span>{aviso}</span>
          <button type="button" aria-label={t.cerrarAviso} onClick={() => setAviso('')} className="rounded p-1 text-accent hover:bg-surface">
            <IconX size={16} />
          </button>
        </div>
      )}

      {filtros.vista === 'impactos' ? (
        <div className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface p-4">
          <div className="w-64">
            <SelectField
              id="imp-plan"
              label={reportesText.impactos.filtros.plan}
              value={String(filtros.plan)}
              options={[{ value: '', label: reportesText.impactos.filtros.todosLosPlanes }, ...(opciones.data?.planes ?? []).map((p) => ({ value: String(p.id), label: `${p.codigo} · ${p.nombre}` }))]}
              onChange={(e) => filtros.actualizar({ plan: e.target.value === '' ? '' : Number(e.target.value) })}
            />
          </div>
          <div className="w-52">
            <SelectField
              id="imp-evaluado"
              label={reportesText.impactos.filtros.evaluado}
              value={filtros.evaluado}
              options={[
                { value: '', label: reportesText.impactos.filtros.todas },
                { value: 'con', label: reportesText.impactos.filtros.con },
                { value: 'sin', label: reportesText.impactos.filtros.sin },
              ]}
              onChange={(e) => filtros.actualizar({ evaluado: e.target.value as FiltroEvaluado | '' })}
            />
          </div>
        </div>
      ) : (
        <BarraDeFiltros filtros={filtros} opciones={opciones.data} />
      )}
      {opciones.isError && <Alert>{reportesText.error.description}</Alert>}

      <Tabs
        label={t.vistas}
        idBase="reportes-vistas"
        scrollable
        tabs={[
          { id: 'indicadores', label: t.pestanas.indicadores },
          { id: 'detalle', label: t.pestanas.detalle },
          { id: 'seguimiento', label: t.pestanas.seguimiento },
          { id: 'impactos', label: t.pestanas.impactos },
        ]}
        active={filtros.vista}
        onChange={(id) => filtros.cambiarVista(id as VistaReportes)}
      >
        {filtros.vista === 'indicadores' && (
          <Estados cargando={dashboard.isPending} error={dashboard.isError} vacio={null} onReintentar={() => void dashboard.refetch()} etiqueta={reportesText.loading}>
            {dashboard.data && (
              <div className="flex flex-col gap-6">
                <AvisoDeFuentes fuentes={dashboard.data.fuentes_no_disponibles} />
                <PanelIndicadores dashboard={dashboard.data} />
                <section aria-labelledby="rep-graf-titulo" className="flex flex-col gap-4">
                  <h2 id="rep-graf-titulo" className="m-0 text-xl font-bold">
                    {reportesText.graficos.titulo}
                  </h2>
                  <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <GraficoBarrasHoras grafico={dashboard.data.graficos.horas_por_departamento} />
                    <GraficoPastelTipos grafico={dashboard.data.graficos.distribucion_por_tipo} />
                  </div>
                  <GraficoLineaBrechas grafico={dashboard.data.graficos.evolucion_brechas} />
                </section>
              </div>
            )}
          </Estados>
        )}

        {filtros.vista === 'detalle' && (
          <div className="flex flex-col gap-4">
            <h2 className="m-0 text-xl font-bold">{reportesText.detalle.titulo}</h2>
            <SearchField id="rep-buscar" label={reportesText.detalle.buscar} placeholder={reportesText.detalle.buscar} value={filtros.q} onChange={(e) => filtros.actualizar({ q: e.target.value })} className="max-w-sm" />
            <AvisoDeFuentes fuentes={detalle.data?.meta.fuentes_no_disponibles} />
            <Estados cargando={detalle.isPending} error={detalle.isError} vacio={detalle.data && detalle.data.data.length === 0 ? reportesText.detalle.vacio : null} onReintentar={() => void detalle.refetch()} etiqueta={reportesText.detalle.tableLabel}>
              {detalle.data && (
                <>
                  <span role="status" aria-live="polite" className="text-[13px] text-muted">
                    {reportesText.detalle.resumen(detalle.data.data.length, detalle.data.meta.total)}
                  </span>
                  <TablaDetalle filas={detalle.data.data} onVerComentarios={setComentarios} />
                  <Paginacion meta={detalle.data.meta} onCambio={(page) => filtros.actualizar({ page })} />
                  <p className="m-0 text-[13px] text-muted">{reportesText.detalle.nota}</p>
                </>
              )}
            </Estados>
          </div>
        )}

        {filtros.vista === 'seguimiento' && (
          <div className="flex flex-col gap-4">
            <div>
              <h2 className="m-0 text-xl font-bold">{reportesText.seguimiento.titulo}</h2>
              <p className="mt-1 text-sm text-muted">{reportesText.seguimiento.descripcion}</p>
            </div>
            <AvisoDeFuentes fuentes={seguimiento.data?.meta.fuentes_no_disponibles} />
            <Estados cargando={seguimiento.isPending} error={seguimiento.isError} vacio={seguimiento.data && seguimiento.data.data.length === 0 ? reportesText.seguimiento.vacio : null} onReintentar={() => void seguimiento.refetch()} etiqueta={reportesText.seguimiento.tableLabel}>
              {seguimiento.data && (
                <>
                  <TablaSeguimiento filas={seguimiento.data.data} />
                  <Paginacion meta={seguimiento.data.meta} onCambio={(page) => filtros.actualizar({ page })} />
                </>
              )}
            </Estados>
          </div>
        )}

        {filtros.vista === 'impactos' && (
          <div className="flex flex-col gap-4">
            <div>
              <h2 className="m-0 text-xl font-bold">{reportesText.impactos.titulo}</h2>
              <p className="mt-1 text-sm text-muted">{reportesText.impactos.descripcion}</p>
            </div>
            <Estados cargando={impactos.isPending} error={impactos.isError} vacio={impactos.data && impactos.data.data.length === 0 ? reportesText.impactos.vacio : null} onReintentar={() => void impactos.refetch()} etiqueta={reportesText.impactos.tableLabel}>
              {impactos.data && (
                <>
                  <TablaImpactos filas={impactos.data.data} onRegistrar={setImpacto} />
                  <Paginacion meta={impactos.data.meta} onCambio={(page) => filtros.actualizar({ page })} />
                </>
              )}
            </Estados>
          </div>
        )}
      </Tabs>

      {comentarios && <ComentariosDialog actividadId={comentarios.actividad_id} nombre={comentarios.nombre} onCerrar={() => setComentarios(null)} />}
      {impacto && (
        <FormularioImpactoModal
          fila={impacto}
          onCerrar={() => setImpacto(null)}
          onGuardado={() => {
            setImpacto(null)
            setAviso(reportesText.impactos.guardado)
          }}
        />
      )}
    </div>
  )
}
