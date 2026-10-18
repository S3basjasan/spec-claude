import { Badge } from '../../../../shared/ui/Badge'
import { IconX } from '../../../../shared/ui/icons'
import type { PosicionDelOrganigrama } from '../api'
import type { ArbolCalculado } from '../arbol'
import { organigramaText } from '../text'

interface PanelDeDetalleProps {
  posicion: PosicionDelOrganigrama
  arbol: ArbolCalculado
  /** Selecciona otra posición desde el panel (abre su rama y lleva el árbol hasta ella). */
  onSeleccionar: (id: number) => void
  onCerrar: () => void
  /** Falso cuando el panel va dentro de un diálogo que ya tiene su propio botón de cierre. */
  conCierre?: boolean
}

const text = organigramaText.detail
const seccion = 'flex flex-col gap-1'
const etiqueta = 'text-xs font-semibold uppercase tracking-wide text-muted'
const enlace =
  'flex w-full cursor-pointer items-center gap-2.5 rounded-lg border border-border bg-surface px-3 py-2.5 text-left text-sm hover:bg-soft'

function nombreDelOcupante(posicion: PosicionDelOrganigrama): string {
  return posicion.ocupante ? (posicion.ocupante.nombre ?? organigramaText.noOccupant) : organigramaText.noOccupant
}

/** Panel con el detalle de la posición seleccionada; todo sale de la respuesta ya cargada (sin otras peticiones). */
export function PanelDeDetalle({ posicion, arbol, onSeleccionar, onCerrar, conCierre = true }: PanelDeDetalleProps) {
  const supervisor = posicion.posicion_jefe_id === null ? null : (arbol.porId.get(posicion.posicion_jefe_id) ?? null)
  const subordinadas = arbol.hijosDe.get(posicion.id) ?? []
  const vacante = posicion.estado === 'Vacante'

  return (
    <aside aria-label={text.regionLabel} className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4">
      <div className="flex items-start justify-between gap-3">
        <div className="flex flex-col gap-1">
          <span className="text-xs tabular-nums text-muted">{posicion.codigo}</span>
          <h2 className="text-base font-bold leading-snug">{posicion.puesto.nombre}</h2>
          <div className="flex flex-wrap items-center gap-2 text-sm text-muted">
            <Badge variant={vacante ? 'warning' : 'default'}>{organigramaText.statuses[posicion.estado]}</Badge>
            <span>{organigramaText.modalities[posicion.modalidad]}</span>
          </div>
        </div>
        {conCierre && (
          <button
            type="button"
            aria-label={text.close}
            onClick={onCerrar}
            className="inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted hover:bg-soft"
          >
            <IconX size={18} />
          </button>
        )}
      </div>

      <section className={seccion}>
        <h3 className={etiqueta}>{text.occupant}</h3>
        <p className={`text-[15px] ${posicion.ocupante ? '' : 'italic text-muted'}`}>{nombreDelOcupante(posicion)}</p>
      </section>

      <section className={seccion}>
        <h3 className={etiqueta}>{text.supervisor}</h3>
        {supervisor ? (
          <button type="button" className={enlace} onClick={() => onSeleccionar(supervisor.id)}>
            <span className="flex flex-col">
              <span className="font-semibold">
                {supervisor.codigo} · {supervisor.puesto.nombre}
              </span>
              <span className={supervisor.ocupante ? 'text-muted' : 'italic text-muted'}>{nombreDelOcupante(supervisor)}</span>
            </span>
          </button>
        ) : (
          <p className="text-[15px]">{organigramaText.topAuthority}</p>
        )}
      </section>

      <section className={seccion}>
        <h3 className={etiqueta}>{text.supervises(subordinadas.length)}</h3>
        {subordinadas.length === 0 ? (
          <p className="text-[15px] text-muted">{text.noSubordinates}</p>
        ) : (
          <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {subordinadas.map((subordinada) => (
              <li key={subordinada.id}>
                <button type="button" className={enlace} onClick={() => onSeleccionar(subordinada.id)}>
                  <span className="flex min-w-0 flex-1 flex-col">
                    <span className="font-semibold">
                      {subordinada.codigo} · {subordinada.puesto.nombre}
                    </span>
                    <span className={subordinada.ocupante ? 'text-muted' : 'italic text-muted'}>{nombreDelOcupante(subordinada)}</span>
                  </span>
                  <Badge variant={subordinada.estado === 'Vacante' ? 'warning' : 'default'}>
                    {organigramaText.statuses[subordinada.estado]}
                  </Badge>
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className={seccion}>
        <h3 className={etiqueta}>{text.position}</h3>
        <p className="text-[15px] leading-relaxed">
          {posicion.puesto.nombre}
          <br />
          <span className="text-muted">{posicion.puesto.descripcion}</span>
        </p>
      </section>

      <section className={seccion}>
        <h3 className={etiqueta}>{text.unit}</h3>
        <p className="text-[15px] leading-relaxed">
          {posicion.unidad.nombre}
          <br />
          <span className="text-muted">{text.unitInfo(posicion.unidad.tipo.nombre, posicion.unidad.estado)}</span>
        </p>
      </section>
    </aside>
  )
}
