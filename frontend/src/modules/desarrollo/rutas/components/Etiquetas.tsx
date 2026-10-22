import type { EstadoInscripcion, EstadoRuta, TipoRuta } from '../api'
import { estilosEstadoInscripcion, estilosEstadoRuta, estilosTipoRuta, pildora } from '../estilosEstado'
import { rutasText } from '../text'

/** Etiqueta de tipo de ruta. */
export function EtiquetaTipo({ tipo }: { tipo: TipoRuta }) {
  return <span className={`${pildora} ${estilosTipoRuta[tipo]}`}>{rutasText.tipos[tipo]}</span>
}

/** Badge del estado de la ruta: el color siempre va con el nombre del estado. */
export function BadgeEstadoRuta({ estado }: { estado: EstadoRuta }) {
  return <span className={`${pildora} ${estilosEstadoRuta[estado]}`}>{rutasText.estados[estado]}</span>
}

/** Badge del estado de la inscripción. */
export function BadgeEstadoInscripcion({ estado }: { estado: EstadoInscripcion }) {
  return <span className={`${pildora} ${estilosEstadoInscripcion[estado]}`}>{rutasText.estadosInscripcion[estado]}</span>
}
