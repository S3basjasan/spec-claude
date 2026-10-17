import { describe, expect, it } from 'vitest'
import type { PreguntaBorrador } from './api'
import { duracionDe, preguntaNueva, validarMicro, type BorradorMicro } from './validation'

function borrador(cambios: Partial<BorradorMicro> = {}): BorradorMicro {
  return {
    titulo: 'Introducción a SQL',
    tipo: 'Video',
    duracion: '12',
    url: 'https://contenido.test/sql',
    miniatura_url: '',
    competencia_ids: [3],
    preguntas: [],
    ...cambios,
  }
}

function pregunta(opciones = 3, correctas = 1, enunciado = '¿Qué es?'): PreguntaBorrador {
  return {
    enunciado,
    opciones: Array.from({ length: opciones }, (_, i) => ({ texto: `Opción ${i + 1}`, es_correcta: i < correctas })),
  }
}

describe('validarMicro', () => {
  it('acepta un video completo y normaliza los vacíos a null', () => {
    const resultado = validarMicro(borrador(), true)

    expect(resultado).toEqual({
      datos: { titulo: 'Introducción a SQL', tipo: 'Video', duracion_minutos: 12, url: 'https://contenido.test/sql', miniatura_url: null, competencia_ids: [3], preguntas: null },
    })
  })

  it('guardar como borrador solo exige título, tipo y duración', () => {
    const resultado = validarMicro(borrador({ url: '', competencia_ids: [] }), false)

    expect('datos' in resultado && resultado.datos.url).toBeNull()
  })

  it('rechaza título vacío o largo y duración inválida', () => {
    expect(validarMicro(borrador({ titulo: '   ' }), false)).toHaveProperty('errores.titulo')
    expect(validarMicro(borrador({ titulo: 'a'.repeat(121) }), false)).toHaveProperty('errores.titulo')
    for (const duracion of ['', '0', '-3', '2.5', 'abc']) {
      expect(validarMicro(borrador({ duracion }), false)).toHaveProperty('errores.duracion_minutos')
    }
  })

  it('valida el formato de la URL y de la miniatura', () => {
    expect(validarMicro(borrador({ url: 'ftp://x.test' }), false)).toHaveProperty('errores.url')
    expect(validarMicro(borrador({ url: 'no es url' }), false)).toHaveProperty('errores.url')
    expect(validarMicro(borrador({ miniatura_url: 'nada' }), false)).toHaveProperty('errores.miniatura_url')
    expect(validarMicro(borrador({ miniatura_url: 'https://img.test/a.png' }), false)).toHaveProperty('datos')
  })

  it('publicar exige URL (salvo quiz), competencias y, en quiz, preguntas', () => {
    expect(validarMicro(borrador({ url: '' }), true)).toHaveProperty('errores.url')
    expect(validarMicro(borrador({ competencia_ids: [] }), true)).toHaveProperty('errores.competencia_ids')
    expect(validarMicro(borrador({ tipo: 'Quiz', url: '', preguntas: [] }), true)).toHaveProperty('errores.preguntas')
    expect(validarMicro(borrador({ tipo: 'Quiz', url: 'https://x.test', preguntas: [pregunta()] }), true)).toHaveProperty('datos.url', null)
  })

  it('un quiz admite de 1 a 20 preguntas con 2 a 5 opciones y una correcta', () => {
    const quiz = (preguntas: PreguntaBorrador[]) => validarMicro(borrador({ tipo: 'Quiz', url: '', preguntas }), false)

    expect(quiz([pregunta(2), pregunta(5)])).toHaveProperty('datos')
    expect(quiz(Array.from({ length: 20 }, () => pregunta()))).toHaveProperty('datos')
    expect(quiz(Array.from({ length: 21 }, () => pregunta()))).toHaveProperty('errores.preguntas')
    expect(quiz([pregunta(1)])).toHaveProperty(['errores', 'preguntas.0.opciones'])
    expect(quiz([pregunta(6)])).toHaveProperty(['errores', 'preguntas.0.opciones'])
    expect(quiz([pregunta(3, 0)])).toHaveProperty(['errores', 'preguntas.0.correcta'])
    expect(quiz([pregunta(3, 2)])).toHaveProperty(['errores', 'preguntas.0.correcta'])
    expect(quiz([pregunta(3, 1, '  ')])).toHaveProperty(['errores', 'preguntas.0.enunciado'])
  })

  it('una opción sin texto se señala en su posición', () => {
    const p = pregunta(3)
    p.opciones[1].texto = ''

    expect(validarMicro(borrador({ tipo: 'Quiz', url: '', preguntas: [p] }), false)).toHaveProperty(['errores', 'preguntas.0.opciones.1.texto'])
  })

  it('las preguntas se ignoran si el tipo no es quiz', () => {
    const resultado = validarMicro(borrador({ preguntas: [pregunta(1)] }), false)

    expect(resultado).toHaveProperty('datos.preguntas', null)
  })
})

describe('helpers', () => {
  it('duracionDe solo acepta enteros', () => {
    expect(duracionDe(' 12 ')).toBe(12)
    expect(Number.isNaN(duracionDe('1.5'))).toBe(true)
    expect(Number.isNaN(duracionDe(''))).toBe(true)
  })

  it('una pregunta nueva tiene dos opciones y la primera correcta', () => {
    const p = preguntaNueva()

    expect(p.opciones).toHaveLength(2)
    expect(p.opciones.map((o) => o.es_correcta)).toEqual([true, false])
  })
})
