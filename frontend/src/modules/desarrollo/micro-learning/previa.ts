import type { Micro, ResultadoRespuesta } from './api'

/**
 * Evalúa una respuesta en el cliente para la vista previa del Administrador. El detalle que recibe el Administrador
 * trae la opción correcta, así que no hace falta ninguna petición: la vista previa no registra progreso ni intentos.
 */
export function evaluarLocal(micro: Micro, preguntaId: number, opcionId: number): ResultadoRespuesta {
  const pregunta = micro.preguntas?.find((p) => p.id === preguntaId)
  const correcta = pregunta?.opciones.find((o) => o.es_correcta === true)
  const esCorrecta = correcta?.id === opcionId

  return {
    es_correcta: esCorrecta,
    opcion_correcta_id: correcta?.id ?? null,
    completado_ahora: false,
    intento: { estado: 'EnCurso', respondidas: [], puntaje: null, total_preguntas: micro.preguntas?.length ?? 0 },
  }
}

/** Preguntas completas (con enunciado y al menos dos opciones con texto) para mostrar en la vista previa de un borrador. */
export function preguntasCompletas(micro: Micro) {
  return (micro.preguntas ?? []).filter((p) => p.enunciado.trim() !== '' && p.opciones.filter((o) => o.texto.trim() !== '').length >= 2 && p.opciones.some((o) => o.es_correcta))
}
