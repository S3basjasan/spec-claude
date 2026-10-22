import type { EstadoResultado, Preparacion, TipoCambio } from '../api'
import { estilosEstadoResultado, estilosPreparacion, estilosTipoCambio, pildora } from '../estilosEstado'
import { adecuacionText } from '../text'

export function PreparacionBadge({ valor }: { valor: Preparacion }) {
  return <span className={`${pildora} ${estilosPreparacion[valor]}`}>{adecuacionText.preparacion[valor]}</span>
}

export function EstadoResultadoBadge({ valor }: { valor: EstadoResultado }) {
  return <span className={`${pildora} ${estilosEstadoResultado[valor]}`}>{adecuacionText.estado[valor]}</span>
}

export function TipoCambioBadge({ valor }: { valor: TipoCambio }) {
  return <span className={`${pildora} ${estilosTipoCambio[valor]}`}>{adecuacionText.tipoCambio[valor]}</span>
}
