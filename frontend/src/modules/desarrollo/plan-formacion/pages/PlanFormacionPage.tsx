import { RequireRole } from '../components/RequireRole'
import { MiPlanPage } from './MiPlanPage'
import { PlanesPage } from './PlanesPage'

/**
 * Entrada del Plan anual: el Administrador ve la tabla de planes y el Colaborador sus acciones. Cada persona tiene un
 * solo rol, por lo que no existe ningún interruptor entre vistas.
 */
export function PlanFormacionPage() {
  return <RequireRole admin={<PlanesPage />} colaborador={<MiPlanPage />} />
}
