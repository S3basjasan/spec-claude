import { Button } from '../../../../shared/ui/Button'
import type { Inscripcion } from '../api'
import { rutasText } from '../text'
import { BadgeEstadoInscripcion } from './Etiquetas'

const t = rutasText.participantes

interface TablaDeParticipantesProps {
  inscripciones: Inscripcion[]
  /** Con el permiso de inscribir se ofrecen las acciones por fila. */
  puedeInscribir: boolean
  ocupado: boolean
  onCambiar: (inscripcion: Inscripcion) => void
  onAlcanzar: (inscripcion: Inscripcion) => void
  onRetirar: (inscripcion: Inscripcion) => void
}

function Nivel({ nivel }: { nivel: Inscripcion['nivel_actual'] }) {
  if (nivel === null) return <span className="text-muted">{t.sinNivel}</span>
  return (
    <div>
      <div className="text-xs font-bold text-accent">{rutasText.escalera.nivel(nivel.orden)}</div>
      <div className="text-sm font-semibold">{nivel.nombre}</div>
    </div>
  )
}

/** Participantes de la ruta (pestaña Participantes de RutasAdmin.dc.html): colaborador, niveles, estado y acciones por fila. */
export function TablaDeParticipantes({ inscripciones, puedeInscribir, ocupado, onCambiar, onAlcanzar, onRetirar }: TablaDeParticipantesProps) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-surface">
      <table aria-label={t.tabla} className="w-full min-w-170 border-collapse text-sm">
        <thead>
          <tr className="text-left text-[13px] text-muted">
            <th scope="col" className="py-3 pl-4.5 pr-3 font-semibold">
              {t.columnas.colaborador}
            </th>
            <th scope="col" className="px-3 py-3 font-semibold">
              {t.columnas.actual}
            </th>
            <th scope="col" className="px-3 py-3 font-semibold">
              {t.columnas.objetivo}
            </th>
            <th scope="col" className="px-3 py-3 font-semibold">
              {t.columnas.estado}
            </th>
            {puedeInscribir && (
              <th scope="col" className="py-3 pl-3 pr-4.5 text-right font-semibold">
                {t.columnas.acciones}
              </th>
            )}
          </tr>
        </thead>
        <tbody>
          {inscripciones.map((i) => {
            const activa = i.estado === 'Activa'
            return (
              <tr key={i.id} className="border-t border-border hover:bg-soft">
                <td className="py-2 pl-4.5 pr-3">
                  <div className="font-semibold">{i.colaborador.nombre}</div>
                  <div className="text-xs text-muted">{i.colaborador.departamento ?? rutasText.sinDato}</div>
                </td>
                <td className="px-3 py-2">
                  <Nivel nivel={i.nivel_actual} />
                </td>
                <td className="px-3 py-2">
                  <Nivel nivel={i.nivel_objetivo} />
                </td>
                <td className="px-3 py-2">
                  <BadgeEstadoInscripcion estado={i.estado} />
                  {i.motivo && <div className="mt-1 max-w-48 text-xs text-muted">{t.retiradoPor(i.motivo)}</div>}
                </td>
                {puedeInscribir && (
                  <td className="py-2 pl-3 pr-4.5 text-right">
                    {activa && (
                      <div className="flex flex-wrap justify-end gap-1.5">
                        <Button variant="secondary" disabled={ocupado} onClick={() => onCambiar(i)} aria-label={t.cambiarDe(i.colaborador.nombre)} className="min-h-9 px-3 text-[13px]">
                          {t.cambiar}
                        </Button>
                        <Button
                          variant="secondary"
                          disabled={ocupado || i.nivel_objetivo === null}
                          onClick={() => onAlcanzar(i)}
                          aria-label={t.alcanzadoDe(i.colaborador.nombre)}
                          title={i.nivel_objetivo === null ? rutasText.alcanzado.sinObjetivo : undefined}
                          className="min-h-9 px-3 text-[13px]"
                        >
                          {t.alcanzado}
                        </Button>
                        <Button variant="secondary" disabled={ocupado} onClick={() => onRetirar(i)} aria-label={t.retirarA(i.colaborador.nombre)} className="min-h-9 px-3 text-[13px] text-danger">
                          {t.retirar}
                        </Button>
                      </div>
                    )}
                  </td>
                )}
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
