import { useState } from 'react'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { IconFileText } from '../../../../shared/ui/icons'
import { BotonExportarFicha } from '../components/BotonExportarFicha'
import { exportacionText } from '../text'

const text = exportacionText.mine

/**
 * Exportar mi ficha. El permiso de exportación es independiente del de consulta por pantalla, por eso esta página no
 * depende de la consulta de la ficha: la acción solicita el PDF y es el servidor quien decide.
 */
export function MiExportacionPage() {
  const [sinPermiso, setSinPermiso] = useState(false)

  return (
    <div className="mx-auto flex max-w-225 flex-col gap-5">
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
        <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
      </div>
      <div className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
        {sinPermiso ? (
          <EmptyState icon={<IconFileText size={24} />} title={text.noPermissionTitle} description={text.noPermissionDescription} />
        ) : (
          <>
            <p className="text-sm text-muted">{text.note}</p>
            <BotonExportarFicha colaboradorId={null} onSinPermiso={() => setSinPermiso(true)} />
          </>
        )}
      </div>
    </div>
  )
}
