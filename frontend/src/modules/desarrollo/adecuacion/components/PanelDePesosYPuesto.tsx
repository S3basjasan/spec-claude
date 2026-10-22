import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextField } from '../../../../shared/ui/TextField'
import type { PuestoOpcion } from '../api'
import type { EstadoDeComparacion } from '../hooks/useComparacion'
import { adecuacionText } from '../text'

const t = adecuacionText.comparar

interface Props {
  puestos: PuestoOpcion[]
  puesto: number | null
  onPuesto: (id: string) => void
  /** Texto de cada peso: lo editado o, si no se editó, el que se está usando. */
  valores: { experiencia: string; conocimientos: string; competencias: string }
  onPeso: EstadoDeComparacion['editarPeso']
  suma: number | null
  error?: 'suma' | 'entero'
  hayPesosEditados: boolean
  onRestablecer: () => void
}

/** «Puesto y pesos» (orden del tablero: Experiencia, Conocimientos, Competencias). Los pesos solo valen para esta consulta. */
export function PanelDePesosYPuesto({ puestos, puesto, onPuesto, valores, onPeso, suma, error, hayPesosEditados, onRestablecer }: Props) {
  const campo = (dimension: 'experiencia' | 'conocimientos' | 'competencias', etiqueta: string) => (
    <div className="w-32">
      <TextField
        id={`peso-${dimension}`}
        label={etiqueta}
        type="number"
        inputMode="numeric"
        min={0}
        max={100}
        value={valores[dimension]}
        aria-invalid={error !== undefined}
        onChange={(e) => onPeso(dimension, e.target.value)}
      />
    </div>
  )

  return (
    <section aria-labelledby="pesos-titulo" className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-5">
      <h2 id="pesos-titulo" className="m-0 text-lg font-semibold">
        {t.pesosTitulo}
      </h2>
      <div className="flex flex-wrap items-end gap-4">
        <div className="min-w-[240px] flex-1">
          <SelectField
            id="comparar-puesto"
            label={t.puesto}
            value={puesto === null ? '' : String(puesto)}
            options={[{ value: '', label: t.puestoPlaceholder }, ...puestos.map((p) => ({ value: String(p.id), label: `${p.nombre} (${p.codigo})` }))]}
            onChange={(e) => onPuesto(e.target.value)}
          />
        </div>
        {campo('experiencia', t.experiencia)}
        {campo('conocimientos', t.conocimientos)}
        {campo('competencias', t.competencias)}
        {hayPesosEditados && (
          <Button variant="secondary" type="button" onClick={onRestablecer}>
            {t.restablecer}
          </Button>
        )}
      </div>
      <p className={`m-0 text-sm font-semibold ${error === undefined ? '' : 'text-danger'}`} role={error === undefined ? undefined : 'alert'}>
        {error === 'entero' ? t.enteroError : error === 'suma' ? `${t.suma(suma)}. ${t.sumaError}` : t.suma(suma)}
      </p>
      <p className="m-0 text-[13px] text-muted">{t.nota}</p>
    </section>
  )
}
