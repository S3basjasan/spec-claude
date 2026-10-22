import { Combobox, type ComboboxOption } from '../../../../shared/ui/Combobox'
import { SelectField, type SelectOption } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import type { ColaboradorOpcion, OpcionesDeColaborador } from '../api'
import { adecuacionText } from '../text'

const t = adecuacionText.seleccion

/** Valor del selector de objetivo: `i:<inscripción>` (nivel objetivo de su ruta) o `p:<puesto>` (puesto directo). */
export type ObjetivoValor = `i:${number}` | `p:${number}` | ''

interface Props {
  colaboradores: ColaboradorOpcion[]
  colaboradorId: number | null
  onColaborador: (id: number | null) => void
  opciones: OpcionesDeColaborador | undefined
  objetivo: ObjetivoValor
  onObjetivo: (valor: ObjetivoValor) => void
  experiencia: string
  onExperiencia: (texto: string) => void
  errorExperiencia?: string
  /** Colaborador elegido sin ninguna inscripción con nivel objetivo. */
  sinObjetivoPorDefecto: boolean
  nombreColaborador: string | null
}

/** Paso «Selección»: colaborador (con búsqueda) y puesto objetivo, por defecto el de su inscripción Activa. */
export function SelectorDeColaboradorYObjetivo({
  colaboradores,
  colaboradorId,
  onColaborador,
  opciones,
  objetivo,
  onObjetivo,
  experiencia,
  onExperiencia,
  errorExperiencia,
  sinObjetivoPorDefecto,
  nombreColaborador,
}: Props) {
  const opcionesDeColaborador: ComboboxOption[] = colaboradores.map((c) => ({
    value: String(c.id),
    label: c.departamento === null ? c.nombre : `${c.nombre} · ${c.departamento}`,
  }))

  const inscripciones = (opciones?.inscripciones ?? []).filter((i) => i.nivel_objetivo !== null)
  const opcionesDeObjetivo: SelectOption[] = [
    { value: '', label: inscripciones.length <= 1 ? t.objetivoPorDefecto : '—' },
    ...inscripciones.map((i) => ({
      value: `i:${i.inscripcion_id}`,
      label: t.objetivoInscripcion(i.ruta_nombre, i.nivel_objetivo?.orden ?? 0, i.nivel_objetivo?.nombre ?? ''),
      group: 'Ruta de carrera',
    })),
    ...(opciones?.puestos ?? []).map((p) => ({ value: `p:${p.id}`, label: t.objetivoDirecto(`${p.nombre} (${p.codigo})`), group: 'Puesto directo' })),
  ]

  return (
    <section aria-label="Selección" className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-5">
      <div className="grid min-w-0 gap-4 sm:grid-cols-2 [&_input]:min-w-0 [&_select]:w-full [&_select]:min-w-0">
        <div className="min-w-0">
          <Combobox
            id="adecuacion-colaborador"
            label={t.colaborador}
            options={opcionesDeColaborador}
            value={colaboradorId === null ? null : String(colaboradorId)}
            onChange={(valor) => onColaborador(valor === null ? null : Number(valor))}
            placeholder={t.colaboradorPlaceholder}
            emptyText={t.colaboradorVacio}
          />
        </div>
        <div className="min-w-0">
          <SelectField
            id="adecuacion-objetivo"
            label={t.objetivo}
            options={opcionesDeObjetivo}
            value={objetivo}
            disabled={colaboradorId === null}
            onChange={(e) => onObjetivo(e.target.value as ObjetivoValor)}
          />
        </div>
      </div>

      {objetivo.startsWith('p:') && (
        <div className="max-w-xs">
          <TextField
            id="adecuacion-experiencia"
            label={t.experiencia}
            hint={t.experienciaAyuda}
            inputMode="decimal"
            value={experiencia}
            error={errorExperiencia}
            onChange={(e) => onExperiencia(e.target.value)}
          />
        </div>
      )}

      {sinObjetivoPorDefecto && nombreColaborador !== null && (
        <p className="m-0 rounded-xl border border-dashed border-border p-4 text-muted">{t.sinObjetivo(nombreColaborador)}</p>
      )}
    </section>
  )
}
