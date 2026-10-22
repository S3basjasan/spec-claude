import { describe, expect, it } from 'vitest'
import type { NivelEnviado, RutaDetalle } from './api'
import { agregarNivel, cuerpoDeRuta, editarNivel, moverNivel, nivelesEnviables, quitarNivel, renumerar } from './niveles'

const niveles: NivelEnviado[] = [
  { id: 10, orden: 1, puesto_id: 1, nombre: 'Asistente', experiencia_minima_anios: null },
  { id: 11, orden: 2, puesto_id: 2, nombre: 'Analista', experiencia_minima_anios: 1 },
  { id: 12, orden: 3, puesto_id: 3, nombre: 'Senior', experiencia_minima_anios: 3 },
]

const ruta = {
  version: 4,
  nombre: 'Ruta de datos',
  tipo: 'Especialista',
  descripcion: 'Camino',
  departamento_id: 1,
  niveles: niveles.map((n) => ({ ...n, puesto: { codigo: null, nombre: null, activo: true } })),
} as unknown as RutaDetalle

describe('niveles', () => {
  it('nivelesEnviables conserva el id y descarta lo que no se guarda', () => {
    expect(nivelesEnviables(ruta)).toEqual(niveles)
  })

  it('renumerar deja órdenes consecutivos desde 1', () => {
    expect(renumerar([{ ...niveles[2], orden: 9 }, { ...niveles[0], orden: 4 }]).map((n) => n.orden)).toEqual([1, 2])
  })

  it('moverNivel sube y baja un nivel e ignora los extremos', () => {
    expect(moverNivel(niveles, 0, 1).map((n) => n.id)).toEqual([11, 10, 12])
    expect(moverNivel(niveles, 2, -1).map((n) => n.id)).toEqual([10, 12, 11])
    expect(moverNivel(niveles, 2, 1)).toBe(niveles)
    expect(moverNivel(niveles, 0, -1)).toBe(niveles)
  })

  it('agregarNivel añade el nivel más alto sin id y sin experiencia', () => {
    const r = agregarNivel(niveles, 9, 'Director')
    expect(r).toHaveLength(4)
    expect(r[3]).toEqual({ orden: 4, puesto_id: 9, nombre: 'Director', experiencia_minima_anios: null })
    expect(r[3]).not.toHaveProperty('id')
  })

  it('quitarNivel elimina por id', () => {
    expect(quitarNivel(niveles, 11).map((n) => n.id)).toEqual([10, 12])
  })

  it('editarNivel cambia solo nombre y experiencia del nivel indicado', () => {
    const r = editarNivel(niveles, 11, 'Analista senior', 2.5)
    expect(r[1]).toEqual({ id: 11, orden: 2, puesto_id: 2, nombre: 'Analista senior', experiencia_minima_anios: 2.5 })
    expect(r[0]).toBe(niveles[0])
  })

  it('cuerpoDeRuta envía la versión leída, los datos de la ruta y los niveles renumerados', () => {
    const cuerpo = cuerpoDeRuta(ruta, moverNivel(niveles, 0, 1), { nombre: 'Otro nombre' })

    expect(cuerpo.version).toBe(4)
    expect(cuerpo.nombre).toBe('Otro nombre')
    expect(cuerpo.tipo).toBe('Especialista')
    expect(cuerpo.niveles.map((n) => [n.id, n.orden])).toEqual([[11, 1], [10, 2], [12, 3]])
  })
})
