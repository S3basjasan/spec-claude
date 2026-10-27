import { describe, expect, it } from 'vitest'
import { problemaDeSalida } from './errores'
import { esFechaValida, esquemaDeCancelacion, esquemaDeSalida, hoyIso } from './validation'
import { httpError, networkError } from '../../../test/apiErrors'

const valida = { colaborador_id: 3, motivo: 'Renuncia', fecha_efectiva: '2999-12-01' }

describe('validación de offboarding', () => {
  it('valida el formato y la existencia de las fechas', () => {
    expect(esFechaValida('2026-02-28')).toBe(true)
    expect(esFechaValida('2026-02-30')).toBe(false)
    expect(esFechaValida('01/11/2026')).toBe(false)
    expect(esFechaValida('')).toBe(false)
  })

  it('la formalización exige persona, motivo del catálogo y fecha efectiva no pasada', () => {
    expect(esquemaDeSalida.safeParse(valida).success).toBe(true)
    expect(esquemaDeSalida.safeParse({ ...valida, fecha_efectiva: hoyIso() }).success).toBe(true)
    expect(esquemaDeSalida.safeParse({ ...valida, colaborador_id: undefined }).success).toBe(false)
    expect(esquemaDeSalida.safeParse({ ...valida, motivo: 'Despido' }).success).toBe(false)
    expect(esquemaDeSalida.safeParse({ ...valida, motivo: undefined }).success).toBe(false)
    expect(esquemaDeSalida.safeParse({ ...valida, fecha_efectiva: '2000-01-01' }).success).toBe(false)
    expect(esquemaDeSalida.safeParse({ ...valida, fecha_efectiva: '2026-13-01' }).success).toBe(false)
  })

  it('la cancelación exige una razón de hasta 1000 caracteres', () => {
    expect(esquemaDeCancelacion.safeParse({ razon: 'Se retiró la renuncia' }).success).toBe(true)
    expect(esquemaDeCancelacion.safeParse({ razon: '   ' }).success).toBe(false)
    expect(esquemaDeCancelacion.safeParse({ razon: 'a'.repeat(1001) }).success).toBe(false)
  })
})

describe('errores de offboarding', () => {
  it('distingue políticas, vínculos, evidencia y cierre de un error genérico', () => {
    expect(problemaDeSalida(httpError(422, { code: 'POLITICA_SIN_CONFIGURAR' })).mensaje).toMatch(/Política sin configurar/)
    expect(problemaDeSalida(httpError(503, { code: 'POLITICA_NO_DISPONIBLE' })).mensaje).toMatch(/no se pudo determinar la política/i)
    expect(problemaDeSalida(httpError(422, { code: 'SIN_VINCULO_VIGENTE' })).mensaje).toMatch(/vínculo laboral vigente/)
    expect(problemaDeSalida(httpError(422, { code: 'EVIDENCIA_REQUERIDA' })).mensaje).toMatch(/respaldo/)
    expect(problemaDeSalida(httpError(500)).mensaje).toMatch(/error inesperado/)
  })

  it('un conflicto de versión se explica y no se trata como error genérico', () => {
    const p = problemaDeSalida(httpError(409, { code: 'EXPEDIENTE_MODIFICADO' }))
    expect(p.conflicto).toBe(true)
    expect(p.mensaje).toMatch(/no se reenvió/)
    expect(problemaDeSalida(httpError(409, { code: 'ESTADO_NO_PERMITIDO' })).conflicto).toBe(false)
  })

  it('conserva los bloqueos del cierre y la incidencia de aplicación del servidor', () => {
    const cierre = problemaDeSalida(httpError(422, { code: 'CIERRE_NO_PROCEDE', bloqueos: ['SALIDA_NO_EFECTIVA', 7] }))
    expect(cierre.bloqueos).toEqual(['SALIDA_NO_EFECTIVA'])

    const incidencia = problemaDeSalida(httpError(409, { code: 'INCIDENCIA_DE_APLICACION', incidencia: { codigo: 'VINCULO_NO_VIGENTE', detalle: 'ya terminó' } }))
    expect(incidencia.incidencia).toEqual({ codigo: 'VINCULO_NO_VIGENTE', detalle: 'ya terminó' })
    expect(incidencia.mensaje).toMatch(/No se hizo ningún cambio/)
  })

  it('marca el 404 y los errores de red', () => {
    expect(problemaDeSalida(httpError(404, { code: 'EXPEDIENTE_NO_ENCONTRADO' })).noEncontrado).toBe(true)
    expect(problemaDeSalida(networkError()).mensaje).toMatch(/No se pudo conectar/)
  })
})
