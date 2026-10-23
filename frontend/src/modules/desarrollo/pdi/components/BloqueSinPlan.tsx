import { Link } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Pagination } from '../../../../shared/ui/Pagination'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useSinPlan } from '../hooks/usePdi'
import { pdiText } from '../text'

const t = pdiText.sinPlan

interface BloqueSinPlanProps {
  departamentoId: number | undefined
  pagina: number
  /** Solo se pide con el permiso de gestión. */
  habilitado: boolean
  onPagina: (pagina: number) => void
}

/** «Personas con brecha y sin PDI»: colaboradores con brechas activas y ningún plan abierto, con acceso a crearles el plan. */
export function BloqueSinPlan({ departamentoId, pagina, habilitado, onPagina }: BloqueSinPlanProps) {
  const consulta = useSinPlan(departamentoId, pagina, habilitado)

  if (!habilitado) return null

  return (
    <section aria-label={t.titulo} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="m-0 text-lg font-bold">{t.titulo}</h2>
        {consulta.isSuccess && <span className="text-[13px] text-muted">{pdiText.lista.resumen(consulta.data.meta.total)}</span>}
      </div>
      <p className="m-0 text-[13px] text-muted">{t.descripcion}</p>

      {consulta.isPending && <Skeleton className="h-16" />}
      {consulta.isError && <Alert tone="neutral">{t.error}</Alert>}
      {consulta.isSuccess && consulta.data.data.length === 0 && <p className="m-0 text-muted">{t.vacio}</p>}

      {consulta.isSuccess && consulta.data.data.length > 0 && (
        <>
          <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {consulta.data.data.map((f) => {
              const params = new URLSearchParams({ colaborador: String(f.colaborador.id), tipo: f.tipo_sugerido })
              if (f.puesto_objetivo !== null) params.set('puesto_objetivo', String(f.puesto_objetivo.id))

              return (
                <li key={`${f.colaborador.id}-${f.origen}-${f.puesto_objetivo?.id ?? 0}`} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3">
                  <div>
                    <p className="m-0 font-semibold">{f.colaborador.nombre}</p>
                    <p className="m-0 text-[13px] text-muted">
                      {[f.colaborador.departamento, t.origen[f.origen], f.puesto_objetivo?.nombre, t.brechas(f.brechas)].filter(Boolean).join(' · ')}
                    </p>
                  </div>
                  <Link to={`/pdi/nuevo?${params.toString()}`} className="inline-flex min-h-11 items-center rounded-lg border border-border px-4 font-semibold hover:bg-soft" aria-label={`${t.crear}: ${f.colaborador.nombre}`}>
                    {t.crear}
                  </Link>
                </li>
              )
            })}
          </ul>
          {consulta.data.meta.last_page > 1 && (
            <Pagination
              page={consulta.data.meta.page}
              lastPage={consulta.data.meta.last_page}
              total={consulta.data.meta.total}
              from={(consulta.data.meta.page - 1) * consulta.data.meta.per_page + 1}
              to={(consulta.data.meta.page - 1) * consulta.data.meta.per_page + consulta.data.data.length}
              onPageChange={onPagina}
            />
          )}
        </>
      )}
    </section>
  )
}
