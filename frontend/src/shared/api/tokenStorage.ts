const TOKEN_KEY = 'hrms.token'

/**
 * Persistencia del token Bearer. Solo la capa HTTP compartida y los hooks de sesión lo usan.
 * El almacenamiento puede no estar disponible (modo privado, datos bloqueados): nunca lanza.
 */
export const tokenStorage = {
  get(): string | null {
    try {
      return localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set(token: string): void {
    try {
      localStorage.setItem(TOKEN_KEY, token)
    } catch {
      // Sin almacenamiento: la sesión solo dura mientras la pestaña esté abierta.
    }
  },
  clear(): void {
    try {
      localStorage.removeItem(TOKEN_KEY)
    } catch {
      // Nada que limpiar.
    }
  },
}
