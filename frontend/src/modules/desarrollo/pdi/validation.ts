import type { DatosAsistente } from './asistente'
import { pdiText } from './text'

const e = pdiText.asistente.errores

/** Errores por campo; las claves siguen el formato del servidor (`compromisos.0.acciones.1.fecha_fin`). */
export type ErroresFormulario = Record<string, string>

function dentro(desde: string, hasta: string, inicio: string, fin: string): boolean {
  return desde >= inicio && hasta <= fin
}

/** Paso 1: colaborador, tipo, objetivo y puesto objetivo (obligatorio en Carrera y Talento). */
export function validarPaso1(d: DatosAsistente): ErroresFormulario {
  const errores: ErroresFormulario = {}
  if (d.colaborador_id === null) errores.colaborador_id = e.colaborador
  if (d.objetivo.trim() === '') errores.objetivo = e.objetivo
  if (d.tipo !== 'Mejora' && d.puesto_objetivo_id === null) errores.puesto_objetivo_id = e.puestoObjetivo

  return errores
}

/** Paso 2: cada compromiso con su elemento o descripción, niveles, fechas dentro del periodo y al menos una acción válida. */
export function validarPaso2(d: DatosAsistente): ErroresFormulario {
  const errores: ErroresFormulario = {}
  const vistos = new Set<string>()
  const periodoDefinido = d.fecha_inicio !== '' && d.fecha_fin !== ''

  d.compromisos.forEach((c, i) => {
    const p = `compromisos.${i}`
    if (c.dimension === 'Competencia' || c.dimension === 'Conocimiento') {
      if (c.elemento_id === null) errores[`${p}.elemento_id`] = e.elemento
      else if (vistos.has(`${c.dimension}:${c.elemento_id}`)) errores[`${p}.elemento_id`] = e.elementoRepetido
      else vistos.add(`${c.dimension}:${c.elemento_id}`)
      if ((c.nivel_actual === '') !== (c.nivel_objetivo === '')) errores[`${p}.nivel_objetivo`] = e.niveles
      else if (c.nivel_actual !== '' && c.nivel_actual === c.nivel_objetivo) errores[`${p}.nivel_objetivo`] = e.nivelesIguales
    } else if (c.descripcion.trim() === '') {
      errores[`${p}.descripcion`] = e.descripcion
    }

    if (c.fecha_inicio === '' || c.fecha_fin === '') errores[`${p}.fecha_inicio`] = e.fechas
    else if (c.fecha_fin < c.fecha_inicio) errores[`${p}.fecha_fin`] = e.fechaFin
    else if (periodoDefinido && !dentro(c.fecha_inicio, c.fecha_fin, d.fecha_inicio, d.fecha_fin)) errores[`${p}.fecha_inicio`] = e.fueraDelPeriodo

    c.acciones.forEach((a, j) => {
      const q = `${p}.acciones.${j}`
      if (a.descripcion.trim() === '') errores[`${q}.descripcion`] = e.descripcion
      if (a.fecha_inicio === '' || a.fecha_fin === '') errores[`${q}.fecha_inicio`] = e.fechas
      else if (a.fecha_fin < a.fecha_inicio) errores[`${q}.fecha_fin`] = e.fechaFin
      else if (periodoDefinido && !dentro(a.fecha_inicio, a.fecha_fin, d.fecha_inicio, d.fecha_fin)) errores[`${q}.fecha_inicio`] = e.fueraDelPeriodo
    })
  })

  return errores
}

/** Paso 3: responsable, periodo e hitos dentro del periodo. */
export function validarPaso3(d: DatosAsistente): ErroresFormulario {
  const errores: ErroresFormulario = {}
  if (d.responsable_id === null) errores.responsable_id = e.responsable
  if (d.fecha_inicio === '') errores.fecha_inicio = e.fechas
  if (d.fecha_fin === '') errores.fecha_fin = e.fechas
  else if (d.fecha_inicio !== '' && d.fecha_fin < d.fecha_inicio) errores.fecha_fin = e.fechaFin

  d.hitos.forEach((h, i) => {
    if (h.descripcion.trim() === '') errores[`hitos.${i}.descripcion`] = e.descripcion
    if (h.fecha === '') errores[`hitos.${i}.fecha`] = e.fechas
    else if (d.fecha_inicio !== '' && d.fecha_fin !== '' && !dentro(h.fecha, h.fecha, d.fecha_inicio, d.fecha_fin)) errores[`hitos.${i}.fecha`] = e.fueraDelPeriodo
  })

  return errores
}

/** Todos los errores del formulario completo. */
export function validarTodo(d: DatosAsistente): ErroresFormulario {
  return { ...validarPaso1(d), ...validarPaso2(d), ...validarPaso3(d) }
}

/** Paso al que pertenece un campo con error, para llevar a la persona a corregirlo. */
export function pasoDelCampo(campo: string): 0 | 1 | 2 {
  if (campo.startsWith('compromisos')) return 1
  if (campo === 'colaborador_id' || campo === 'objetivo' || campo === 'puesto_objetivo_id' || campo === 'tipo') return 0

  return 2
}
