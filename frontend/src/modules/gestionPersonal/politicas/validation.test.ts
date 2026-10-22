import { describe, expect, it } from 'vitest'
import { definicionDe } from '../../../test/politicasFixtures'
import {
  erroresDeParametros,
  formatearInstanteLocal,
  formatearValor,
  LIMITES,
  parametrosParaEnviar,
  problemaDeGuardado,
  publicacionSchema,
  retiroSchema,
} from './validation'
import { httpError, networkError } from '../../../test/apiErrors'

const valido = { motivo: 'Revisión', revisor: 'Ana', observaciones: '', modo: 'inmediato' as const, fecha_hora_local: '' }

describe('publicacionSchema', () => {
  it('exige motivo y revisor y respeta los máximos', () => {
    expect(publicacionSchema.safeParse(valido).success).toBe(true)
    expect(publicacionSchema.safeParse({ ...valido, motivo: '  ' }).success).toBe(false)
    expect(publicacionSchema.safeParse({ ...valido, revisor: '' }).success).toBe(false)
    expect(publicacionSchema.safeParse({ ...valido, motivo: 'a'.repeat(LIMITES.motivo + 1) }).success).toBe(false)
    expect(publicacionSchema.safeParse({ ...valido, revisor: 'a'.repeat(LIMITES.revisor + 1) }).success).toBe(false)
    expect(publicacionSchema.safeParse({ ...valido, observaciones: 'a'.repeat(LIMITES.observacionesRevision + 1) }).success).toBe(false)
  })

  it('un inicio programado exige la fecha y hora', () => {
    expect(publicacionSchema.safeParse({ ...valido, modo: 'programado', fecha_hora_local: '' }).success).toBe(false)
    expect(publicacionSchema.safeParse({ ...valido, modo: 'programado', fecha_hora_local: '2030-01-01T08:00' }).success).toBe(true)
  })
})

describe('retiroSchema', () => {
  it('exige justificación de hasta 500 caracteres', () => {
    expect(retiroSchema.safeParse({ justificacion: '  ' }).success).toBe(false)
    expect(retiroSchema.safeParse({ justificacion: 'a'.repeat(501) }).success).toBe(false)
    expect(retiroSchema.safeParse({ justificacion: 'Motivo' }).success).toBe(true)
  })
})

describe('erroresDeParametros', () => {
  it('la anticipación debe ser un entero mayor o igual que cero; vacío no es error al guardar', () => {
    const def = definicionDe('permisos')
    expect(erroresDeParametros(def, { anticipacion_dias: '-1' })['parametros.anticipacion_dias']).toBeDefined()
    expect(erroresDeParametros(def, { anticipacion_dias: '2.5' })['parametros.anticipacion_dias']).toBeDefined()
    expect(erroresDeParametros(def, { anticipacion_dias: 'dos' })['parametros.anticipacion_dias']).toBeDefined()
    expect(erroresDeParametros(def, { anticipacion_dias: '0' })).toEqual({})
    expect(erroresDeParametros(def, { anticipacion_dias: 3 })).toEqual({})
    expect(erroresDeParametros(def, {})).toEqual({})
  })

  it('las tareas exigen nombre, únicos sin distinguir mayúsculas ni tildes, y máximo 30', () => {
    const def = definicionDe('offboarding')
    const tarea = (nombre: string) => ({ nombre, descripcion: null, exige_evidencia: false })

    const errores = erroresDeParametros(def, { tareas: [tarea('Devolución de equipos'), tarea('  '), tarea('DEVOLUCION DE EQUIPOS')] })
    expect(Object.keys(errores)).toEqual(['parametros.tareas.1.nombre', 'parametros.tareas.2.nombre'])

    expect(erroresDeParametros(def, { tareas: Array.from({ length: 31 }, (_, i) => tarea(`Tarea ${i}`)) })['parametros.tareas']).toBeDefined()
    expect(erroresDeParametros(def, { tareas: Array.from({ length: 30 }, (_, i) => tarea(`Tarea ${i}`)) })).toEqual({})
  })
})

describe('parametrosParaEnviar', () => {
  it('convierte los enteros escritos, omite lo vacío y recorta las tareas', () => {
    expect(parametrosParaEnviar(definicionDe('permisos'), { disponible: true, anticipacion_dias: '3', documento_obligatorio: undefined })).toEqual({ disponible: true, anticipacion_dias: 3 })
    expect(parametrosParaEnviar(definicionDe('offboarding'), { tareas: [{ nombre: ' A ', descripcion: '  ', exige_evidencia: true }] })).toEqual({
      tareas: [{ nombre: 'A', descripcion: null, exige_evidencia: true }],
    })
  })

  it('ignora claves que el tipo no admite', () => {
    expect(parametrosParaEnviar(definicionDe('ausencias'), { disponible: true, anticipacion_dias: 2 })).toEqual({ disponible: true })
  })
})

describe('problemaDeGuardado', () => {
  it('traduce cada respuesta del servidor a lo que muestra la pantalla', () => {
    expect(problemaDeGuardado(networkError()).aviso).toBe('red')
    expect(problemaDeGuardado(httpError(409, { code: 'BORRADOR_MODIFICADO' })).aviso).toBe('conflicto')
    expect(problemaDeGuardado(httpError(409, { code: 'PROGRAMADA_EXISTENTE' })).aviso).toBe('programada')
    expect(problemaDeGuardado(httpError(409, { code: 'VIGENCIA_INICIADA' })).aviso).toBe('iniciada')
    expect(problemaDeGuardado(httpError(409, { code: 'VERSION_NO_RETIRABLE' })).aviso).toBe('noRetirable')
    expect(problemaDeGuardado(httpError(404, { code: 'POLITICA_NO_ENCONTRADA' })).aviso).toBe('noExiste')
    expect(problemaDeGuardado(httpError(503, { code: 'ESCRITURA_DESHABILITADA' })).aviso).toBe('deshabilitada')
    expect(problemaDeGuardado(httpError(500)).aviso).toBe('inesperado')
    expect(problemaDeGuardado(httpError(422, { code: 'VALIDACION', errors: { motivo: ['Este campo es obligatorio.'] } }))).toEqual({
      campos: { motivo: 'Este campo es obligatorio.' },
      aviso: null,
    })
  })
})

describe('formatos', () => {
  it('formatea instantes locales y valores de diferencias', () => {
    expect(formatearInstanteLocal('2026-11-01T08:00')).toBe('01/11/2026 08:00')
    expect(formatearInstanteLocal(null)).toBe('—')
    expect(formatearValor(true)).toBe('Sí')
    expect(formatearValor(false)).toBe('No')
    expect(formatearValor(3)).toBe('3 días calendario')
    expect(formatearValor(null)).toBe('Sin definir')
    expect(formatearValor([{ nombre: 'A' }, { nombre: 'B' }])).toBe('2 · A, B')
    expect(formatearValor([])).toContain('No hay tareas')
  })
})
