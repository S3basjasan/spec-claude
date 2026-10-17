import { describe, expect, it } from 'vitest'
import { micro, quiz } from '../../../test/microFixtures'
import { evaluarLocal, preguntasCompletas } from './previa'

describe('previa', () => {
  it('evalúa en el cliente con la opción correcta del detalle del Administrador', () => {
    const q = quiz()

    expect(evaluarLocal(q, 10, 101)).toMatchObject({ es_correcta: true, opcion_correcta_id: 101 })
    expect(evaluarLocal(q, 10, 102)).toMatchObject({ es_correcta: false, opcion_correcta_id: 101 })
    expect(evaluarLocal(q, 999, 1)).toMatchObject({ es_correcta: false, opcion_correcta_id: null })
  })

  it('solo muestra las preguntas completas de un borrador', () => {
    const q = quiz()
    q.preguntas = [
      ...(q.preguntas ?? []),
      { id: 3, enunciado: '', orden: 3, opciones: [{ id: 1, texto: 'a', orden: 1, es_correcta: true }, { id: 2, texto: 'b', orden: 2, es_correcta: false }] },
      { id: 4, enunciado: 'Sin correcta', orden: 4, opciones: [{ id: 1, texto: 'a', orden: 1, es_correcta: false }, { id: 2, texto: 'b', orden: 2, es_correcta: false }] },
    ]

    expect(preguntasCompletas(q).map((p) => p.id)).toEqual([10, 11])
    expect(preguntasCompletas(micro())).toEqual([])
  })
})
