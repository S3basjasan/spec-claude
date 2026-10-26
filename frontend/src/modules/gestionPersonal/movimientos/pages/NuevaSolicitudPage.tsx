import { PantallaDeNovedad } from '../components/PantallaDeNovedad'

/** `/mis-solicitudes/nueva`: el Colaborador presenta una novedad propia. */
export function NuevaSolicitudPage() {
  return <PantallaDeNovedad rol="colaborador" modo="novedad" />
}
