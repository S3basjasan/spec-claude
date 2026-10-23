import { SinAccesoPdi } from '../components/RequirePermiso'
import { usePermisosPdi } from '../hooks/usePdi'
import { PERMISOS, tieneAlgunaDeGestion, tienePermiso } from '../permisos'
import { pdiText } from '../text'
import { ListaDePdiPage } from './ListaDePdiPage'
import { MiPlanDeDesarrolloPage } from './MiPlanDeDesarrolloPage'

/**
 * Entrada de «PDI»: quien tiene alguna clave de gestión ve la lista del administrador y quien solo tiene `ver_propio` ve
 * «Mi plan de desarrollo». La decisión es por permisos, no por el nombre del rol.
 */
export function EntradaPdiPage() {
  const { data: permisos, isPending } = usePermisosPdi()

  if (isPending) return <div role="status" aria-label={pdiText.loading} />
  if (tieneAlgunaDeGestion(permisos)) return <ListaDePdiPage />
  if (tienePermiso(permisos, PERMISOS.verPropio)) return <MiPlanDeDesarrolloPage />

  return <SinAccesoPdi />
}
