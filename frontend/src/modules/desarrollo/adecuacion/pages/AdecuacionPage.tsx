import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Tabs, type TabItem } from '../../../../shared/ui/Tabs'
import type { PestanaAdecuacion } from '../api'
import { PestanaComparar } from '../components/PestanaComparar'
import { PestanaDiagnostico } from '../components/PestanaDiagnostico'
import { PestanaEvaluaciones } from '../components/PestanaEvaluaciones'
import { PestanaMapa } from '../components/PestanaMapa'
import { PestanaParametros } from '../components/PestanaParametros'
import { usePermisosAdecuacion } from '../hooks/useAdecuacion'
import { PERMISOS, tienePermiso } from '../permisos'
import { adecuacionText } from '../text'

const t = adecuacionText

/** Pestañas del administrador con la clave que exige cada una; una pestaña sin permiso no se muestra. */
const PESTANAS: { id: PestanaAdecuacion; clave: (typeof PERMISOS)[keyof typeof PERMISOS] }[] = [
  { id: 'diagnostico', clave: PERMISOS.generar },
  { id: 'comparar', clave: PERMISOS.comparar },
  { id: 'mapa', clave: PERMISOS.mapa },
  { id: 'evaluaciones', clave: PERMISOS.ver },
  { id: 'parametros', clave: PERMISOS.parametros },
]

/** Página del administrador: «Adecuación a puesto futuro» con sus pestañas. La pestaña activa va en la URL. */
export function AdecuacionPage() {
  const { data: permisos } = usePermisosAdecuacion()
  const [params, setParams] = useSearchParams()
  const [mensaje, setMensaje] = useState<string | null>(null)

  const visibles = PESTANAS.filter((p) => tienePermiso(permisos, p.clave))
  const pedida = params.get('pestana')
  const activa = visibles.find((p) => p.id === pedida)?.id ?? visibles[0]?.id ?? 'diagnostico'

  const cambiar = (id: string) => {
    // Cada pestaña tiene sus propios filtros: al cambiar se parte de una URL limpia.
    const siguiente = new URLSearchParams()
    if (id !== visibles[0]?.id) siguiente.set('pestana', id)
    setParams(siguiente, { replace: true })
    setMensaje(null)
  }

  const items: TabItem[] = visibles.map((p) => ({ id: p.id, label: t.pestanas[p.id] }))

  return (
    <div className="mx-auto flex max-w-[1200px] flex-col gap-5">
      <header>
        <p className="m-0 text-[13px] font-semibold text-muted">{t.sobretitulo}</p>
        <h1 className="m-0 mt-0.5 text-2xl font-bold">{t.titulo}</h1>
        <p className="m-0 mt-1 text-muted">{t.descripcion}</p>
      </header>

      {/* <p role="note" className="m-0 rounded-xl border border-border bg-infosoft px-4 py-3 text-sm text-info">
        {t.avisoGuia}
      </p> */}

      {mensaje !== null && (
        <Alert tone="neutral">
          <div className="flex flex-wrap items-center gap-3">
            <span>{mensaje}</span>
            <Button variant="secondary" type="button" onClick={() => setMensaje(null)}>
              {t.cerrar}
            </Button>
          </div>
        </Alert>
      )}

      <Tabs label={t.pestanas.etiqueta} tabs={items} active={activa} onChange={cambiar} idBase="adecuacion" scrollable>
        {activa === 'diagnostico' && <PestanaDiagnostico onRegistrada={setMensaje} />}
        {activa === 'comparar' && <PestanaComparar />}
        {activa === 'mapa' && <PestanaMapa />}
        {activa === 'evaluaciones' && <PestanaEvaluaciones />}
        {activa === 'parametros' && <PestanaParametros />}
      </Tabs>
    </div>
  )
}
