/**
 * Mensaje de error de una lista completa (p. ej. «Agrega al menos una función»). Con resolvers de esquema el
 * error puede quedar en `message` o en `root`, según cómo lo haya registrado react-hook-form.
 */
export function mensajeDeLista(error: unknown): string | undefined {
  if (typeof error !== 'object' || error === null) return undefined
  const { message, root } = error as { message?: unknown; root?: { message?: unknown } }
  if (typeof root?.message === 'string') return root.message
  return typeof message === 'string' ? message : undefined
}
