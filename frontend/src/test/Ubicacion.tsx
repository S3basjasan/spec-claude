import { useLocation } from 'react-router-dom'

/** Expone la ubicación actual del enrutador en memoria para las pruebas. */
export function Ubicacion() {
  const location = useLocation()
  return <output data-testid="ubicacion">{location.pathname + location.search}</output>
}
