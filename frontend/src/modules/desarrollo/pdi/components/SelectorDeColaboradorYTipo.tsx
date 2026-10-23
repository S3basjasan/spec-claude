import { Link } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Combobox } from '../../../../shared/ui/Combobox'
import { SelectField } from '../../../../shared/ui/SelectField'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TIPOS_PDI, type OpcionesDeColaborador, type OpcionesGenerales, type Precarga, type TipoPdi } from '../api'
import type { DatosAsistente } from '../asistente'
import { pdiText } from '../text'

const t = pdiText.asistente.paso1

interface SelectorDeColaboradorYTipoProps {
  datos: DatosAsistente
  errores: Record<string, string>
  opciones: OpcionesGenerales | undefined
  deColaborador: OpcionesDeColaborador | undefined
  precarga: Precarga | undefined
  usarPrecarga: boolean
  /** En edición el colaborador y el tipo no cambian. */
  bloqueado: boolean
  onChange: (cambios: Partial<DatosAsistente>) => void
  onUsarPrecarga: (usar: boolean) => void
}

/** Paso 1: colaborador, tipo, puesto objetivo, objetivo y oferta de precarga (aviso si ya hay un plan abierto del tipo). */
export function SelectorDeColaboradorYTipo({ datos, errores, opciones, deColaborador, precarga, usarPrecarga, bloqueado, onChange, onUsarPrecarga }: SelectorDeColaboradorYTipoProps) {
  const planAbierto = bloqueado ? null : (deColaborador?.plan_abierto[datos.tipo] ?? null)
  const sugerido = deColaborador?.puesto_objetivo_sugerido ?? null
  const puestos = opciones?.puestos ?? []

  const elegirTipo = (tipo: TipoPdi) => {
    onChange({ tipo, puesto_objetivo_id: tipo === 'Mejora' ? null : (datos.puesto_objetivo_id ?? sugerido?.id ?? null) })
  }

  return (
    <div className="flex flex-col gap-5">
      <Combobox
        id="pdi-colaborador"
        label={t.colaborador}
        placeholder={t.buscarColaborador}
        options={(opciones?.colaboradores ?? []).map((c) => ({ value: String(c.id), label: c.nombre, description: [c.codigo, c.departamento].filter(Boolean).join(' · ') }))}
        value={datos.colaborador_id === null ? null : String(datos.colaborador_id)}
        disabled={bloqueado}
        error={errores.colaborador_id}
        onChange={(valor) => {
          const elegido = opciones?.colaboradores.find((c) => String(c.id) === valor)
          onChange({ colaborador_id: elegido?.id ?? null, colaborador_nombre: elegido?.nombre ?? '', puesto_objetivo_id: null, origen: null })
        }}
      />

      <SelectField
        id="pdi-tipo"
        label={t.tipo}
        options={TIPOS_PDI.map((tipo) => ({ value: tipo, label: pdiText.tiposDescripcion[tipo] }))}
        value={datos.tipo}
        disabled={bloqueado}
        onChange={(e) => elegirTipo(e.target.value as TipoPdi)}
        error={errores.tipo}
      />

      {datos.tipo !== 'Mejora' && (
        <div className="flex flex-col gap-1">
          <SelectField
            id="pdi-puesto"
            label={t.puestoObjetivo}
            options={[{ value: '', label: t.elegirPuesto }, ...puestos.map((p) => ({ value: String(p.id), label: `${p.nombre} (${p.codigo})` }))]}
            value={datos.puesto_objetivo_id === null ? '' : String(datos.puesto_objetivo_id)}
            disabled={bloqueado}
            onChange={(e) => onChange({ puesto_objetivo_id: e.target.value === '' ? null : Number(e.target.value), origen: null })}
            error={errores.puesto_objetivo_id}
          />
          {sugerido !== null && datos.puesto_objetivo_id === sugerido.id && <p className="m-0 text-[13px] text-muted">{t.puestoSugerido}</p>}
        </div>
      )}

      <TextareaField id="pdi-objetivo" label={t.objetivo} hint={t.objetivoAyuda} value={datos.objetivo} maxLength={500} currentLength={datos.objetivo.length} onChange={(e) => onChange({ objetivo: e.target.value })} error={errores.objetivo} />

      {planAbierto !== null && (
        <Alert>
          {t.planAbierto(planAbierto.codigo)}{' '}
          <Link to={`/pdi/${planAbierto.id}`} className="font-semibold underline">
            {t.verPlanAbierto}
          </Link>
        </Alert>
      )}

      {!bloqueado && datos.colaborador_id !== null && precarga !== undefined && (
        <section aria-label={t.precarga} className="flex flex-col gap-2 rounded-xl bg-bg p-4">
          <h3 className="m-0 text-[15px] font-bold">{t.precarga}</h3>
          <p className="m-0 text-[13px] text-muted">{t.precargaAyuda}</p>
          {precarga.compromisos.length === 0 ? (
            <p className="m-0">{t.sinPrecarga}</p>
          ) : (
            <>
              <p className="m-0 font-semibold">
                {t.brechasDetectadas(precarga.compromisos.length)} · {precarga.origen?.tipo === 'dnf' ? t.origenDnf : t.origenAdecuacion}
              </p>
              <ul className="m-0 list-inside list-disc text-[14px]">
                {precarga.compromisos.map((c, i) => (
                  <li key={i}>
                    {c.elemento?.nombre ?? c.descripcion ?? pdiText.dimensiones[c.dimension]}
                    {c.nivel_actual !== null && c.nivel_objetivo !== null && ` (${c.nivel_actual} → ${c.nivel_objetivo})`}
                  </li>
                ))}
              </ul>
              <label className="flex min-h-11 items-center gap-2 text-[15px]">
                <input type="checkbox" checked={usarPrecarga} onChange={(e) => onUsarPrecarga(e.target.checked)} />
                {t.usarPrecarga}
              </label>
            </>
          )}
          {precarga.avisos.map((a) => (
            <p key={a.tipo} role="note" className="m-0 text-[13px] text-muted">
              {a.mensaje}
            </p>
          ))}
        </section>
      )}
    </div>
  )
}
