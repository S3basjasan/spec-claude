import { useId } from 'react'
import { agruparPorArea, type GrupoDeArea } from '../agrupar'
import type { FilaCatalogo } from '../api'
import { TarjetaCompetencia } from './TarjetaCompetencia'

interface GrupoPorAreaProps {
  filas: FilaCatalogo[]
  onAbrir: (id: number) => void
}

function Seccion({ grupo, onAbrir }: { grupo: GrupoDeArea; onAbrir: (id: number) => void }) {
  const titulo = useId()

  return (
    <section aria-labelledby={titulo} className="flex flex-col gap-3">
      <h2 id={titulo} className="text-lg font-bold">
        {grupo.nombre}
      </h2>
      <ul className="m-0 grid list-none gap-4 p-0" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))' }}>
        {grupo.filas.map((fila) => (
          <li key={fila.competencia_id}>
            <TarjetaCompetencia fila={fila} onAbrir={onAbrir} />
          </li>
        ))}
      </ul>
    </section>
  )
}

/** Tarjetas de competencias agrupadas por área formativa. */
export function GrupoPorArea({ filas, onAbrir }: GrupoPorAreaProps) {
  return (
    <div className="flex flex-col gap-8">
      {agruparPorArea(filas).map((grupo) => (
        <Seccion key={grupo.clave} grupo={grupo} onAbrir={onAbrir} />
      ))}
    </div>
  )
}
