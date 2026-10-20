import { describe, expect, it } from 'vitest'
import { httpError } from '../../../test/apiErrors'
import { aDatosDeCategoria, categoriaSchema, normalizarTexto, problemaDeGuardado, valoresIniciales, type CategoriaFormValues } from './validation'
import { categoriaReconocimiento } from '../../../test/categoriasReconocimientoFixtures'

function valores(cambios: Partial<CategoriaFormValues> = {}): CategoriaFormValues {
  return {
    nombre: 'Trabajo en equipo',
    descripcion: 'Colabora de forma activa con sus compañeros.',
    competencia_id: '10',
    puntuacion_base: '100',
    icono: 'equipo',
    indicadores: [{ texto: 'Comparte información' }],
    ...cambios,
  }
}

function mensajes(entrada: CategoriaFormValues): string[] {
  const resultado = categoriaSchema.safeParse(entrada)
  return resultado.success ? [] : resultado.error.issues.map((i) => `${i.path.join('.')}: ${i.message}`)
}

describe('categoriaSchema', () => {
  it('acepta una categoría válida y los límites exactos', () => {
    expect(mensajes(valores())).toEqual([])
    expect(
      mensajes(
        valores({
          nombre: 'n'.repeat(100),
          descripcion: 'd'.repeat(500),
          puntuacion_base: '1000',
          indicadores: [{ texto: 'i'.repeat(250) }, { texto: 'Dos indicador' }, { texto: 'Tres indicador' }, { texto: 'Cuatro indicador' }, { texto: 'Cinco indicador' }],
        }),
      ),
    ).toEqual([])
  })

  it.each([
    ['nombre vacío', { nombre: '   ' }, 'nombre'],
    ['nombre corto', { nombre: 'ab' }, 'nombre'],
    ['nombre largo', { nombre: 'a'.repeat(101) }, 'nombre'],
    ['descripción corta', { descripcion: 'corta' }, 'descripcion'],
    ['descripción larga', { descripcion: 'a'.repeat(501) }, 'descripcion'],
    ['sin competencia', { competencia_id: '' }, 'competencia_id'],
    ['puntuación vacía', { puntuacion_base: '' }, 'puntuacion_base'],
    ['puntuación decimal', { puntuacion_base: '10.5' }, 'puntuacion_base'],
    ['puntuación cero', { puntuacion_base: '0' }, 'puntuacion_base'],
    ['puntuación 1001', { puntuacion_base: '1001' }, 'puntuacion_base'],
    ['sin ícono', { icono: '' }, 'icono'],
    ['ícono fuera de la lista', { icono: 'cohete' }, 'icono'],
    ['sin indicadores', { indicadores: [] }, 'indicadores'],
    ['seis indicadores', { indicadores: Array.from({ length: 6 }, (_, i) => ({ texto: `Indicador número ${i}` })) }, 'indicadores'],
    ['indicador corto', { indicadores: [{ texto: 'cort' }] }, 'indicadores.0.texto'],
    ['indicador largo', { indicadores: [{ texto: 'a'.repeat(251) }] }, 'indicadores.0.texto'],
    ['HTML en el nombre', { nombre: '<b>Equipo</b>' }, 'nombre'],
    ['HTML en un indicador', { indicadores: [{ texto: 'Texto <img src=x>' }] }, 'indicadores.0.texto'],
  ] as const)('rechaza: %s', (_titulo, cambios, campo) => {
    expect(mensajes(valores(cambios as Partial<CategoriaFormValues>)).some((m) => m.startsWith(campo))).toBe(true)
  })

  it('rechaza indicadores repetidos sin distinguir mayúsculas ni tildes', () => {
    const errores = mensajes(valores({ indicadores: [{ texto: 'Colaboración activa' }, { texto: '  COLABORACION   activa ' }] }))

    expect(errores).toEqual(['indicadores.1.texto: Los indicadores no pueden repetirse.'])
  })
})

describe('normalizarTexto', () => {
  it('quita tildes, mayúsculas y espacios repetidos', () => {
    expect(normalizarTexto('  Orientación   al CLIENTE ')).toBe('orientacion al cliente')
  })
})

describe('conversión de valores', () => {
  it('precarga la categoría y arma los datos a enviar', () => {
    const inicial = valoresIniciales(categoriaReconocimiento())

    expect(inicial).toEqual({
      nombre: 'Trabajo en equipo',
      descripcion: 'Colabora de forma activa con sus compañeros.',
      competencia_id: '10',
      puntuacion_base: '100',
      icono: 'equipo',
      indicadores: [{ texto: 'Comparte información con su equipo' }, { texto: 'Ayuda a quien lo necesita' }],
    })
    expect(aDatosDeCategoria({ ...inicial, nombre: '  Nuevo nombre ' })).toMatchObject({
      nombre: 'Nuevo nombre',
      competencia_id: 10,
      puntuacion_base: 100,
      indicadores: ['Comparte información con su equipo', 'Ayuda a quien lo necesita'],
    })
    expect(valoresIniciales().indicadores).toEqual([{ texto: '' }])
  })
})

describe('problemaDeGuardado', () => {
  it('asigna los 422 a su campo y a su indicador', () => {
    const problema = problemaDeGuardado(
      httpError(422, { errors: { nombre: ['Ya existe una categoría con ese nombre.'], 'indicadores.1': ['Los indicadores no pueden repetirse.'], competencia_id: ['Inactiva'] } }),
    )

    expect(problema.campos.nombre).toBe('Ya existe una categoría con ese nombre.')
    expect(problema.campos.competencia_id).toBe('Inactiva')
    expect(problema.indicadores[1]).toBe('Los indicadores no pueden repetirse.')
    expect(problema.aviso).toBeNull()
  })

  it('distingue conflicto, inexistente y error inesperado', () => {
    expect(problemaDeGuardado(httpError(409)).aviso).toBe('conflicto')
    expect(problemaDeGuardado(httpError(404)).aviso).toBe('noExiste')
    expect(problemaDeGuardado(httpError(500)).aviso).toBe('inesperado')
    expect(problemaDeGuardado(httpError(422, { errors: {} })).aviso).toBe('inesperado')
  })
})
