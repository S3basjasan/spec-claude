import type { Grado, Resultado } from './api'
import { adecuacionText } from './text'

/** Largo de las barras (solo presentación): A llena la barra y D un cuarto. El cálculo siempre lo hace el servidor. */
const LARGO_DE_GRADO: Record<Grado, number> = { A: 1, B: 0.75, C: 0.5, D: 0.25 }

export interface FraccionesDeBarras {
  actual: number | null
  persona: number | null
  futuro: number | null
}

export function fraccionesDe(r: Resultado): FraccionesDeBarras {
  if (r.dimension !== 'Experiencia') {
    return {
      actual: r.requerido_actual === null ? null : LARGO_DE_GRADO[r.requerido_actual],
      persona: r.valor === null ? null : LARGO_DE_GRADO[r.valor],
      futuro: r.requerido_objetivo === null ? null : LARGO_DE_GRADO[r.requerido_objetivo],
    }
  }

  const tope = Math.max(r.requerido_actual_anios ?? 0, r.requerido_objetivo_anios ?? 0, r.valor_anios ?? 0, 1)

  return {
    actual: r.requerido_actual_anios === null ? null : r.requerido_actual_anios / tope,
    persona: r.valor_anios === null ? null : r.valor_anios / tope,
    futuro: r.requerido_objetivo_anios === null ? null : r.requerido_objetivo_anios / tope,
  }
}

/** Texto de un requisito: «Nivel actual: C · Requerido: B · Diferencia: −1» (o su equivalente en años). */
export function resumenDe(r: Resultado): string {
  return r.dimension === 'Experiencia'
    ? adecuacionText.panel.resumenAnios(r.valor_anios, r.requerido_objetivo_anios, r.diferencia)
    : adecuacionText.panel.resumenGrado(r.valor, r.requerido_objetivo, r.diferencia)
}

/** Texto alternativo de las tres barras. */
export function etiquetaDeBarras(r: Resultado): string {
  const t = adecuacionText.panel
  const grado = (valor: Grado | null) => valor ?? t.noExigido
  const anios = (valor: number | null) => (valor === null ? t.noExigido : `${valor} años`)
  const nombre = r.nombre ?? t.experiencia

  return r.dimension === 'Experiencia'
    ? t.aria(nombre, anios(r.requerido_actual_anios), r.valor_anios === null ? t.sinValor : anios(r.valor_anios), anios(r.requerido_objetivo_anios))
    : t.aria(nombre, grado(r.requerido_actual), r.valor === null ? t.sinValor : r.valor, grado(r.requerido_objetivo))
}

/** Texto alternativo de las dos barras de «Mi adecuación» (tu nivel actual y el requisito del puesto). */
export function etiquetaDeBarrasPropias(r: Resultado): string {
  const t = adecuacionText.panel
  const nombre = r.nombre ?? t.experiencia
  const persona = r.dimension === 'Experiencia' ? (r.valor_anios === null ? t.sinValor : `${r.valor_anios} años`) : (r.valor ?? t.sinValor)
  const futuro = r.dimension === 'Experiencia' ? (r.requerido_objetivo_anios === null ? t.noExigido : `${r.requerido_objetivo_anios} años`) : (r.requerido_objetivo ?? t.noExigido)

  return `${nombre}: tu nivel actual ${persona}, requisito del puesto ${futuro}`
}
