import { useState, type FormEvent } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { TextField } from '../../../../shared/ui/TextField'
import { MAX_NOMBRE_TIPO } from '../api'
import { useCrearTipo } from '../hooks/useTiposUnidad'
import { unidadesText } from '../text'
import { claveDeNombre, nombreDeTipoSchema } from '../validation'

const text = unidadesText.newType

interface NuevoTipoModalProps {
  /** Nombres de los tipos actuales: el último es el nivel bajo el que se agrega. */
  nombresActuales: string[]
  onCerrar: () => void
  onCreado: () => void
}

/** Modal "Nuevo tipo": agrega un tipo al final de la cadena, como hijo del último nivel actual. */
export function NuevoTipoModal({ nombresActuales, onCerrar, onCreado }: NuevoTipoModalProps) {
  const crear = useCrearTipo()
  const [nombre, setNombre] = useState('')
  const [error, setError] = useState<string | undefined>()
  const [problema, setProblema] = useState<'red' | 'inesperado' | null>(null)

  const guardar = (evento: FormEvent) => {
    evento.preventDefault()
    if (crear.isPending) return
    setProblema(null)

    const valido = nombreDeTipoSchema.safeParse(nombre)
    if (!valido.success) return setError(valido.error.issues[0].message)
    if (nombresActuales.some((actual) => claveDeNombre(actual) === claveDeNombre(valido.data))) {
      return setError(unidadesText.validation.typeNameDuplicated)
    }
    setError(undefined)

    crear.mutate(valido.data, {
      onSuccess: onCreado,
      onError: (fallo) => {
        const api = toApiError(fallo)
        if (api.isNetworkError) return setProblema('red')
        if (api.status === 422 && api.fieldErrors.nombre?.[0]) return setError(api.fieldErrors.nombre[0])
        setProblema('inesperado')
      },
    })
  }

  return (
    <Modal
      open
      title={text.title}
      onClose={onCerrar}
      busy={crear.isPending}
      footer={
        <>
          <Button variant="secondary" onClick={onCerrar} disabled={crear.isPending}>
            {text.cancel}
          </Button>
          <Button type="submit" form="formulario-tipo-nuevo" loading={crear.isPending}>
            {crear.isPending ? text.saving : text.save}
          </Button>
        </>
      }
    >
      <form id="formulario-tipo-nuevo" onSubmit={guardar} noValidate className="flex flex-col gap-4">
        {problema === 'red' && <Alert>{text.networkError}</Alert>}
        {problema === 'inesperado' && <Alert>{text.unexpectedError}</Alert>}
        <p className="text-sm text-muted">{text.help(nombresActuales[nombresActuales.length - 1] ?? '')}</p>
        <TextField
          id="tipo-nuevo-nombre"
          label={text.name}
          maxLength={MAX_NOMBRE_TIPO}
          value={nombre}
          onChange={(evento) => setNombre(evento.target.value)}
          hint={text.nameCounter(nombre.length)}
          error={error}
        />
      </form>
    </Modal>
  )
}
