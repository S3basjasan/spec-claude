import { describe, expect, it } from 'vitest'
import {
  claveDeTexto,
  competenciaCompletaSchema,
  rutaDeCampo,
  seccionComportamientosSchema,
  seccionPreguntasSchema,
  type CompetenciaCompletaValues,
} from './validation'

function valoresValidos(cambios: Partial<CompetenciaCompletaValues> = {}): CompetenciaCompletaValues {
  return {
    nombre: 'Liderazgo',
    descripcion: 'Guiar a otros.',
    tipo: 'Cardinal',
    tipoGradoId: 1,
    escala: 'Letras',
    categorias: ['Excelente', 'Muy buena', 'Buena', 'Normal'],
    grados: ['a', 'b', 'c', 'd'],
    comportamientos: [
      ['a1', 'a2', 'a3', 'a4'],
      ['b1', 'b2', 'b3', 'b4'],
      ['c1', 'c2', 'c3', 'c4'],
      ['d1', 'd2', 'd3', 'd4'],
    ],
    preguntas: ['p1', 'p2', 'p3', 'p4'],
    ...cambios,
  }
}

/** Mensajes de error de la validación, por ruta (`grados.1`, `categorias.0`…). */
function errores(valores: CompetenciaCompletaValues): Record<string, string> {
  const resultado = competenciaCompletaSchema.safeParse(valores)
  if (resultado.success) return {}
  return Object.fromEntries(resultado.error.issues.map((problema) => [problema.path.join('.'), problema.message]))
}

describe('claveDeTexto', () => {
  it('ignora tildes, mayúsculas y espacios laterales', () => {
    expect(claveDeTexto('  MAESTRÍA ')).toBe('maestria')
    expect(claveDeTexto('Lidérazgo')).toBe(claveDeTexto('liderazgo'))
  })
})

describe('competenciaCompletaSchema', () => {
  it('acepta una competencia completa', () => {
    expect(errores(valoresValidos())).toEqual({})
  })

  it('valida los límites del nombre (50), la descripción (500) y los grados (500)', () => {
    expect(errores(valoresValidos({ nombre: 'n'.repeat(50) }))).toEqual({})
    expect(errores(valoresValidos({ nombre: 'n'.repeat(51) })).nombre).toBe('El nombre no puede superar los 50 caracteres.')
    expect(errores(valoresValidos({ descripcion: 'd'.repeat(501) })).descripcion).toBe('La descripción no puede superar los 500 caracteres.')
    expect(errores(valoresValidos({ grados: ['a', 'b', 'c', 'g'.repeat(501)] }))['grados.3']).toBe('La descripción de un grado no puede superar los 500 caracteres.')
  })

  it('valida los límites del comportamiento (300) y de la pregunta (500)', () => {
    const comportamientos = valoresValidos().comportamientos
    comportamientos[0][0] = 'c'.repeat(300)
    expect(errores(valoresValidos({ comportamientos }))).toEqual({})
    comportamientos[0][0] = 'c'.repeat(301)
    expect(errores(valoresValidos({ comportamientos }))['comportamientos.0.0']).toBe('Un comportamiento no puede superar los 300 caracteres.')
    expect(errores(valoresValidos({ preguntas: ['p', 'p', 'p', 'p'.repeat(501)] }))['preguntas.3']).toBe('Una pregunta no puede superar los 500 caracteres.')
  })

  it('un texto con solo espacios cuenta como vacío', () => {
    const comportamientos = valoresValidos().comportamientos
    comportamientos[1][2] = '   '
    const resultado = errores(valoresValidos({ nombre: '   ', grados: ['a', '  ', 'c', 'd'], comportamientos, preguntas: ['p1', 'p2', ' ', 'p4'] }))

    expect(resultado.nombre).toBe('Ingresa el nombre.')
    expect(resultado['grados.1']).toBe('Completa los cuatro grados.')
    expect(resultado['comportamientos.1.2']).toBe('Completa los cuatro comportamientos de cada grado.')
    expect(resultado['preguntas.2']).toBe('Completa las cuatro preguntas.')
  })

  it('exige el tipo y la escala', () => {
    const resultado = errores(valoresValidos({ tipo: '' as CompetenciaCompletaValues['tipo'] }))
    expect(resultado.tipo).toBe('Selecciona el tipo.')
  })

  it('no valida las categorías si la escala no es Categorías', () => {
    expect(errores(valoresValidos({ categorias: ['', '', '', ''] }))).toEqual({})
  })

  describe('con la escala Categorías', () => {
    const conCategorias = (categorias: string[]) => valoresValidos({ escala: 'Categorías', tipoGradoId: 3, categorias })

    it('acepta cuatro nombres distintos, incluido uno de exactamente 20 caracteres', () => {
      expect(errores(conCategorias(['Maestría', 'Avanzado', 'Competente', 'Inicial']))).toEqual({})
      expect(errores(conCategorias(['x'.repeat(20), 'B', 'C', 'D']))).toEqual({})
    })

    it('rechaza un nombre vacío o de más de 20 caracteres', () => {
      expect(errores(conCategorias(['A', 'B', 'C', '  ']))['categorias.3']).toBe('Escribe las cuatro categorías, sin repetir nombres.')
      expect(errores(conCategorias(['x'.repeat(21), 'B', 'C', 'D']))['categorias.0']).toBe('Un nombre de categoría no puede superar los 20 caracteres.')
    })

    it('rechaza nombres repetidos sin distinguir mayúsculas, tildes ni espacios', () => {
      const resultado = errores(conCategorias(['Buena', ' búena ', 'Alta', 'Baja']))
      expect(resultado['categorias.0']).toBe('Escribe las cuatro categorías, sin repetir nombres.')
      expect(resultado['categorias.1']).toBe('Escribe las cuatro categorías, sin repetir nombres.')
      expect(errores(conCategorias(['Maestría', 'Maestria', 'Alta', 'Baja']))['categorias.1']).toBeDefined()
    })
  })
})

describe('esquemas de sección', () => {
  it('exigen los 16 comportamientos y las 4 preguntas', () => {
    expect(seccionComportamientosSchema.safeParse({ comportamientos: valoresValidos().comportamientos }).success).toBe(true)
    expect(seccionComportamientosSchema.safeParse({ comportamientos: [['a'], ['b'], ['c'], ['d']] }).success).toBe(false)
    expect(seccionPreguntasSchema.safeParse({ preguntas: ['a', 'b', 'c', 'd'] }).success).toBe(true)
    expect(seccionPreguntasSchema.safeParse({ preguntas: ['a', 'b', 'c'] }).success).toBe(false)
  })
})

describe('rutaDeCampo', () => {
  it('traduce los campos del backend a las rutas del formulario', () => {
    expect(rutaDeCampo('tipo_grado_id')).toBe('tipoGradoId')
    expect(rutaDeCampo('grados.2')).toBe('grados.2')
    expect(rutaDeCampo('comportamientos.1.3')).toBe('comportamientos.1.3')
    expect(rutaDeCampo('comportamientos.1')).toBe('comportamientos.1.0')
    expect(rutaDeCampo('categorias')).toBe('categorias.0')
    expect(rutaDeCampo('preguntas')).toBe('preguntas.0')
  })
})
