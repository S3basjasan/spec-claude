import type { Competencia, CompetenciaDetalle, Escala, PaginaCompetencias } from '../modules/estructura/competencias/api'

/** Elemento del listado. */
export function competencia(cambios: Partial<Competencia> = {}): Competencia {
  return {
    id: 1,
    codigo: 'COMP-0001',
    nombre: 'Liderazgo',
    tipo: 'Cardinal',
    estado: 'Activo',
    completa: true,
    ...cambios,
  }
}

/** Las tres escalas del catálogo: Letras (activa), Números y Categorías. */
export function escalas(): Escala[] {
  return [
    {
      id: 1,
      nombre: 'Letras',
      activo: true,
      valores: [
        { orden: 1, valor: 'A', etiqueta: 'Grado A' },
        { orden: 2, valor: 'B', etiqueta: 'Grado B' },
        { orden: 3, valor: 'C', etiqueta: 'Grado C' },
        { orden: 4, valor: 'D', etiqueta: 'Grado D' },
      ],
    },
    {
      id: 2,
      nombre: 'Números',
      activo: false,
      valores: [
        { orden: 1, valor: 'Nivel 1', etiqueta: 'Nivel 1' },
        { orden: 2, valor: 'Nivel 2', etiqueta: 'Nivel 2' },
        { orden: 3, valor: 'Nivel 3', etiqueta: 'Nivel 3' },
        { orden: 4, valor: 'Nivel 4', etiqueta: 'Nivel 4' },
      ],
    },
    {
      id: 3,
      nombre: 'Categorías',
      activo: false,
      valores: [
        { orden: 1, valor: 'Excelente', etiqueta: 'Excelente' },
        { orden: 2, valor: 'Muy buena', etiqueta: 'Muy buena' },
        { orden: 3, valor: 'Buena', etiqueta: 'Buena' },
        { orden: 4, valor: 'Normal', etiqueta: 'Normal' },
      ],
    },
  ]
}

/** Detalle completo en la escala Letras: 4 grados con 4 comportamientos cada uno y 4 preguntas. */
export function detalle(cambios: Partial<CompetenciaDetalle> = {}): CompetenciaDetalle {
  const letras = ['A', 'B', 'C', 'D']
  return {
    id: 1,
    codigo: 'COMP-0001',
    nombre: 'Liderazgo',
    descripcion: 'Guiar e inspirar a otras personas.',
    tipo: 'Cardinal',
    estado: 'Activo',
    version: 1,
    completa: true,
    faltantes: [],
    escala: { tipo_grado_id: 1, nombre: 'Letras' },
    categorias: null,
    grados: letras.map((letra, indice) => ({
      id: indice + 1,
      orden: indice + 1,
      etiqueta: `Grado ${letra}`,
      descripcion: `Descripción del grado ${letra}.`,
      comportamientos: [1, 2, 3, 4].map((numero) => `Comportamiento ${numero} del grado ${letra}.`),
    })),
    preguntas: ['Pregunta uno.', 'Pregunta dos.', 'Pregunta tres.', 'Pregunta cuatro.'],
    ...cambios,
  }
}

export function pagina(items: Competencia[], meta: Partial<PaginaCompetencias['meta']> = {}): PaginaCompetencias {
  return {
    data: items,
    meta: {
      current_page: 1,
      per_page: 12,
      total: items.length,
      last_page: 1,
      from: items.length ? 1 : null,
      to: items.length || null,
      ...meta,
    },
  }
}
