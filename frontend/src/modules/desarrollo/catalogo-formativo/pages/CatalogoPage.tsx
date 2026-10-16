import { RequireRole } from '../components/RequireRole'
import { CatalogoAdminPage } from './CatalogoAdminPage'
import { CatalogoColaboradorPage } from './CatalogoColaboradorPage'

/**
 * Entrada del catálogo formativo: el Administrador ve la matriz y el Colaborador ve las tarjetas. Cada persona
 * tiene un solo rol, por lo que no existe ningún interruptor entre vistas.
 */
export function CatalogoPage() {
  return <RequireRole admin={<CatalogoAdminPage />} colaborador={<CatalogoColaboradorPage />} />
}
