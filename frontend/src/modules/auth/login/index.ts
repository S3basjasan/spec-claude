// Contrato público del módulo de autenticación: es lo único que otros módulos pueden importar.
export { useCurrentUser } from './hooks/useCurrentUser'
export type { CurrentUser, Role } from './api'
