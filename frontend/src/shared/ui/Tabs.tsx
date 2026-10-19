import { useRef, type KeyboardEvent, type ReactNode } from 'react'

export interface TabItem {
  id: string
  label: string
}

interface TabsProps {
  /** Etiqueta accesible del grupo de pestañas. */
  label: string
  tabs: TabItem[]
  /** Id de la pestaña activa. */
  active: string
  onChange: (id: string) => void
  /** Contenido de la pestaña activa (se renderiza dentro del `tabpanel`). */
  children: ReactNode
  /** Prefijo para los ids de pestañas y paneles (único por pantalla). */
  idBase?: string
  /** Con muchas pestañas o rótulos largos, la lista se desplaza dentro de su propio contenedor en lugar de ensanchar la página. */
  scrollable?: boolean
}

/**
 * Pestañas accesibles (design/DESIGN.md §4): `tablist` con botones `tab` de 44px, flechas para moverse entre ellas
 * y un único `tabpanel` asociado a la activa.
 */
export function Tabs({ label, tabs, active, onChange, children, idBase = 'tabs', scrollable = false }: TabsProps) {
  const refs = useRef<Record<string, HTMLButtonElement | null>>({})

  const moverA = (indice: number) => {
    const destino = tabs[(indice + tabs.length) % tabs.length]
    onChange(destino.id)
    refs.current[destino.id]?.focus()
  }

  const alTeclear = (evento: KeyboardEvent<HTMLButtonElement>, indice: number) => {
    if (evento.key === 'ArrowRight') {
      evento.preventDefault()
      moverA(indice + 1)
    } else if (evento.key === 'ArrowLeft') {
      evento.preventDefault()
      moverA(indice - 1)
    } else if (evento.key === 'Home') {
      evento.preventDefault()
      moverA(0)
    } else if (evento.key === 'End') {
      evento.preventDefault()
      moverA(tabs.length - 1)
    }
  }

  return (
    <div>
      <div role="tablist" aria-label={label} className={`flex gap-1 border-b border-border ${scrollable ? 'overflow-x-auto overflow-y-hidden pb-px' : ''}`}>
        {tabs.map((tab, indice) => {
          const seleccionada = tab.id === active
          return (
            <button
              key={tab.id}
              ref={(el) => {
                refs.current[tab.id] = el
              }}
              type="button"
              role="tab"
              id={`${idBase}-tab-${tab.id}`}
              aria-selected={seleccionada}
              aria-controls={`${idBase}-panel-${tab.id}`}
              tabIndex={seleccionada ? 0 : -1}
              onClick={() => onChange(tab.id)}
              onKeyDown={(e) => alTeclear(e, indice)}
              className={`-mb-px inline-flex h-11 items-center border-b-[3px] px-4 text-[15px] font-semibold transition-colors ${scrollable ? 'shrink-0 whitespace-nowrap ' : ''}${
                seleccionada ? 'border-accent text-accent' : 'border-transparent text-muted hover:text-text'
              }`}
            >
              {tab.label}
            </button>
          )
        })}
      </div>
      <div role="tabpanel" id={`${idBase}-panel-${active}`} aria-labelledby={`${idBase}-tab-${active}`} className="pt-5">
        {children}
      </div>
    </div>
  )
}
