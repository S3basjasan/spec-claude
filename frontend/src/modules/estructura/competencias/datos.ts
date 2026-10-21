import type { DatosCompetencia } from './api'
import type { CompetenciaCompletaValues } from './validation'

/** Convierte lo escrito en el cuerpo de la creación: textos recortados y categorías solo con esa escala. */
export function aDatosDeCreacion(valores: CompetenciaCompletaValues): DatosCompetencia {
  const conCategorias = valores.escala === 'Categorías'
  return {
    nombre: valores.nombre.trim(),
    descripcion: valores.descripcion.trim(),
    tipo: valores.tipo,
    tipo_grado_id: valores.tipoGradoId,
    ...(conCategorias ? { categorias: valores.categorias.map((nombre) => nombre.trim()) } : {}),
    grados: valores.grados.map((texto) => texto.trim()),
    comportamientos: valores.comportamientos.map((grado) => grado.map((texto) => texto.trim())),
    preguntas: valores.preguntas.map((texto) => texto.trim()),
  }
}
