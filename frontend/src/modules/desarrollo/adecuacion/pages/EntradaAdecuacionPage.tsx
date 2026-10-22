import { SinAccesoAdecuacion } from '../components/RequirePermiso'
import { usePermisosAdecuacion } from '../hooks/useAdecuacion'
import { PERMISOS, tieneAlgunaDeGestion, tienePermiso } from '../permisos'
import { adecuacionText } from '../text'
import { AdecuacionPage } from './AdecuacionPage'
import { MiAdecuacionPage } from './MiAdecuacionPage'

/**
 * Entrada de «Adecuación»: quien tiene alguna clave de gestión ve la página del administrador y quien solo tiene
 * `ver_propio` ve «Mi adecuación». La decisión es por permisos, no por el nombre del rol.
 */
export function EntradaAdecuacionPage() {
  const { data: permisos, isPending } = usePermisosAdecuacion()

  if (isPending) return <div role="status" aria-label={adecuacionText.loading} />
  if (tieneAlgunaDeGestion(permisos)) return <AdecuacionPage />
  if (tienePermiso(permisos, PERMISOS.verPropio)) return <MiAdecuacionPage />

  return <SinAccesoAdecuacion />
}
