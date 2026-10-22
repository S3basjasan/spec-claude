import { RequireRole } from '../components/RequireRole'
import { VistaAdministrador } from '../components/VistaAdministrador'
import { MiRutaPage } from './MiRutaPage'

/**
 * Entrada de Rutas de carrera: el Administrador gestiona las rutas y el Colaborador consulta la suya. Cada persona tiene
 * un solo rol, por lo que no existe ningún interruptor entre vistas.
 */
export function RutasPage() {
  return <RequireRole admin={<VistaAdministrador />} colaborador={<MiRutaPage />} />
}
