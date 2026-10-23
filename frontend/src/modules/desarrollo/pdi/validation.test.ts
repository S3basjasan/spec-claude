import { describe, expect, it } from 'vitest'
import { compromisoVacio, datosVacios, type DatosAsistente } from './asistente'
import { pasoDelCampo, validarPaso1, validarPaso2, validarPaso3, validarTodo } from './validation'

function datos(cambios: Partial<DatosAsistente> = {}): DatosAsistente {
  return datosVacios(1, { colaborador_id: 10, objetivo: 'Objetivo', fecha_inicio: '2026-07-01', fecha_fin: '2026-12-31', ...cambios })
}

function conElemento(cambios: Partial<ReturnType<typeof compromisoVacio>> = {}) {
  const base = compromisoVacio('2026-07-01', '2026-11-30')
  base.elemento_id = 1
  base.acciones[0].descripcion = 'Curso'
  base.acciones[0].fecha_inicio = '2026-07-15'
  base.acciones[0].fecha_fin = '2026-09-30'

  return { ...base, ...cambios }
}

describe('validarPaso1', () => {
  it('exige colaborador y objetivo', () => {
    expect(Object.keys(validarPaso1(datosVacios(1)))).toEqual(['colaborador_id', 'objetivo'])
    expect(validarPaso1(datos())).toEqual({})
  })

  it('exige el puesto objetivo en Carrera y Talento y no en Mejora', () => {
    expect(validarPaso1(datos({ tipo: 'Carrera' })).puesto_objetivo_id).toBeDefined()
    expect(validarPaso1(datos({ tipo: 'Talento' })).puesto_objetivo_id).toBeDefined()
    expect(validarPaso1(datos({ tipo: 'Carrera', puesto_objetivo_id: 2 }))).toEqual({})
    expect(validarPaso1(datos({ tipo: 'Mejora' })).puesto_objetivo_id).toBeUndefined()
  })
})

describe('validarPaso2', () => {
  it('acepta un compromiso de competencia completo', () => {
    expect(validarPaso2(datos({ compromisos: [conElemento()] }))).toEqual({})
  })

  it('exige elemento en competencias y descripción en experiencia y meta', () => {
    expect(validarPaso2(datos({ compromisos: [conElemento({ elemento_id: null })] }))['compromisos.0.elemento_id']).toBeDefined()
    expect(validarPaso2(datos({ compromisos: [conElemento({ dimension: 'Meta', descripcion: ' ' })] }))['compromisos.0.descripcion']).toBeDefined()
    expect(validarPaso2(datos({ compromisos: [conElemento({ dimension: 'Experiencia', descripcion: 'Liderar un proyecto' })] }))).toEqual({})
  })

  it('los niveles van en pareja y son distintos', () => {
    expect(validarPaso2(datos({ compromisos: [conElemento({ nivel_actual: 'C' })] }))['compromisos.0.nivel_objetivo']).toBeDefined()
    expect(validarPaso2(datos({ compromisos: [conElemento({ nivel_actual: 'C', nivel_objetivo: 'C' })] }))['compromisos.0.nivel_objetivo']).toBeDefined()
    expect(validarPaso2(datos({ compromisos: [conElemento({ nivel_actual: 'C', nivel_objetivo: 'B' })] }))).toEqual({})
  })

  it('las fechas de compromisos y acciones caen dentro del periodo y en orden', () => {
    expect(validarPaso2(datos({ compromisos: [conElemento({ fecha_inicio: '2026-06-01' })] }))['compromisos.0.fecha_inicio']).toBeDefined()
    expect(validarPaso2(datos({ compromisos: [conElemento({ fecha_inicio: '2026-10-01', fecha_fin: '2026-09-01' })] }))['compromisos.0.fecha_fin']).toBeDefined()
    const accionFuera = conElemento()
    accionFuera.acciones[0].fecha_fin = '2027-03-01'
    expect(validarPaso2(datos({ compromisos: [accionFuera] }))['compromisos.0.acciones.0.fecha_inicio']).toBeDefined()
  })

  it('no repite el mismo elemento y exige descripción en cada acción', () => {
    expect(validarPaso2(datos({ compromisos: [conElemento(), conElemento()] }))['compromisos.1.elemento_id']).toBeDefined()
    const sinDescripcion = conElemento()
    sinDescripcion.acciones[0].descripcion = ''
    expect(validarPaso2(datos({ compromisos: [sinDescripcion] }))['compromisos.0.acciones.0.descripcion']).toBeDefined()
  })
})

describe('validarPaso3', () => {
  it('exige responsable y periodo, y los hitos dentro del periodo', () => {
    expect(Object.keys(validarPaso3(datos({ responsable_id: null, fecha_inicio: '', fecha_fin: '' })))).toEqual(['responsable_id', 'fecha_inicio', 'fecha_fin'])
    expect(validarPaso3(datos({ fecha_fin: '2026-06-01' })).fecha_fin).toBeDefined()
    expect(validarPaso3(datos({ hitos: [{ clave: 'a', fecha: '2027-01-01', descripcion: 'x' }] }))['hitos.0.fecha']).toBeDefined()
    expect(validarPaso3(datos({ hitos: [{ clave: 'a', fecha: '2026-09-01', descripcion: ' ' }] }))['hitos.0.descripcion']).toBeDefined()
    expect(validarPaso3(datos({ hitos: [{ clave: 'a', fecha: '2026-09-01', descripcion: 'Revisión' }] }))).toEqual({})
  })
})

describe('validarTodo y pasoDelCampo', () => {
  it('reúne los errores de los tres pasos y lleva cada campo a su paso', () => {
    const errores = validarTodo(datosVacios(null))

    expect(Object.keys(errores)).toEqual(expect.arrayContaining(['colaborador_id', 'objetivo', 'responsable_id', 'fecha_inicio']))
    expect(pasoDelCampo('objetivo')).toBe(0)
    expect(pasoDelCampo('compromisos.0.acciones.0.descripcion')).toBe(1)
    expect(pasoDelCampo('hitos.0.fecha')).toBe(2)
    expect(pasoDelCampo('responsable_id')).toBe(2)
  })
})
