import { useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { ColaboradorDetalle } from '../api'
import { AlertaDuplicada } from '../components/AlertaDuplicada'
import { FichaFormulario } from '../components/FichaFormulario'
import { useActualizarColaborador, useCrearColaborador } from '../hooks/useGuardarFicha'
import { useColaborador } from '../hooks/useColaboradores'
import { fichasText } from '../text'
import { cambiosDe, datosDeFormulario, valoresDeFicha, valoresVacios, type FichaFormValues } from '../validation'

const text = fichasText.form

type Problema =
  | { tipo: 'duplicada'; fichaId: number | null }
  | { tipo: 'conflicto' }
  | { tipo: 'claveReutilizada' }
  | { tipo: 'deshabilitada' }
  | { tipo: 'noExiste' }
  | { tipo: 'red' }
  | { tipo: 'inesperado' }
  | null

const CAMPOS = new Set(Object.keys(valoresVacios()))

function erroresPorCampo(campos: Record<string, string[]>): Record<string, string> {
  const salida: Record<string, string> = {}
  for (const [campo, mensajes] of Object.entries(campos)) {
    if (CAMPOS.has(campo) && mensajes[0]) salida[campo] = mensajes[0]
  }
  return salida
}

function fichaExistenteDe(datos: Record<string, unknown> | undefined): number | null {
  const existente = datos?.ficha_existente
  if (existente !== null && typeof existente === 'object' && 'id' in existente) {
    const id = Number((existente as { id: unknown }).id)
    return Number.isInteger(id) ? id : null
  }
  return null
}

interface FormularioProps {
  ficha?: ColaboradorDetalle
  onRecargar?: () => void
}

function FichaForm({ ficha, onRecargar }: FormularioProps) {
  const navigate = useNavigate()
  const editando = ficha !== undefined
  const crear = useCrearColaborador()
  const actualizar = useActualizarColaborador(ficha?.id ?? 0, ficha?.version ?? 0)
  const guardando = crear.isPending || actualizar.isPending
  const [problema, setProblema] = useState<Problema>(null)
  const [errores, setErrores] = useState<Record<string, string>>({})
  // La clave identifica este formulario abierto: un reenvío con el mismo contenido no duplica la ficha.
  const clave = useRef<string>(crypto.randomUUID())

  const volver = () => navigate(editando ? `/colaboradores/${ficha.id}` : '/colaboradores')

  const manejarError = (error: unknown) => {
    const api = toApiError(error)
    setErrores(erroresPorCampo(api.fieldErrors))
    if (api.isNetworkError) return setProblema({ tipo: 'red' })
    if (api.code === 'IDENTIFICACION_DUPLICADA') return setProblema({ tipo: 'duplicada', fichaId: fichaExistenteDe(api.data) })
    if (api.code === 'CLAVE_DE_OPERACION_REUTILIZADA') {
      clave.current = crypto.randomUUID()
      return setProblema({ tipo: 'claveReutilizada' })
    }
    if (api.code === 'ESCRITURA_DESHABILITADA') return setProblema({ tipo: 'deshabilitada' })
    if (api.status === 409) return setProblema({ tipo: 'conflicto' })
    if (api.status === 404) return setProblema({ tipo: 'noExiste' })
    if (api.status === 422 && Object.keys(api.fieldErrors).length > 0) return setProblema(null)
    return setProblema({ tipo: 'inesperado' })
  }

  const guardar = (valores: FichaFormValues) => {
    setProblema(null)
    setErrores({})
    const datos = datosDeFormulario(valores)

    if (!editando) {
      crear.mutate(
        { datos, clave: clave.current },
        {
          onSuccess: (creada) => navigate(`/colaboradores/${creada.id}`, { state: { aviso: fichasText.list.created } }),
          onError: manejarError,
        },
      )
      return
    }

    actualizar.mutate(cambiosDe(ficha, datos), {
      onSuccess: (guardada) => navigate(`/colaboradores/${guardada.id}`, { state: { aviso: fichasText.list.updated } }),
      onError: manejarError,
    })
  }

  const avisos = (
    <>
      {problema?.tipo === 'duplicada' && <AlertaDuplicada fichaExistenteId={problema.fichaId} />}
      {problema?.tipo === 'conflicto' && (
        <Alert>
          <p className="font-semibold">{text.conflictTitle}</p>
          <p>{text.conflict}</p>
          {onRecargar && (
            <Button
              variant="secondary"
              className="mt-2"
              onClick={() => {
                setProblema(null)
                onRecargar()
              }}
            >
              {text.reload}
            </Button>
          )}
        </Alert>
      )}
      {problema?.tipo === 'claveReutilizada' && <Alert>{text.keyReused}</Alert>}
      {problema?.tipo === 'deshabilitada' && <Alert>{text.writeDisabled}</Alert>}
      {problema?.tipo === 'noExiste' && <Alert>{text.notFound}</Alert>}
      {problema?.tipo === 'red' && <Alert>{text.network}</Alert>}
      {problema?.tipo === 'inesperado' && <Alert>{text.unexpected}</Alert>}
    </>
  )

  return (
    <FichaFormulario
      valoresIniciales={ficha ? valoresDeFicha(ficha) : valoresVacios()}
      codigo={ficha?.codigo}
      guardando={guardando}
      erroresServidor={errores}
      avisos={avisos}
      onGuardar={guardar}
      onCancelar={volver}
    />
  )
}

/** Crear (`/colaboradores/nuevo`) y editar (`/colaboradores/:colaboradorId/editar`) comparten formulario. */
export function FichaFormPage() {
  const { colaboradorId } = useParams()
  const id = colaboradorId === undefined ? null : Number(colaboradorId)
  const editando = id !== null
  const consulta = useColaborador(id ?? 0, true)

  const migas = [
    { label: fichasText.navLabel, to: '/colaboradores' },
    { label: editando ? text.editTitle : text.createTitle },
  ]

  let contenido
  if (!editando) {
    contenido = <FichaForm />
  } else if (consulta.isPending) {
    contenido = (
      <div role="status" aria-label={text.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2, 3].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  } else if (consulta.isError) {
    const noExiste = toApiError(consulta.error).status === 404
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        <ErrorState
          title={noExiste ? fichasText.detail.notFoundTitle : text.errorTitle}
          description={noExiste ? fichasText.detail.notFoundDescription : fichasText.detail.errorDescription}
          retryLabel={fichasText.retry}
          onRetry={() => void consulta.refetch()}
        />
      </div>
    )
  } else {
    // La clave del formulario es el id: al recargar tras un conflicto, lo escrito se conserva y solo cambia la versión.
    contenido = <FichaForm key={consulta.data.id} ficha={consulta.data} onRecargar={() => void consulta.refetch()} />
  }

  return (
    <div className="mx-auto flex max-w-225 flex-col gap-5">
      <Breadcrumb items={migas} label={text.breadcrumbLabel} />
      <h1 className="text-[28px] font-bold leading-tight tracking-tight">{editando ? text.editTitle : text.createTitle}</h1>
      {contenido}
    </div>
  )
}
