import { Button } from '../../../../shared/ui/Button'
import { IconArrowDown, IconArrowUp, IconPlus, IconTrash } from '../../../../shared/ui/icons'
import { TextField } from '../../../../shared/ui/TextField'
import type { Tarea } from '../api'
import { politicasText } from '../text'
import { LIMITES } from '../validation'

const text = politicasText.params.tasks

interface EditorDeTareasProps {
  /** Prefijo de los ids de los campos. */
  id: string
  tareas: Tarea[]
  onChange: (tareas: Tarea[]) => void
  /** Errores por campo con las claves del servidor: `parametros.tareas.<n>.nombre`, etc. */
  errores: Record<string, string>
  /** Error del grupo (por ejemplo, más de 30 tareas). */
  errorDelGrupo?: string
  disabled?: boolean
}

/** Alta, edición, quita y reordenado accesible de las tareas de la plantilla de Offboarding. */
export function EditorDeTareas({ id, tareas, onChange, errores, errorDelGrupo, disabled = false }: EditorDeTareasProps) {
  const reemplazar = (indice: number, cambios: Partial<Tarea>) => onChange(tareas.map((t, i) => (i === indice ? { ...t, ...cambios } : t)))
  const mover = (indice: number, delta: -1 | 1) => {
    const destino = indice + delta
    if (destino < 0 || destino >= tareas.length) return
    const copia = [...tareas]
    ;[copia[indice], copia[destino]] = [copia[destino], copia[indice]]
    onChange(copia)
  }
  const alMaximo = tareas.length >= LIMITES.maxTareas

  return (
    <fieldset className="flex flex-col gap-3" disabled={disabled}>
      <legend className="text-sm font-semibold">{text.title}</legend>
      <p className="text-[13px] text-muted">{text.max(LIMITES.maxTareas)}</p>
      {errorDelGrupo && (
        <p role="alert" className="text-[13px] text-danger">
          {errorDelGrupo}
        </p>
      )}

      {tareas.length === 0 && <p className="text-sm text-muted">{text.empty}</p>}

      <ol className="flex flex-col gap-3">
        {tareas.map((tarea, i) => {
          const base = `parametros.tareas.${i}`
          return (
            <li key={i} className="flex flex-col gap-2 rounded-lg border border-border p-3">
              <TextField
                id={`${id}-nombre-${i}`}
                label={text.name(i + 1)}
                value={tarea.nombre}
                onChange={(e) => reemplazar(i, { nombre: e.target.value })}
                error={errores[`${base}.nombre`]}
                maxLength={LIMITES.tareaNombre + 20}
              />
              <TextField
                id={`${id}-descripcion-${i}`}
                label={text.description(i + 1)}
                value={tarea.descripcion ?? ''}
                onChange={(e) => reemplazar(i, { descripcion: e.target.value })}
                error={errores[`${base}.descripcion`]}
              />
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={tarea.exige_evidencia} onChange={(e) => reemplazar(i, { exige_evidencia: e.target.checked })} className="size-5" />
                {text.evidence}
              </label>
              <div className="flex flex-wrap gap-2">
                <Button variant="secondary" aria-label={text.up(i + 1)} disabled={i === 0} onClick={() => mover(i, -1)}>
                  <IconArrowUp size={16} />
                </Button>
                <Button variant="secondary" aria-label={text.down(i + 1)} disabled={i === tareas.length - 1} onClick={() => mover(i, 1)}>
                  <IconArrowDown size={16} />
                </Button>
                <Button variant="secondary" aria-label={text.remove(i + 1)} onClick={() => onChange(tareas.filter((_, j) => j !== i))}>
                  <IconTrash size={16} />
                </Button>
              </div>
            </li>
          )
        })}
      </ol>

      <div>
        <Button variant="secondary" disabled={alMaximo} onClick={() => onChange([...tareas, { nombre: '', descripcion: null, exige_evidencia: false }])}>
          <IconPlus size={16} />
          {text.add}
        </Button>
        {alMaximo && <p className="mt-1 text-[13px] text-muted">{text.maxReached}</p>}
      </div>
    </fieldset>
  )
}
