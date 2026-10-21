import type { Escala } from './api'

/** Nombres que la escala Categorías trae por defecto cuando la competencia no define los suyos. */
export function etiquetasDeEscala(escala: Escala, categorias: string[] | null): string[] {
  if (escala.nombre === 'Categorías' && categorias !== null && categorias.length === escala.valores.length) {
    return categorias
  }
  return escala.valores.map((valor) => valor.etiqueta)
}

/** Nombres por defecto de la escala Categorías, para prellenar los campos del formulario. */
export function categoriasPorDefecto(escalas: Escala[]): string[] {
  const escala = escalas.find((e) => e.nombre === 'Categorías')
  return escala ? escala.valores.map((valor) => valor.valor) : ['Excelente', 'Muy buena', 'Buena', 'Normal']
}
