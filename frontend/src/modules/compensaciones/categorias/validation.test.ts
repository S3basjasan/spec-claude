import { describe, expect, it } from 'vitest'
import { httpError } from '../../../test/apiErrors'
import { aDatosDeCategoria, categoriaSchema, crearCategoriaSchema, normalizarTexto, problemaDeGuardado, valoresIniciales, type CategoriaFormValues } from './validation'
import { categoriaReconocimiento } from '../../../test/categoriasReconocimientoFixtures'

function valores(cambios: Partial<CategoriaFormValues> = {}): CategoriaFormValues {
  return {
    nombre: 'Trabajo en equipo',
    descripcion: 'Colabora de forma activa con sus compañeros.',
    competencia_id: '10',
    puntuacion_base: '100',
    icono: 'equipo',
    indicadores: [{ texto: 'Comparte información' }],
    unidades_ids: [1],
    todas: false,
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
    ['sin unidades', { unidades_ids: [], todas: false }, 'unidades_ids'],
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
      unidades_ids: [1],
      todas: false,
    })
    expect(aDatosDeCategoria({ ...inicial, nombre: '  Nuevo nombre ' })).toMatchObject({
      nombre: 'Nuevo nombre',
      competencia_id: 10,
      puntuacion_base: 100,
      indicadores: ['Comparte información con su equipo', 'Ayuda a quien lo necesita'],
    })
    expect(valoresIniciales().indicadores).toEqual([{ texto: '' }])
  })

  it('acepta «Todas las unidades» sin ids y la envía como bandera; si no, envía la lista', () => {
    expect(mensajes(valores({ unidades_ids: [], todas: true }))).toEqual([])
    expect(aDatosDeCategoria(valores({ unidades_ids: [], todas: true }))).toMatchObject({ todas_las_unidades: true })
    expect(aDatosDeCategoria(valores({ unidades_ids: [2, 3], todas: true }))).not.toHaveProperty('unidades_ids')
    expect(aDatosDeCategoria(valores({ unidades_ids: [2, 3] }))).toMatchObject({ unidades_ids: [2, 3] })
    expect(aDatosDeCategoria(valores({ unidades_ids: [2, 3] }))).not.toHaveProperty('todas_las_unidades')
  })

  it('precarga solo las unidades activas y respeta «aplica a todas»', () => {
    const categoria = categoriaReconocimiento({
      unidades: [
        { id: 1, nombre: 'Operaciones', activa: true },
        { id: 4, nombre: 'Tecnología', activa: false },
      ],
      aplica_a_todas: false,
    })

    expect(valoresIniciales(categoria)).toMatchObject({ unidades_ids: [1], todas: false })
    expect(valoresIniciales(categoriaReconocimiento({ aplica_a_todas: true }))).toMatchObject({ todas: true })
    expect(valoresIniciales(categoriaReconocimiento({ unidades: undefined }))).toMatchObject({ unidades_ids: [], todas: false })
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

  it('asigna el 422 de unidades al campo de unidades', () => {
    const problema = problemaDeGuardado(httpError(422, { errors: { unidades_ids: ['Elige al menos una unidad.'] } }))

    expect(problema.campos.unidades_ids).toBe('Elige al menos una unidad.')
    expect(problema.aviso).toBeNull()
    expect(problemaDeGuardado(httpError(422, { errors: { 'unidades_ids.0': ['La unidad seleccionada ya no está activa.'] } })).campos.unidades_ids).toBe(
      'La unidad seleccionada ya no está activa.',
    )
  })

  it('distingue conflicto, inexistente y error inesperado', () => {
    expect(problemaDeGuardado(httpError(409)).aviso).toBe('conflicto')
    expect(problemaDeGuardado(httpError(404)).aviso).toBe('noExiste')
    expect(problemaDeGuardado(httpError(500)).aviso).toBe('inesperado')
    expect(problemaDeGuardado(httpError(422, { errors: {} })).aviso).toBe('inesperado')
  })
})

describe('crearCategoriaSchema · máximo permitido', () => {
  const mensajesConMaximo = (maximo: number | undefined, puntuacion: string) => {
    const resultado = crearCategoriaSchema(maximo).safeParse(valores({ puntuacion_base: puntuacion }))
    return resultado.success ? [] : resultado.error.issues.filter((i) => i.path[0] === 'puntuacion_base').map((i) => i.message)
  }

  it('acepta de 1 al máximo y rechaza 0, el máximo más uno, decimales y vacío con el mensaje del máximo', () => {
    expect(mensajesConMaximo(100, '1')).toEqual([])
    expect(mensajesConMaximo(100, '100')).toEqual([])
    expect(mensajesConMaximo(100, '0')).toEqual(['La puntuación base debe estar entre 1 y 100.'])
    expect(mensajesConMaximo(100, '101')).toEqual(['La puntuación base debe estar entre 1 y 100.'])
    expect(mensajesConMaximo(100, '1.5')).toEqual(['La puntuación base debe ser un número entero.'])
    expect(mensajesConMaximo(100, '')[0]).toBe('Este campo es obligatorio.')
  })

  it('con un máximo menor por cambio programado usa ese máximo', () => {
    expect(mensajesConMaximo(60, '60')).toEqual([])
    expect(mensajesConMaximo(60, '61')).toEqual(['La puntuación base debe estar entre 1 y 60.'])
  })

  it('sin máximo conocido rige el límite exterior de 1000', () => {
    expect(mensajesConMaximo(undefined, '1000')).toEqual([])
    expect(mensajesConMaximo(undefined, '1001')).toEqual(['La puntuación base debe estar entre 1 y 1000.'])
  })
})
