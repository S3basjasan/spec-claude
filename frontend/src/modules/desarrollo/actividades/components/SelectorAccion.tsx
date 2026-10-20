import { useMemo, useState } from 'react'
import { Combobox } from '../../../../shared/ui/Combobox'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Alert } from '../../../../shared/ui/Alert'
import type { AccionDisponible, PlanOpcion } from '../api'
import { useAccionesDisponibles } from '../hooks/useActividades'
import { actividadesText } from '../text'

const t = actividadesText.formulario

interface SelectorAccionProps {
  planes: PlanOpcion[]
  valor: number | null
  onChange: (accion: AccionDisponible | null) => void
  error?: string
}

/**
 * Selector de la acción del plan: lista las acciones no canceladas de planes Ejecutando que aún no tienen actividad,
 * con búsqueda por nombre (al escribir) y filtro por plan.
 */
export function SelectorAccion({ planes, valor, onChange, error }: SelectorAccionProps) {
  const [planId, setPlanId] = useState<number | undefined>(undefined)
  const acciones = useAccionesDisponibles({ plan_id: planId })
  const lista = acciones.data?.data

  const opciones = useMemo(
    () =>
      (lista ?? []).map((a) => ({
        value: String(a.accion_id),
        label: a.nombre,
        description: `${a.plan.codigo} · ${actividadesText.rango(a.fecha_inicio, a.fecha_fin)}`,
      })),
    [lista],
  )

  return (
    <div className="flex flex-col gap-3">
      <SelectField
        id="actividad-filtro-plan"
        label={t.filtroPlan}
        value={planId === undefined ? '' : String(planId)}
        onChange={(e) => {
          setPlanId(e.target.value === '' ? undefined : Number(e.target.value))
          onChange(null)
        }}
        options={[{ value: '', label: t.todosLosPlanes }, ...planes.filter((p) => p.estado === 'Ejecutando').map((p) => ({ value: String(p.id), label: `${p.codigo} · ${p.nombre}` }))]}
      />
      {acciones.isPending ? (
        <div role="status" aria-label={t.cargandoAcciones}>
          <Skeleton className="h-11" />
        </div>
      ) : acciones.isError ? (
        <Alert>{actividadesText.error.description}</Alert>
      ) : (
        <>
          <Combobox
            id="actividad-accion"
            label={t.accion}
            placeholder={t.accionPlaceholder}
            options={opciones}
            value={valor === null ? null : String(valor)}
            onChange={(v) => onChange(v === null ? null : (lista?.find((a) => String(a.accion_id) === v) ?? null))}
            error={error}
            emptyText={t.accionVacia}
          />
          {opciones.length === 0 && <p className="text-sm text-muted">{t.accionVacia}</p>}
        </>
      )}
    </div>
  )
}
