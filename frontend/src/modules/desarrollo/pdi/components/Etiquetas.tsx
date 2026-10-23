import type { EstadoAccion, EstadoPdi, ResultadoPdi, TipoPdi } from '../api'
import { estilosEstadoAccion, estilosEstadoPdi, estilosResultado, estilosTipoPdi, pildora } from '../estilosEstado'
import { pdiText } from '../text'

export function BadgeDeEstadoDePdi({ valor }: { valor: EstadoPdi }) {
  return <span className={`${pildora} ${estilosEstadoPdi[valor]}`}>{pdiText.estados[valor]}</span>
}

export function BadgeDeTipoDePdi({ valor }: { valor: TipoPdi }) {
  return <span className={`${pildora} ${estilosTipoPdi[valor]}`}>{pdiText.tipos[valor]}</span>
}

export function BadgeDeEstadoDeAccion({ valor }: { valor: EstadoAccion }) {
  return <span className={`${pildora} ${estilosEstadoAccion[valor]}`}>{pdiText.estadosAccion[valor]}</span>
}

export function BadgeDeResultado({ valor }: { valor: ResultadoPdi }) {
  return <span className={`${pildora} ${estilosResultado[valor]}`}>{pdiText.resultados[valor]}</span>
}

/** Barra de avance con el porcentaje (o «Sin avance» cuando no es calculable). */
export function BarraDeAvance({ valor }: { valor: number | null }) {
  return (
    <div className="flex items-center gap-2">
      <div
        role="progressbar"
        aria-label={pdiText.detalle.avance}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={valor ?? undefined}
        aria-valuetext={valor === null ? pdiText.lista.sinAvance : pdiText.formatos.porcentaje(valor)}
        className="h-2 w-24 overflow-hidden rounded-full bg-bg"
      >
        <div className="h-full rounded-full bg-accent" style={{ width: `${valor ?? 0}%` }} />
      </div>
      <span className="text-[13px] text-muted">{valor === null ? pdiText.lista.sinAvance : pdiText.formatos.porcentaje(valor)}</span>
    </div>
  )
}
