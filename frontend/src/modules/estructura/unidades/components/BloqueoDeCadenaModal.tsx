import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import type { UnidadEnConflicto } from '../api'
import { unidadesText } from '../text'

const text = unidadesText.block

interface BloqueoDeCadenaModalProps {
  unidades: UnidadEnConflicto[]
  /** "Ver en el árbol": muestra la unidad en el listado. */
  onVer: (unidad: UnidadEnConflicto) => void
  /** Vuelve al borrador sin perderlo. */
  onVolver: () => void
}

/** Bloqueo de un reordenamiento: no se aplicó nada y estas unidades quedarían bajo una padre de nivel igual o más profundo. */
export function BloqueoDeCadenaModal({ unidades, onVer, onVolver }: BloqueoDeCadenaModalProps) {
  return (
    <Modal
      open
      nested
      size="lg"
      title={text.title}
      description={text.description(unidades.length)}
      onClose={onVolver}
      footer={<Button onClick={onVolver}>{text.backToEdit}</Button>}
    >
      <ul aria-label={text.listLabel} className="m-0 flex list-none flex-col gap-2 p-0">
        {unidades.map((unidad) => (
          <li key={unidad.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-bg px-4 py-3">
            <div className="min-w-0 text-sm">
              <p className="font-semibold">
                {unidad.codigo} {unidad.nombre}
              </p>
              <p className="text-muted">{text.item(unidad.tipo.nombre, unidad.tipo.nivel)}</p>
              <p className="text-muted">
                {text.parent(unidad.unidad_padre.nombre, unidad.unidad_padre.codigo, unidad.unidad_padre.tipo.nombre, unidad.unidad_padre.tipo.nivel)}
              </p>
            </div>
            <Button variant="secondary" aria-label={text.viewInTree(unidad.nombre)} onClick={() => onVer(unidad)}>
              {text.viewInTreeLabel}
            </Button>
          </li>
        ))}
      </ul>
    </Modal>
  )
}
