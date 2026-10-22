import { useSearchParams } from 'react-router-dom'
import { TIPOS, type FiltroSituacion, type TipoPolitica } from '../api'

const SITUACIONES: FiltroSituacion[] = ['SinConfigurar', 'Vigente', 'NoAdmiteSolicitudes', 'ConProgramada']

interface Cambios {
  tipo?: TipoPolitica | ''
  situacion?: FiltroSituacion | ''
}

/** Tipo y situación del listado, guardados en la URL para poder compartirlos. */
export function usePoliticasParams() {
  const [params, setParams] = useSearchParams()

  const tipo = TIPOS.find((valor) => valor === params.get('tipo')) ?? null
  const situacion = SITUACIONES.find((valor) => valor === params.get('situacion')) ?? null

  const update = (cambios: Cambios) => {
    const next = new URLSearchParams(params)
    if (cambios.tipo !== undefined) {
      if (cambios.tipo) next.set('tipo', cambios.tipo)
      else next.delete('tipo')
    }
    if (cambios.situacion !== undefined) {
      if (cambios.situacion) next.set('situacion', cambios.situacion)
      else next.delete('situacion')
    }
    setParams(next, { replace: true })
  }

  return { tipo, situacion, update }
}
