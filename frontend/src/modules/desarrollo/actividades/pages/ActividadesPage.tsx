import { VistaAdministrador } from '../components/VistaAdministrador'
import { RequireRole } from '../components/RequireRole'
import { MisActividadesPage } from './MisActividadesPage'

/**
 * Entrada de Actividades: el Administrador gestiona las actividades y sus calificaciones y el Colaborador consulta las
 * suyas. Cada persona tiene un solo rol, por lo que no existe ningún interruptor entre vistas.
 */
export function ActividadesPage() {
  return <RequireRole admin={<VistaAdministrador />} colaborador={<MisActividadesPage />} />
}
