import { useState } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { IconPencil, IconPlus, IconTrash } from '../../../../shared/ui/icons'
import { estaCompleta, nuevaClave, type AccionAsistente } from '../asistente'
import { useCursosParaPlan, useOpcionesPlan } from '../hooks/useOpcionesPlan'
import { planText } from '../text'
import { valoresDeAccion } from '../validation'
import { ChipTipo } from './ChipTipo'
import { FormularioAccion } from './FormularioAccion'

const t = planText.asistente.paso2

interface Paso2AccionesProps {
  anio: number
  acciones: AccionAsistente[]
  /** Nombres de competencias ya asociadas que hoy no están activas (para mostrarlas al editar). */
  nombresExtra: Record<number, string>
  onChange: (acciones: AccionAsistente[]) => void
}

/** Paso 2 del asistente: lista de acciones con agregar, editar y quitar (con confirmación). */
export function Paso2Acciones({ anio, acciones, nombresExtra, onChange }: Paso2AccionesProps) {
  const opciones = useOpcionesPlan()
  const [formulario, setFormulario] = useState<{ abierto: boolean; clave: string | null }>({ abierto: false, clave: null })
  const [porQuitar, setPorQuitar] = useState<AccionAsistente | null>(null)
  const cursos = useCursosParaPlan(formulario.abierto)

  const enEdicion = acciones.find((a) => a.clave === formulario.clave)
  const nombreCompetencia = (id: number) => opciones.data?.competencias.find((c) => c.id === id)?.nombre ?? nombresExtra[id] ?? `#${id}`

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-lg font-bold">
          {t.title} ({acciones.length})
        </h2>
        <Button onClick={() => setFormulario({ abierto: true, clave: null })}>
          <IconPlus size={18} />
          {t.agregar}
        </Button>
      </div>

      {acciones.length === 0 ? (
        <p className="rounded-xl border border-dashed border-border bg-surface px-4 py-8 text-center text-muted">{t.vacio}</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {acciones.map((accion) => (
            <li key={accion.clave} className="rounded-xl border border-border bg-surface p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="text-base font-bold">{accion.datos.nombre}</p>
                  <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-muted">
                    <ChipTipo tipo={accion.datos.tipo} />
                    <span>{accion.datos.competencia_ids.map(nombreCompetencia).join(', ')}</span>
                    <span>{planText.rango(accion.datos.fecha_inicio, accion.datos.fecha_fin)}</span>
                    <span>{t.cupos(accion.datos.capacidad_maxima)}</span>
                    <span>{planText.dinero(accion.datos.costo)}</span>
                    <span className={estaCompleta(accion) ? 'text-accent' : 'text-warn'}>{estaCompleta(accion) ? t.completa : t.incompleta}</span>
                  </div>
                </div>
                <div className="flex gap-2">
                  <Button variant="secondary" aria-label={`${t.editar} ${accion.datos.nombre}`} onClick={() => setFormulario({ abierto: true, clave: accion.clave })}>
                    <IconPencil size={16} />
                    {t.editar}
                  </Button>
                  <Button variant="secondary" aria-label={`${t.quitar} ${accion.datos.nombre}`} onClick={() => setPorQuitar(accion)}>
                    <IconTrash size={16} />
                    {t.quitar}
                  </Button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}

      <FormularioAccion
        open={formulario.abierto}
        anio={anio}
        inicial={enEdicion ? valoresDeAccion(enEdicion.datos) : undefined}
        opciones={opciones.data}
        cursos={cursos.data}
        nombresExtra={nombresExtra}
        onCancelar={() => setFormulario({ abierto: false, clave: null })}
        onGuardar={(datos) => {
          if (enEdicion) {
            onChange(acciones.map((a) => (a.clave === enEdicion.clave ? { ...a, datos } : a)))
          } else {
            onChange([...acciones, { clave: nuevaClave(), datos, participantes: [] }])
          }
          setFormulario({ abierto: false, clave: null })
        }}
      />

      <ConfirmDialog
        open={porQuitar !== null}
        title={t.quitarTitle}
        description={porQuitar ? t.quitarDescription(porQuitar.datos.nombre) : ''}
        confirmLabel={t.quitarConfirmar}
        cancelLabel={planText.asistente.accion.cancelar}
        onCancel={() => setPorQuitar(null)}
        onConfirm={() => {
          if (porQuitar) onChange(acciones.filter((a) => a.clave !== porQuitar.clave))
          setPorQuitar(null)
        }}
      />
    </div>
  )
}
