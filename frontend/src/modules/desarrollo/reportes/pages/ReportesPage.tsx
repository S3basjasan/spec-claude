import { RequireRole } from '../components/RequireRole'
import { VistaAdministrador } from '../components/VistaAdministrador'
import { MiProgresoPage } from './MiProgresoPage'

/**
 * Entrada de Reportes: el Administrador evalúa los resultados de la formación y el Colaborador ve su progreso. Cada
 * persona tiene un solo rol, por lo que no existe ningún interruptor entre vistas.
 */
export function ReportesPage() {
  return <RequireRole admin={<VistaAdministrador />} colaborador={<MiProgresoPage />} />
}
