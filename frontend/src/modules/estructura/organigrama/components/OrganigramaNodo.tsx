import type { NodoDelArbol } from '../arbol'
import { textoDeRama } from '../arbol'
import { organigramaText } from '../text'
import { TarjetaDePosicion } from './TarjetaDePosicion'

/** Lo que comparten todos los nodos del árbol; se arma una vez en la pantalla. */
export interface ContextoDelArbol {
  seleccionada: number | null
  /** Posiciones que coinciden con la búsqueda y el filtro, o nulo si no hay filtros. */
  coincidencias: Set<number> | null
  descendientes: Map<number, number>
  estaAbierta: (id: number) => boolean
  esContraible: (id: number) => boolean
  onAlternar: (id: number) => void
  onSeleccionar: (id: number) => void
}

interface OrganigramaNodoProps {
  nodo: NodoDelArbol
  contexto: ContextoDelArbol
}

/** Nodo recursivo del árbol (listas anidadas), sin límite de profundidad: tarjeta, botón de rama y subordinadas. */
export function OrganigramaNodo({ nodo, contexto }: OrganigramaNodoProps) {
  const { posicion, hijos } = nodo
  const abierta = contexto.estaAbierta(posicion.id)
  const resaltado = contexto.coincidencias === null ? 'normal' : contexto.coincidencias.has(posicion.id) ? 'coincide' : 'atenuada'
  const contraible = contexto.esContraible(posicion.id)
  const text = organigramaText.tree

  return (
    <li>
      <div className="flex flex-col items-center">
        <TarjetaDePosicion
          posicion={posicion}
          seleccionada={contexto.seleccionada === posicion.id}
          resaltado={resaltado}
          onSeleccionar={() => contexto.onSeleccionar(posicion.id)}
        />
        {hijos.length > 0 && (
          <button
            type="button"
            aria-expanded={abierta}
            aria-label={abierta ? text.collapseLabel(posicion.codigo) : text.expandLabel(posicion.codigo)}
            disabled={abierta && !contraible}
            onClick={() => contexto.onAlternar(posicion.id)}
            className="-mt-px inline-flex h-6 cursor-pointer items-center gap-1 rounded-b-lg border-[1.5px] border-t-0 border-border bg-surface px-2 text-xs font-semibold text-muted hover:bg-soft hover:text-accent disabled:cursor-not-allowed disabled:opacity-60"
          >
            {abierta ? text.collapse : textoDeRama(contexto.descendientes.get(posicion.id) ?? 0)}
          </button>
        )}
      </div>
      {abierta && hijos.length > 0 && (
        <ul>
          {hijos.map((hijo) => (
            <OrganigramaNodo key={hijo.posicion.id} nodo={hijo} contexto={contexto} />
          ))}
        </ul>
      )}
    </li>
  )
}
