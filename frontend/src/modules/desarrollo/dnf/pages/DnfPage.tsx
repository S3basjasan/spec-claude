import { RequireRole } from '../components/RequireRole'
import { DnfAdminPage } from './DnfAdminPage'
import { MiDiagnosticoPage } from './MiDiagnosticoPage'

/**
 * Entrada de la DNF: el Administrador ve el asistente de generación y el Colaborador su diagnóstico. Cada persona
 * tiene un solo rol, por lo que no existe ningún interruptor entre vistas.
 */
export function DnfPage() {
  return <RequireRole admin={<DnfAdminPage />} colaborador={<MiDiagnosticoPage />} />
}
