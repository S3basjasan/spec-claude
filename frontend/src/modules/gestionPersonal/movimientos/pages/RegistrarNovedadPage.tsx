import { useSearchParams } from 'react-router-dom'
import { PantallaDeNovedad } from '../components/PantallaDeNovedad'

/** `/movimientos-personal/novedad`: el Administrador registra por un colaborador; con `?tipo=Ausencia` informa un hecho ocurrido. */
export function RegistrarNovedadPage() {
  const [params] = useSearchParams()

  return <PantallaDeNovedad rol="administrador" modo={params.get('tipo') === 'Ausencia' ? 'ausencia' : 'novedad'} />
}
