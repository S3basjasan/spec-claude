import type { FieldErrors, FieldValues } from 'react-hook-form'

/** Primer mensaje de error que haya dentro de un grupo de campos (el mensaje se muestra una sola vez). */
export function primerMensaje(errores: unknown): string | undefined {
  if (errores === null || typeof errores !== 'object') return undefined
  const { message, ...resto } = errores as { message?: unknown }
  if (typeof message === 'string' && message !== '') return message
  for (const valor of Object.values(resto)) {
    const encontrado = primerMensaje(valor)
    if (encontrado) return encontrado
  }
  return undefined
}

/** `true` si hay un error en esa ruta del formulario (p. ej. `grados.2`). */
export function conErrorEn<T extends FieldValues>(errores: FieldErrors<T>, ruta: string): boolean {
  let actual: unknown = errores
  for (const parte of ruta.split('.')) {
    if (actual === null || typeof actual !== 'object') return false
    actual = (actual as Record<string, unknown>)[parte]
  }
  return actual !== undefined
}
