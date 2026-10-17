import { describe, expect, it } from 'vitest'
import type { Participante } from './api'
import {
  borradorDesde,
  cambiarCalificacion,
  cambiarObservaciones,
  cambiosSobreExistentes,
  elegirAsistencia,
  errorDeCalificacion,
  hayCambios,
  marcarTodosPresentes,
  registrosParaGuardar,
  resumenDelBorrador,
} from './registros'

const participantes: Participante[] = [
  { colaborador_id: 1, nombre: 'Ana', asistencia: 'Presente', calificacion: 4, observaciones: 'Buena' },
  { colaborador_id: 2, nombre: 'Luis', asistencia: 'Ausente', calificacion: null, observaciones: null },
  { colaborador_id: 3, nombre: 'Eva', asistencia: 'Pendiente', calificacion: null, observaciones: null },
  { colaborador_id: 4, nombre: 'Pedro', asistencia: 'Pendiente', calificacion: null, observaciones: null },
]
const nombres = { Pendiente: 'Pendiente', Presente: 'Presente', Ausente: 'Ausente', Justificado: 'Justificado' } as const

describe('borrador de asistencia', () => {
  it('parte del detalle sin cambios', () => {
    const original = borradorDesde(participantes)

    expect(original[0]).toEqual({ colaborador_id: 1, nombre: 'Ana', asistencia: 'Presente', calificacion: 4, observaciones: 'Buena' })
    expect(hayCambios(original, original)).toBe(false)
    expect(registrosParaGuardar(original, original)).toEqual([])
  })

  it('elegir una opción la selecciona y borra la calificación si no es Presente', () => {
    const [ana] = borradorDesde(participantes)

    expect(elegirAsistencia(ana, 'Ausente')).toMatchObject({ asistencia: 'Ausente', calificacion: null, observaciones: 'Buena' })
    expect(elegirAsistencia(ana, 'Justificado')).toMatchObject({ asistencia: 'Justificado', calificacion: null })
  })

  it('pulsar de nuevo la opción seleccionada vuelve a Pendiente y limpia calificación y observaciones', () => {
    const [ana] = borradorDesde(participantes)

    expect(elegirAsistencia(ana, 'Presente')).toMatchObject({ asistencia: 'Pendiente', calificacion: null, observaciones: '' })
  })

  it('la calificación solo se acepta con Presente y las observaciones no con Pendiente', () => {
    const [ana, luis, eva] = borradorDesde(participantes)

    expect(cambiarCalificacion(ana, 5).calificacion).toBe(5)
    expect(cambiarCalificacion(luis, 5).calificacion).toBeNull()
    expect(cambiarObservaciones(luis, 'Avisó').observaciones).toBe('Avisó')
    expect(cambiarObservaciones(eva, 'Nota').observaciones).toBe('')
  })

  it('marcar todos presentes solo cambia a los pendientes', () => {
    const filas = marcarTodosPresentes(borradorDesde(participantes))

    expect(filas.map((f) => f.asistencia)).toEqual(['Presente', 'Ausente', 'Presente', 'Presente'])
    expect(filas[0].calificacion).toBe(4)
  })

  it('el cuerpo del guardado incluye solo lo que cambió', () => {
    const original = borradorDesde(participantes)
    const filas = marcarTodosPresentes(original)
    filas[2] = cambiarCalificacion(filas[2], 5)

    expect(registrosParaGuardar(filas, original)).toEqual([
      { colaborador_id: 3, asistencia: 'Presente', calificacion: 5, observaciones: null },
      { colaborador_id: 4, asistencia: 'Presente', calificacion: null, observaciones: null },
    ])
  })

  it('volver a Pendiente envía Pendiente sin calificación ni observaciones', () => {
    const original = borradorDesde(participantes)
    const filas = [elegirAsistencia(original[0], 'Presente'), ...original.slice(1)]

    expect(registrosParaGuardar(filas, original)).toEqual([{ colaborador_id: 1, asistencia: 'Pendiente', calificacion: null, observaciones: null }])
  })

  it('una observación recortada vacía se envía como nula', () => {
    const original = borradorDesde(participantes)
    const filas = [cambiarObservaciones(original[0], '   '), ...original.slice(1)]

    expect(registrosParaGuardar(filas, original)).toEqual([{ colaborador_id: 1, asistencia: 'Presente', calificacion: 4, observaciones: null }])
  })

  it('detecta los cambios sobre participantes que ya tenían un registro', () => {
    const original = borradorDesde(participantes)
    const filas = marcarTodosPresentes(original)
    filas[0] = cambiarCalificacion(filas[0], 5)
    filas[1] = elegirAsistencia(filas[1], 'Justificado')

    expect(cambiosSobreExistentes(filas, original, nombres)).toEqual([
      { colaborador_id: 1, nombre: 'Ana', antes: 'Presente (4)', despues: 'Presente (5)' },
      { colaborador_id: 2, nombre: 'Luis', antes: 'Ausente', despues: 'Justificado' },
    ])
  })

  it('solo marcar pendientes no pide confirmación', () => {
    const original = borradorDesde(participantes)

    expect(cambiosSobreExistentes(marcarTodosPresentes(original), original, nombres)).toEqual([])
  })

  it('valida la calificación: entera de 1 a 5', () => {
    const [ana] = borradorDesde(participantes)
    const mensajes = { calificacion: 'Debe ser un entero de 1 a 5.' }

    expect(errorDeCalificacion({ ...ana, calificacion: 3 }, mensajes)).toBeUndefined()
    expect(errorDeCalificacion({ ...ana, calificacion: null }, mensajes)).toBeUndefined()
    for (const mala of [0, 6, 3.5, -1]) expect(errorDeCalificacion({ ...ana, calificacion: mala }, mensajes)).toBe(mensajes.calificacion)
    expect(errorDeCalificacion({ ...ana, asistencia: 'Ausente', calificacion: 9 }, mensajes)).toBeUndefined()
  })

  it('cuenta presentes, ausentes, justificados y sin registrar', () => {
    expect(resumenDelBorrador(borradorDesde(participantes))).toEqual({ presentes: 1, ausentes: 1, justificados: 0, sinRegistrar: 2 })
  })
})
