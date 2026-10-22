import { IconAlertTriangle, IconArrowDown, IconArrowUp, IconTrash } from '../../../../shared/ui/icons'
import type { Nivel } from '../api'
import { rutasText } from '../text'

const t = rutasText.escalera

interface EscaleraDeNivelesProps {
  /** Niveles en orden ascendente (el 1 es el de ingreso); la escalera los dibuja con el más alto arriba. */
  niveles: Nivel[]
  seleccionado: number | null
  onSeleccionar: (nivelId: number) => void
  /** Con el permiso de gestión se ofrecen subir, bajar y quitar. */
  puedeGestionar: boolean
  /** Hay una operación pendiente: se bloquean los controles. */
  ocupado: boolean
  onMover: (nivelId: number, direccion: 1 | -1) => void
  onQuitar: (nivel: Nivel) => void
}

const botonIcono = 'inline-flex items-center justify-center bg-transparent text-text hover:bg-soft disabled:cursor-not-allowed disabled:opacity-40'

/** Escalera vertical de niveles con el nivel 1 abajo (RutasAdmin.dc.html): cada peldaño se desplaza 14 px a la derecha. */
export function EscaleraDeNiveles({ niveles, seleccionado, onSeleccionar, puedeGestionar, ocupado, onMover, onQuitar }: EscaleraDeNivelesProps) {
  const dePeldano = niveles.map((nivel, indice) => ({ nivel, indice })).reverse()

  return (
    <ol aria-label={t.aria} className="m-0 flex list-none flex-col gap-2.5 p-0">
      {dePeldano.map(({ nivel, indice }) => {
        const elegido = nivel.id === seleccionado
        const puestoInactivo = nivel.puesto.activo === false
        const bloqueado = nivel.en_uso === true
        const esUltimo = indice === niveles.length - 1
        const nombrePuesto = nivel.puesto.nombre ?? nivel.nombre

        return (
          <li key={nivel.id} style={{ marginLeft: Math.min(indice, 6) * 14 }}>
            <div className={`flex items-stretch overflow-hidden rounded-xl border-2 bg-surface ${elegido ? 'border-accent' : 'border-border'}`}>
              <button
                type="button"
                onClick={() => onSeleccionar(nivel.id)}
                aria-pressed={elegido}
                aria-label={t.ver(nivel.orden, nivel.nombre)}
                className="flex min-h-11 min-w-0 flex-1 cursor-pointer flex-col gap-0.5 border-0 bg-transparent px-3.5 py-3 text-left text-text"
              >
                <span className="text-xs font-bold text-accent">{t.nivel(nivel.orden)}</span>
                <span className="text-[15px] font-semibold leading-snug">{nivel.nombre}</span>
                <span className="text-xs text-muted">
                  {nivel.puesto.codigo ?? '—'}
                  {nombrePuesto !== nivel.nombre ? ` · ${nombrePuesto}` : ''}
                  {nivel.inscritos_activos !== undefined ? ` · ${t.personas(nivel.inscritos_activos)}` : ''}
                </span>
                {puestoInactivo && (
                  <span className="mt-1 flex items-start gap-1 text-xs font-semibold text-warning">
                    <IconAlertTriangle size={14} className="mt-px shrink-0" />
                    {t.puestoInactivo}
                  </span>
                )}
              </button>
              {puedeGestionar && (
                <>
                  <div className="flex flex-col border-l border-border">
                    <button type="button" onClick={() => onMover(nivel.id, 1)} disabled={ocupado || esUltimo} aria-label={t.subir(nivel.nombre)} className={`size-10 ${botonIcono}`}>
                      <IconArrowUp size={16} />
                    </button>
                    <button type="button" onClick={() => onMover(nivel.id, -1)} disabled={ocupado || indice === 0} aria-label={t.bajar(nivel.nombre)} className={`size-10 border-t border-border ${botonIcono}`}>
                      <IconArrowDown size={16} />
                    </button>
                  </div>
                  <button
                    type="button"
                    onClick={() => onQuitar(nivel)}
                    disabled={ocupado || bloqueado}
                    aria-label={t.quitar(nivel.nombre)}
                    title={bloqueado ? t.enUso : undefined}
                    className={`w-11 border-l border-border text-danger ${botonIcono}`}
                  >
                    <IconTrash size={18} />
                  </button>
                </>
              )}
            </div>
          </li>
        )
      })}
    </ol>
  )
}
