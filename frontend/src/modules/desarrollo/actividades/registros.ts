import { CALIFICACION_MAX, CALIFICACION_MIN, MAX_OBSERVACIONES, type Asistencia, type AsistenciaRegistrada, type Participante, type RegistroAsistencia } from './api'

/** Fila editable de la tabla de asistencia (estado local hasta pulsar Guardar). */
export interface FilaBorrador {
  colaborador_id: number
  nombre: string
  asistencia: Asistencia
  /** Valor tecleado: puede estar fuera de rango hasta que se corrija (entonces bloquea el guardado). */
  calificacion: number | null
  observaciones: string
}

export function borradorDesde(participantes: Participante[]): FilaBorrador[] {
  return participantes.map((p) => ({
    colaborador_id: p.colaborador_id,
    nombre: p.nombre,
    asistencia: p.asistencia,
    calificacion: p.calificacion,
    observaciones: p.observaciones ?? '',
  }))
}

/**
 * Elige una opción del control. Pulsar de nuevo la opción seleccionada vuelve a Pendiente. Salvo Presente, no hay
 * calificación; con Pendiente tampoco hay observaciones.
 */
export function elegirAsistencia(fila: FilaBorrador, opcion: AsistenciaRegistrada): FilaBorrador {
  if (fila.asistencia === opcion) return { ...fila, asistencia: 'Pendiente', calificacion: null, observaciones: '' }
  return { ...fila, asistencia: opcion, calificacion: opcion === 'Presente' ? fila.calificacion : null }
}

/** «Marcar todos presentes»: solo cambia a quienes siguen en Pendiente; el resto conserva su registro. */
export function marcarTodosPresentes(filas: FilaBorrador[]): FilaBorrador[] {
  return filas.map((f) => (f.asistencia === 'Pendiente' ? { ...f, asistencia: 'Presente' as const } : f))
}

export function cambiarCalificacion(fila: FilaBorrador, calificacion: number | null): FilaBorrador {
  return fila.asistencia === 'Presente' ? { ...fila, calificacion } : fila
}

export function cambiarObservaciones(fila: FilaBorrador, observaciones: string): FilaBorrador {
  return fila.asistencia === 'Pendiente' ? fila : { ...fila, observaciones }
}

/** Mensaje de error de una calificación fuera de rango o decimal; vacío si es válida. */
export function errorDeCalificacion(fila: FilaBorrador, mensajes: { calificacion: string }): string | undefined {
  const { calificacion } = fila
  if (fila.asistencia !== 'Presente' || calificacion === null) return undefined
  return Number.isInteger(calificacion) && calificacion >= CALIFICACION_MIN && calificacion <= CALIFICACION_MAX ? undefined : mensajes.calificacion
}

export function errorDeObservaciones(fila: FilaBorrador, mensaje: string): string | undefined {
  return fila.observaciones.length > MAX_OBSERVACIONES ? mensaje : undefined
}

function igual(a: FilaBorrador, b: FilaBorrador): boolean {
  return a.asistencia === b.asistencia && a.calificacion === b.calificacion && a.observaciones.trim() === b.observaciones.trim()
}

/** Filas cuyo valor difiere del original. */
export function filasModificadas(actuales: FilaBorrador[], originales: FilaBorrador[]): FilaBorrador[] {
  const porId = new Map(originales.map((o) => [o.colaborador_id, o]))
  return actuales.filter((f) => {
    const original = porId.get(f.colaborador_id)
    return original !== undefined && !igual(f, original)
  })
}

export function hayCambios(actuales: FilaBorrador[], originales: FilaBorrador[]): boolean {
  return filasModificadas(actuales, originales).length > 0
}

/** Cuerpo del guardado: solo lo que cambió, con calificación únicamente para Presente y sin observaciones en Pendiente. */
export function registrosParaGuardar(actuales: FilaBorrador[], originales: FilaBorrador[]): RegistroAsistencia[] {
  return filasModificadas(actuales, originales).map((f) => ({
    colaborador_id: f.colaborador_id,
    asistencia: f.asistencia,
    calificacion: f.asistencia === 'Presente' ? f.calificacion : null,
    observaciones: f.asistencia === 'Pendiente' ? null : f.observaciones.trim() || null,
  }))
}

export interface CambioSobreRegistro {
  colaborador_id: number
  nombre: string
  antes: string
  despues: string
}

function etiqueta(fila: FilaBorrador, nombres: Record<Asistencia, string>): string {
  const base = nombres[fila.asistencia]
  return fila.asistencia === 'Presente' && fila.calificacion !== null ? `${base} (${fila.calificacion})` : base
}

/** Cambios sobre participantes que ya tenían un registro guardado (piden confirmación antes de guardar). */
export function cambiosSobreExistentes(actuales: FilaBorrador[], originales: FilaBorrador[], nombres: Record<Asistencia, string>): CambioSobreRegistro[] {
  const porId = new Map(originales.map((o) => [o.colaborador_id, o]))

  return filasModificadas(actuales, originales)
    .map((f) => ({ fila: f, original: porId.get(f.colaborador_id) as FilaBorrador }))
    .filter(({ original }) => original.asistencia !== 'Pendiente')
    .map(({ fila, original }) => ({
      colaborador_id: fila.colaborador_id,
      nombre: fila.nombre,
      antes: etiqueta(original, nombres),
      despues: fila.asistencia === original.asistencia && fila.calificacion === original.calificacion ? `${etiqueta(fila, nombres)} · observaciones` : etiqueta(fila, nombres),
    }))
}

/** Cuenta de cada situación del borrador para el resumen. */
export function resumenDelBorrador(filas: FilaBorrador[]): { presentes: number; ausentes: number; justificados: number; sinRegistrar: number } {
  const cuenta = { presentes: 0, ausentes: 0, justificados: 0, sinRegistrar: 0 }
  for (const f of filas) {
    if (f.asistencia === 'Presente') cuenta.presentes++
    else if (f.asistencia === 'Ausente') cuenta.ausentes++
    else if (f.asistencia === 'Justificado') cuenta.justificados++
    else cuenta.sinRegistrar++
  }
  return cuenta
}
