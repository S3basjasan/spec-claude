import { useState, type FormEvent } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import type { Inscripcion, RutaDetalle } from '../api'
import { useCambiarNiveles } from '../hooks/useMutacionesRutas'
import { rutasText } from '../text'

const t = rutasText.niveles

interface CambiarNivelesModalProps {
  inscripcion: Inscripcion
  ruta: RutaDetalle
  onCerrar: () => void
  onGuardado: (mensaje: string) => void
}

/** Ajusta el nivel actual y el nivel objetivo de una inscripción Activa. Se monta al abrirse para partir de los valores vigentes. */
export function CambiarNivelesModal({ inscripcion, ruta, onCerrar, onGuardado }: CambiarNivelesModalProps) {
  const cambiar = useCambiarNiveles(ruta.id)
  const [actual, setActual] = useState(inscripcion.nivel_actual === null ? '' : String(inscripcion.nivel_actual.id))
  const [objetivo, setObjetivo] = useState(inscripcion.nivel_objetivo === null ? '' : String(inscripcion.nivel_objetivo.id))
  const [errorObjetivo, setErrorObjetivo] = useState<string | undefined>()
  const [errorActual, setErrorActual] = useState<string | undefined>()
  const [errorGeneral, setErrorGeneral] = useState<string | undefined>()

  const opciones = [{ value: '', label: t.sinNivel }, ...ruta.niveles.map((n) => ({ value: String(n.id), label: `${rutasText.escalera.nivel(n.orden)} · ${n.nombre}` }))]
  const nombre = inscripcion.colaborador.nombre

  const enviar = (evento: FormEvent) => {
    evento.preventDefault()
    if (cambiar.isPending) return
    setErrorActual(undefined)
    setErrorObjetivo(undefined)
    setErrorGeneral(undefined)

    const ordenDe = (id: string) => ruta.niveles.find((n) => String(n.id) === id)?.orden
    const a = ordenDe(actual)
    const o = ordenDe(objetivo)
    if (a !== undefined && o !== undefined && o <= a) {
      setErrorObjetivo(t.objetivoPosterior)
      return
    }

    cambiar.mutate(
      { id: inscripcion.id, actual: actual === '' ? null : Number(actual), objetivo: objetivo === '' ? null : Number(objetivo) },
      {
        onSuccess: () => onGuardado(t.exito(nombre)),
        onError: (error) => {
          const e = toApiError(error)
          const campo = (clave: string) => e.fieldErrors[clave]?.[0]
          setErrorActual(campo('nivel_actual_id'))
          setErrorObjetivo(campo('nivel_objetivo_id'))
          setErrorGeneral(campo('nivel_actual_id') || campo('nivel_objetivo_id') ? undefined : (e.message ?? t.errorGuardar))
        },
      },
    )
  }

  return (
    <Modal open title={t.titulo} onClose={onCerrar} description={t.descripcion(nombre)} closeLabel={rutasText.cerrar} busy={cambiar.isPending}>
      <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
        {errorGeneral && <Alert>{errorGeneral}</Alert>}
        <SelectField id="niveles-actual" label={t.actual} value={actual} onChange={(e) => setActual(e.target.value)} error={errorActual} options={opciones} />
        <SelectField id="niveles-objetivo" label={t.objetivo} value={objetivo} onChange={(e) => setObjetivo(e.target.value)} error={errorObjetivo} options={opciones} />
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
          <Button variant="secondary" className="border-transparent" onClick={onCerrar} disabled={cambiar.isPending}>
            {rutasText.cancelar}
          </Button>
          <Button type="submit" loading={cambiar.isPending}>
            {t.guardar}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
