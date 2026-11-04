import type { ReactNode } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { Tabs } from '../../shared/ui/Tabs'
import { useCurrentUser } from '../auth/login'
import { parametrosText } from './parametros/text'

const RUTAS = {
  categorias: '/compensaciones/categorias',
  parametros: '/compensaciones/parametros',
} as const

type Pestana = keyof typeof RUTAS

const PESTANAS: { id: Pestana; label: string }[] = [
  { id: 'categorias', label: parametrosText.tabs.categorias },
  { id: 'parametros', label: parametrosText.tabs.parametros },
]

/**
 * Pestañas «Categorías | Parámetros» del módulo. Usa `Tabs` de shared/ui sin modificarlo: cada pestaña es una ruta, así
 * que al cambiar navega y el contenido de la página activa se pasa como `children`. Solo el Gestor ve las pestañas;
 * el resto ve la página como antes. El backend sigue siendo la autoridad.
 */
export function PestanasCompensaciones({ children }: { children: ReactNode }) {
  const { data: usuario } = useCurrentUser()
  const { pathname } = useLocation()
  const navigate = useNavigate()

  if (usuario?.role !== 'admin') return <>{children}</>

  const activa: Pestana = pathname.startsWith(RUTAS.parametros) ? 'parametros' : 'categorias'

  return (
    <Tabs
      label={parametrosText.tabsLabel}
      tabs={PESTANAS}
      active={activa}
      onChange={(id) => navigate(RUTAS[id as Pestana])}
      idBase="compensaciones"
    >
      {children}
    </Tabs>
  )
}
