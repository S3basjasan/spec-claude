import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { FormularioDeIngreso, type ValoresDeIngreso } from '../components/FormularioDeIngreso'
import { useColaborador } from '../hooks/useColaboradores'
import { useOpcionesDeIngreso, useRegistrarIngreso } from '../hooks/useIngreso'
import { fichasText } from '../text'
import { hoyIso } from '../validation'

const text = fichasText.entry
const RETARDO_BUSQUEDA_MS = 300
const FECHA_VALIDA = /^\d{4}-\d{2}-\d{2}$/

type Problema =
  | { tipo: 'posicion'; mensaje: string }
  | { tipo: 'vinculo'; mensaje: string }
  | { tipo: 'noVerificable' }
  | { tipo: 'claveReutilizada' }
  | { tipo: 'deshabilitado' }
  | { tipo: 'noExiste' }
  | { tipo: 'red' }
  | { tipo: 'inesperado' }
  | null

/** Registrar ingreso o reingreso: `/colaboradores/:colaboradorId/ingreso` (solo Administrador). */
export function RegistrarIngresoPage() {
  const { colaboradorId } = useParams()
  const id = Number(colaboradorId)
  const navigate = useNavigate()
  const ficha = useColaborador(id)

  const [valores, setValores] = useState<ValoresDeIngreso>({ fecha: hoyIso(), contrato: '', posicionId: null, busqueda: '' })
  const [consulta, setConsulta] = useState('')
  const [errores, setErrores] = useState<Record<string, string>>({})
  const [problema, setProblema] = useState<Problema>(null)
  // La clave identifica este formulario abierto: reenviar el mismo contenido no duplica el ingreso.
  const clave = useRef<string>(crypto.randomUUID())
  const registrar = useRegistrarIngreso(id)

  // La búsqueda se escribe sin demora y se aplica a la consulta tras una breve pausa.
  useEffect(() => {
    const espera = setTimeout(() => setConsulta(valores.busqueda.trim()), RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [valores.busqueda])

  const fechaLista = FECHA_VALIDA.test(valores.fecha)
  const opciones = useOpcionesDeIngreso(id, valores.fecha, consulta, fechaLista && Number.isInteger(id))

  // Al cambiar la fecha, una selección que deja de ser elegible se descarta con aviso (sin búsqueda activa, la
  // lista completa de opciones decide si la posición sigue siendo elegible).
  const seDescarta =
    valores.posicionId !== null &&
    opciones.data !== undefined &&
    !opciones.isFetching &&
    consulta === '' &&
    !opciones.data.posiciones.some((posicion) => posicion.id === valores.posicionId)
  const posicionVigente = seDescarta ? null : valores.posicionId

  const cambiar = (cambios: Partial<ValoresDeIngreso>) => {
    setValores((actuales) => ({ ...actuales, ...cambios }))
    if (cambios.fecha !== undefined || cambios.posicionId !== undefined) {
      setProblema(null)
    }
    setErrores((actuales) => {
      const siguientes = { ...actuales }
      if (cambios.fecha !== undefined) delete siguientes.fecha_ingreso
      if (cambios.contrato !== undefined) delete siguientes.tipo_contrato
      if (cambios.posicionId !== undefined) delete siguientes.posicion_id
      return siguientes
    })
  }

  const validar = (): Record<string, string> => {
    const salida: Record<string, string> = {}
    if (!valores.fecha) salida.fecha_ingreso = text.required
    else if (!fechaLista) salida.fecha_ingreso = text.invalidDate
    if (!valores.contrato) salida.tipo_contrato = text.required
    if (posicionVigente === null) salida.posicion_id = text.required
    return salida
  }

  const confirmar = () => {
    setProblema(null)
    const locales = validar()
    setErrores(locales)
    if (Object.keys(locales).length > 0 || posicionVigente === null) return

    registrar.mutate(
      {
        datos: { fecha_ingreso: valores.fecha, tipo_contrato: valores.contrato, posicion_id: posicionVigente },
        clave: clave.current,
      },
      {
        onSuccess: (resultado) =>
          navigate(`/colaboradores/${id}`, {
            state: { aviso: resultado.vinculo.programado ? text.successScheduled : text.success },
          }),
        onError: (error) => {
          const api = toApiError(error)
          const campos: Record<string, string> = {}
          for (const [campo, mensajes] of Object.entries(api.fieldErrors)) {
            if (mensajes[0]) campos[campo] = mensajes[0]
          }
          setErrores(campos)

          if (api.isNetworkError) return setProblema({ tipo: 'red' })
          if (api.code === 'POSICION_NO_DISPONIBLE') return setProblema({ tipo: 'posicion', mensaje: api.message ?? text.positionUnavailable })
          if (api.code === 'VINCULO_SUPERPUESTO') return setProblema({ tipo: 'vinculo', mensaje: api.message ?? '' })
          if (api.code === 'ESTRUCTURA_NO_VERIFICABLE') return setProblema({ tipo: 'noVerificable' })
          if (api.code === 'INGRESOS_DESHABILITADOS') return setProblema({ tipo: 'deshabilitado' })
          if (api.code === 'CLAVE_DE_OPERACION_REUTILIZADA') {
            clave.current = crypto.randomUUID()
            return setProblema({ tipo: 'claveReutilizada' })
          }
          if (api.status === 404) return setProblema({ tipo: 'noExiste' })
          if (api.status === 422 && Object.keys(campos).length > 0) return setProblema(null)
          return setProblema({ tipo: 'inesperado' })
        },
      },
    )
  }

  const reingreso = ficha.data?.laboral.estado === 'Desvinculado'
  const titulo = reingreso ? text.reentryTitle : text.title
  const nombre = ficha.data ? `${ficha.data.nombres} ${ficha.data.apellidos}` : fichasText.detail.loading

  const avisos = (
    <>
      {seDescarta && <Alert tone="neutral">{text.discarded}</Alert>}
      {problema?.tipo === 'posicion' && <Alert>{problema.mensaje}</Alert>}
      {problema?.tipo === 'vinculo' && <Alert>{problema.mensaje}</Alert>}
      {problema?.tipo === 'noVerificable' && <Alert>{text.notVerifiable}</Alert>}
      {problema?.tipo === 'claveReutilizada' && <Alert>{text.keyReused}</Alert>}
      {problema?.tipo === 'deshabilitado' && <Alert>{text.disabled}</Alert>}
      {problema?.tipo === 'noExiste' && <Alert>{text.notFound}</Alert>}
      {problema?.tipo === 'red' && <Alert>{text.network}</Alert>}
      {problema?.tipo === 'inesperado' && <Alert>{text.unexpected}</Alert>}
    </>
  )

  let contenido
  if (ficha.isPending) {
    contenido = (
      <div role="status" aria-label={fichasText.detail.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  } else if (ficha.isError) {
    const noExiste = toApiError(ficha.error).status === 404
    contenido = (
      <div className="rounded-xl border border-border bg-surface">
        <ErrorState
          title={noExiste ? fichasText.detail.notFoundTitle : fichasText.detail.errorTitle}
          description={noExiste ? fichasText.detail.notFoundDescription : fichasText.detail.errorDescription}
          retryLabel={fichasText.retry}
          onRetry={() => void ficha.refetch()}
        />
      </div>
    )
  } else {
    contenido = (
      <FormularioDeIngreso
        valores={{ ...valores, posicionId: posicionVigente }}
        onCambio={cambiar}
        opciones={opciones.data}
        cargandoOpciones={opciones.isFetching && !opciones.data}
        errorDeOpciones={opciones.isError}
        onReintentarOpciones={() => void opciones.refetch()}
        errores={errores}
        avisos={avisos}
        guardando={registrar.isPending}
        onConfirmar={confirmar}
        onCancelar={() => navigate(`/colaboradores/${id}`)}
      />
    )
  }

  return (
    <div className="mx-auto flex max-w-225 flex-col gap-5">
      <Breadcrumb
        label={text.breadcrumbLabel}
        items={[
          { label: fichasText.navLabel, to: '/colaboradores' },
          { label: nombre, to: `/colaboradores/${id}` },
          { label: titulo },
        ]}
      />
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{titulo}</h1>
        <p className="mt-1.5 text-base text-muted">
          {nombre} · {reingreso ? text.reentrySubtitle : text.subtitle}
        </p>
      </div>
      {contenido}
    </div>
  )
}
