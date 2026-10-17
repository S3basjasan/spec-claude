import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { Categoria } from '../api'
import { useCategoria } from '../hooks/useCategorias'
import { categoriasReconocimientoText } from '../text'
import { CategoriaFormModal } from './CategoriaFormModal'

interface EditarCategoriaProps {
  id: number
  onCerrar: () => void
  onGuardado: (categoria: Categoria) => void
}

/** Carga la categoría con sus indicadores (el listado no los incluye) y abre el formulario con los datos precargados. */
export function EditarCategoria({ id, onCerrar, onGuardado }: EditarCategoriaProps) {
  const consulta = useCategoria(id)
  const text = categoriasReconocimientoText.detail
  const cerrar = (
    <Button variant="secondary" onClick={onCerrar}>
      {text.close}
    </Button>
  )

  if (consulta.isPending) {
    return (
      <Modal open title={categoriasReconocimientoText.form.editTitle} closeLabel={text.close} onClose={onCerrar} footer={cerrar}>
        <div role="status" aria-label={text.loading} className="flex flex-col gap-3">
          <Skeleton className="h-10" />
          <Skeleton className="h-24" />
          <Skeleton className="h-10" />
        </div>
      </Modal>
    )
  }

  if (consulta.isError) {
    const noExiste = toApiError(consulta.error).status === 404
    return (
      <Modal open title={categoriasReconocimientoText.form.editTitle} closeLabel={text.close} onClose={onCerrar} footer={cerrar}>
        <Alert>{noExiste ? text.notFoundDescription : text.errorDescription}</Alert>
      </Modal>
    )
  }

  return <CategoriaFormModal categoria={consulta.data} onCerrar={onCerrar} onGuardado={onGuardado} />
}
