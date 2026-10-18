import { useEffect, type ReactNode } from 'react'
import { useArrastre } from '../hooks/useArrastre'
import { organigramaText } from '../text'
import { ControlesDeZoom } from './ControlesDeZoom'

interface AreaDelArbolProps {
  children: ReactNode
  zoom: number
  onAlejar: () => void
  onAcercar: () => void
  onRestablecer: () => void
  /** Primera coincidencia de la búsqueda: el árbol se lleva hasta ella cuando cambia. */
  llevarABusqueda: { id: number; clave: string } | null
  /** Posición elegida desde el panel: el árbol se lleva hasta ella cada vez que se elige. */
  llevarASeleccion: { id: number; clave: string } | null
}

/**
 * Área del árbol: 640 px de alto, desplazable con el puntero (arrastrando), las barras y el teclado (recibe foco), con los
 * controles de zoom arriba a la derecha. El zoom se aplica al contenedor del árbol.
 */
export function AreaDelArbol({ children, zoom, onAlejar, onAcercar, onRestablecer, llevarABusqueda, llevarASeleccion }: AreaDelArbolProps) {
  const { ref, manejadores } = useArrastre<HTMLDivElement>()
  const llevar = (id: number) => {
    const tarjeta = ref.current?.querySelector<HTMLElement>(`[data-posicion-id="${id}"]`)
    if (!tarjeta) return
    const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    tarjeta.scrollIntoView({ block: 'center', inline: 'center', behavior: sinMovimiento ? 'auto' : 'smooth' })
  }

  // Solo cuando cambia el objetivo, no en cada dibujo del árbol.
  useEffect(() => {
    if (llevarABusqueda) llevar(llevarABusqueda.id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [llevarABusqueda?.id, llevarABusqueda?.clave])

  useEffect(() => {
    if (llevarASeleccion) llevar(llevarASeleccion.id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [llevarASeleccion?.id, llevarASeleccion?.clave])

  return (
    <div className="flex flex-col gap-2">
      <div className="relative overflow-hidden rounded-xl border border-border bg-surface">
        <div className="absolute right-3 top-3 z-10">
          <ControlesDeZoom zoom={zoom} onAlejar={onAlejar} onAcercar={onAcercar} onRestablecer={onRestablecer} />
        </div>
        <div
          ref={ref}
          role="region"
          aria-label={organigramaText.tree.areaLabel}
          tabIndex={0}
          {...manejadores}
          className="h-160 cursor-grab select-none overflow-auto active:cursor-grabbing"
        >
          <div className="organigrama-arbol w-max min-w-full px-8 pb-10 pt-18" style={{ zoom }}>
            <ul>{children}</ul>
          </div>
        </div>
      </div>
      <p className="text-[13px] text-muted">{organigramaText.tree.help}</p>
    </div>
  )
}
