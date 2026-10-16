import { zodResolver } from '@hookform/resolvers/zod'
import { useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Alert } from '../../../../shared/ui/Alert'
import { Badge } from '../../../../shared/ui/Badge'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { TextField } from '../../../../shared/ui/TextField'
import type { Categoria } from '../api'
import { useGuardarCategoria } from '../hooks/useGuardarCategoria'
import { categoriasText } from '../text'
import { categoriaSchema, LIMITE_NOMBRE, problemaDeGuardado, type CategoriaFormValues, type ProblemaDeGuardado } from '../validation'

const text = categoriasText.form
const FORM_ID = 'formulario-categoria'

interface FormularioCategoriaProps {
  /** Categoría vigente a corregir; sin ella, alta. La versión se lee en cada envío. */
  categoria?: Categoria
  onCerrar: () => void
  onGuardado: (categoria: Categoria) => void
  /** Vuelve a consultar el catálogo tras un conflicto (conserva lo escrito en el formulario). */
  onActualizar: () => void
}

function Aviso({ aviso, onActualizar }: { aviso: ProblemaDeGuardado['aviso']; onActualizar: () => void }) {
  switch (aviso) {
    case 'conflicto':
      return (
        <Alert>
          <p className="font-semibold">{text.conflictTitle}</p>
          <p>{text.conflict}</p>
          <Button variant="secondary" className="mt-2" onClick={onActualizar}>
            {text.reload}
          </Button>
        </Alert>
      )
    case 'noExiste':
      return <Alert>{text.notFound}</Alert>
    case 'deshabilitada':
      return <Alert>{text.writeDisabled}</Alert>
    case 'red':
      return <Alert>{text.network}</Alert>
    case 'inesperado':
      return <Alert>{text.unexpected}</Alert>
    default:
      return null
  }
}

/** Formulario único de crear y corregir el nombre, en un diálogo. */
export function FormularioCategoria({ categoria, onCerrar, onGuardado, onActualizar }: FormularioCategoriaProps) {
  const guardar = useGuardarCategoria()
  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<CategoriaFormValues>({
    resolver: zodResolver(categoriaSchema),
    defaultValues: { nombre: categoria?.nombre ?? '' },
  })
  const [problema, setProblema] = useState<ProblemaDeGuardado>({ nombre: null, aviso: null })
  const enviando = useRef(false)
  const valores = useWatch({ control })

  const errorNombre = errors.nombre?.message ?? problema.nombre ?? undefined
  const hayErrores = errorNombre !== undefined
  const largo = String(valores.nombre ?? '').length

  const recargar = () => {
    setProblema({ nombre: null, aviso: null })
    onActualizar()
  }

  const enviar = (formulario: CategoriaFormValues) => {
    if (enviando.current) return
    enviando.current = true
    setProblema({ nombre: null, aviso: null })
    guardar.mutate(
      { nombre: formulario.nombre.trim(), fila: categoria ? { id: categoria.id, version: categoria.version } : undefined },
      {
        onSuccess: onGuardado,
        onError: (e) => setProblema(problemaDeGuardado(e)),
        onSettled: () => {
          enviando.current = false
        },
      },
    )
  }

  return (
    <Modal
      open
      title={categoria ? text.editTitle : text.createTitle}
      busy={guardar.isPending}
      closeLabel={text.close}
      onClose={onCerrar}
      footer={
        <>
          <Button variant="secondary" disabled={guardar.isPending} onClick={onCerrar}>
            {text.cancel}
          </Button>
          <Button type="submit" form={FORM_ID} loading={guardar.isPending}>
            {text.save}
          </Button>
        </>
      }
    >
      <form id={FORM_ID} noValidate onSubmit={(evento) => void handleSubmit(enviar)(evento)} className="flex flex-col gap-4">
        {hayErrores && <Alert>{text.errorsAlert}</Alert>}
        <Aviso aviso={problema.aviso} onActualizar={recargar} />

        <TextField
          id="categoria-nombre"
          label={text.nameLabel}
          error={errorNombre}
          hint={`${largo}/${LIMITE_NOMBRE}`}
          autoComplete="off"
          {...register('nombre')}
        />

        {categoria && (
          <div className="flex flex-col gap-1.5">
            <span className="text-sm font-semibold">{text.statusLabel}</span>
            <div>
              <Badge variant={categoria.estado === 'Activa' ? 'default' : 'neutral'}>{categoriasText.estados[categoria.estado]}</Badge>
            </div>
          </div>
        )}
      </form>
    </Modal>
  )
}
