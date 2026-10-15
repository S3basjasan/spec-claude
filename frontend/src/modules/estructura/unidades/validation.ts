import { z } from 'zod'
import { MAX_NOMBRE, MAX_NOMBRE_TIPO, type DatosUnidad } from './api'
import { unidadesText } from './text'

const text = unidadesText.validation

/**
 * Replica las reglas de GuardarUnidadRequest del backend. El selector trabaja con texto: `''` en la unidad padre significa
 * "Ninguna (unidad raíz)", válido solo al editar la raíz. El tipo no se envía: lo calcula el servidor.
 */
export function esquemaDeUnidad(exigirPadre: boolean) {
  return z.object({
    nombre: z.string().trim().min(1, text.nameRequired).max(MAX_NOMBRE, text.nameTooLong(MAX_NOMBRE)),
    unidad_padre_id: exigirPadre ? z.string().min(1, text.parentRequired) : z.string(),
  })
}

export type UnidadFormValues = z.infer<ReturnType<typeof esquemaDeUnidad>>

/** Valores del formulario → cuerpo de la petición. */
export function datosDeFormulario(valores: UnidadFormValues): DatosUnidad {
  return {
    nombre: valores.nombre,
    unidad_padre_id: valores.unidad_padre_id === '' ? null : Number(valores.unidad_padre_id),
  }
}

/** Esquema del nombre del tipo nuevo. La unicidad sin tildes ni mayúsculas la comprueba el servidor. */
export const nombreDeTipoSchema = z.string().trim().min(1, text.nameRequired).max(MAX_NOMBRE_TIPO, text.nameTooLong(MAX_NOMBRE_TIPO))

/** Compara nombres de tipo sin distinguir mayúsculas ni tildes. */
export function claveDeNombre(nombre: string): string {
  return nombre
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{Mn}+/gu, '')
}
