import { RequireRole } from '../components/RequireRole'
import { MiAprendizajePage } from './MiAprendizajePage'
import { RepositorioAdminPage } from './RepositorioAdminPage'

/**
 * Entrada de micro-learning: el Administrador ve el repositorio y el Colaborador su aprendizaje. Cada persona tiene
 * un solo rol, por lo que no existe ningún interruptor entre vistas.
 */
export function MicroLearningPage() {
  return <RequireRole admin={<RepositorioAdminPage />} colaborador={<MiAprendizajePage />} />
}
