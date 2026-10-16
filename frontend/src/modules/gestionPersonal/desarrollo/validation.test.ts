import { describe, expect, it } from 'vitest'
import { experienciaSchema, formacionSchema, LIMITES, movilidadSchema } from './validation'

const HOY = '2026-10-15'

const formacionValida = (cambios: Record<string, string> = {}) => ({
  nivel: 'Tercer nivel',
  titulo_programa: 'Ingeniería',
  institucion: 'EPN',
  estado: 'Completada',
  fecha_inicio: '',
  fecha_finalizacion: '',
  observaciones: '',
  ...cambios,
})

const experienciaValida = (cambios: Record<string, string> = {}) => ({
  organizacion: 'ACME',
  cargo: 'Analista',
  fecha_inicio: '2019-01-07',
  fecha_finalizacion: '',
  funciones: '',
  observaciones: '',
  ...cambios,
})

const camposConError = (resultado: { success: boolean; error?: { issues: { path: PropertyKey[] }[] } }) =>
  resultado.error?.issues.map((i) => String(i.path[0])) ?? []

describe('formacionSchema', () => {
  const esquema = formacionSchema(HOY)

  it('acepta una formación sin fechas y recorta espacios', () => {
    const resultado = esquema.safeParse(formacionValida({ nivel: '  Tercer nivel  ' }))
    expect(resultado.success).toBe(true)
    expect(resultado.data?.nivel).toBe('Tercer nivel')
  })

  it('exige nivel, título, institución y estado', () => {
    const resultado = esquema.safeParse(formacionValida({ nivel: '   ', titulo_programa: '', institucion: '', estado: '' }))
    expect(camposConError(resultado)).toEqual(expect.arrayContaining(['nivel', 'titulo_programa', 'institucion', 'estado']))
  })

  it('aplica los límites en el máximo y máximo más uno', () => {
    expect(esquema.safeParse(formacionValida({ nivel: 'a'.repeat(LIMITES.nivel) })).success).toBe(true)
    expect(camposConError(esquema.safeParse(formacionValida({ nivel: 'a'.repeat(LIMITES.nivel + 1) })))).toContain('nivel')
    expect(camposConError(esquema.safeParse(formacionValida({ observaciones: 'a'.repeat(LIMITES.observaciones + 1) })))).toContain('observaciones')
  })

  it('inicio igual a fin es válido; fin anterior no; inicio futuro no; fin futura sí', () => {
    expect(esquema.safeParse(formacionValida({ fecha_inicio: '2020-01-01', fecha_finalizacion: '2020-01-01' })).success).toBe(true)
    expect(camposConError(esquema.safeParse(formacionValida({ fecha_inicio: '2020-01-02', fecha_finalizacion: '2020-01-01' })))).toContain('fecha_finalizacion')
    expect(camposConError(esquema.safeParse(formacionValida({ fecha_inicio: '2026-10-16' })))).toContain('fecha_inicio')
    expect(esquema.safeParse(formacionValida({ estado: 'EnCurso', fecha_finalizacion: '2030-01-01' })).success).toBe(true)
  })

  it('rechaza fechas inexistentes', () => {
    expect(camposConError(esquema.safeParse(formacionValida({ fecha_inicio: '2020-02-30' })))).toContain('fecha_inicio')
  })
})

describe('experienciaSchema', () => {
  const esquema = experienciaSchema(HOY)

  it('exige organización, cargo e inicio', () => {
    const resultado = esquema.safeParse(experienciaValida({ organizacion: '', cargo: ' ', fecha_inicio: '' }))
    expect(camposConError(resultado)).toEqual(expect.arrayContaining(['organizacion', 'cargo', 'fecha_inicio']))
  })

  it('aplica los límites de funciones y observaciones', () => {
    expect(esquema.safeParse(experienciaValida({ funciones: 'a'.repeat(LIMITES.funciones) })).success).toBe(true)
    expect(camposConError(esquema.safeParse(experienciaValida({ funciones: 'a'.repeat(LIMITES.funciones + 1) })))).toContain('funciones')
  })

  it('rechaza inicio futuro, fin futura y fin anterior al inicio', () => {
    expect(camposConError(esquema.safeParse(experienciaValida({ fecha_inicio: '2026-10-16' })))).toContain('fecha_inicio')
    expect(camposConError(esquema.safeParse(experienciaValida({ fecha_finalizacion: '2026-10-16' })))).toContain('fecha_finalizacion')
    expect(camposConError(esquema.safeParse(experienciaValida({ fecha_finalizacion: '2018-01-01' })))).toContain('fecha_finalizacion')
  })

  it('sin fecha final es válida (no implica vigencia)', () => {
    expect(esquema.safeParse(experienciaValida()).success).toBe(true)
  })
})

describe('movilidadSchema', () => {
  it('acepta los tres estados y limita las observaciones', () => {
    for (const estado of ['Disponible', 'NoDisponible', 'NoInformada']) {
      expect(movilidadSchema.safeParse({ estado, observaciones: '' }).success).toBe(true)
    }
    expect(movilidadSchema.safeParse({ estado: 'Disponible', observaciones: 'a'.repeat(2001) }).success).toBe(false)
    expect(movilidadSchema.safeParse({ estado: 'Quizá', observaciones: '' }).success).toBe(false)
  })
})
