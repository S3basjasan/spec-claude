import type { AccionBorrador, Plan, PlanBorrador } from './api'
import type { ValoresPaso1 } from './validation'

/** Acción tal como se edita en el asistente: sin id de servidor hasta que se guarda por primera vez. */
export interface AccionAsistente {
  /** Clave local estable para la lista (no viaja al servidor). */
  clave: string
  /** Id de servidor si la acción ya existe en el plan. */
  id?: number
  datos: Omit<AccionBorrador, 'id' | 'colaborador_ids'>
  participantes: { id: number; nombre: string }[]
}

export interface DatosAsistente extends ValoresPaso1 {
  aprobador_id: number | null
  acciones: AccionAsistente[]
}

let secuencia = 0

export function nuevaClave(): string {
  secuencia += 1
  return `accion-${secuencia}`
}

export function anioActual(): string {
  return String(new Date().getFullYear())
}

export function datosVacios(): DatosAsistente {
  return { nombre: '', anio: anioActual(), descripcion: '', aprobador_id: null, acciones: [] }
}

/** Datos del asistente a partir de un plan guardado (edición de un Borrador). */
export function datosDesdePlan(plan: Plan): DatosAsistente {
  return {
    nombre: plan.nombre,
    anio: String(plan.anio),
    descripcion: plan.descripcion ?? '',
    aprobador_id: plan.aprobador?.id ?? null,
    acciones: plan.acciones.map((a) => ({
      clave: nuevaClave(),
      id: a.id,
      datos: {
        nombre: a.nombre,
        curso_id: a.curso_id,
        tipo: a.tipo,
        fecha_inicio: a.fecha_inicio,
        fecha_fin: a.fecha_fin,
        duracion_horas: a.duracion_horas,
        formador: a.formador,
        capacidad_maxima: a.capacidad_maxima,
        costo: a.costo,
        competencia_ids: a.competencias.map((c) => c.id),
      },
      participantes: a.participantes.map((p) => ({ id: p.colaborador_id, nombre: p.nombre })),
    })),
  }
}

/** Cuerpo para `POST`/`PUT /api/planes`. */
export function planBorrador(datos: DatosAsistente): PlanBorrador {
  return {
    nombre: datos.nombre.trim(),
    anio: Number(datos.anio),
    descripcion: datos.descripcion.trim() === '' ? null : datos.descripcion.trim(),
    aprobador_id: datos.aprobador_id,
    acciones: datos.acciones.map((a) => ({
      ...(a.id === undefined ? {} : { id: a.id }),
      ...a.datos,
      colaborador_ids: a.participantes.map((p) => p.id),
    })),
  }
}

/** Una acción con todos sus datos obligatorios para enviar a revisión (informativo en el asistente). */
export function estaCompleta(accion: AccionAsistente): boolean {
  const d = accion.datos
  return (
    d.tipo !== null &&
    d.fecha_inicio !== null &&
    d.fecha_fin !== null &&
    d.duracion_horas !== null &&
    d.formador !== null &&
    d.capacidad_maxima !== null &&
    d.costo !== null &&
    d.competencia_ids.length > 0 &&
    accion.participantes.length > 0
  )
}

export function costoEstimado(datos: DatosAsistente): number {
  return datos.acciones.reduce((suma, a) => suma + (a.datos.costo ?? 0), 0)
}

export function participantesUnicos(datos: DatosAsistente): number {
  return new Set(datos.acciones.flatMap((a) => a.participantes.map((p) => p.id))).size
}
