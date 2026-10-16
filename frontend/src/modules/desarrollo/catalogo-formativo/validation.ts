import { z } from 'zod'
import { MAX_NOMBRE_AREA } from './api'
import { catalogoText } from './text'

/** Nombre de un área formativa: obligatorio (sin contar espacios) y de hasta 60 caracteres. */
export const nombreAreaSchema = z
  .string()
  .transform((valor) => valor.trim().replace(/\s+/g, ' '))
  .pipe(
    z
      .string()
      .min(1, catalogoText.validation.areaRequired)
      .max(MAX_NOMBRE_AREA, catalogoText.validation.areaTooLong),
  )

/** Ids sin repetir (los dos selectores solo ofrecen recursos aún no asociados, pero se valida igual). */
export function sinRepetidos(ids: number[]): boolean {
  return new Set(ids).size === ids.length
}
