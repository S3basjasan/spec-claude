import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconAward } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useCurrentUser } from '../../../auth/login'
import { PestanasCompensaciones } from '../../PestanasCompensaciones'
import { CLAVES, type ClaveParametro, type Parametro } from '../api'
import { TarjetaParametro } from '../components/TarjetaParametro'
import { TarjetaSensibilidad } from '../components/TarjetaSensibilidad'
import { VistaPreviaPuntos } from '../components/VistaPreviaPuntos'
import { primeroDelMesSiguiente } from '../formato'
import { useGuardarParametros } from '../hooks/useGuardarParametros'
import { useParametros } from '../hooks/useParametros'
import { parametrosText } from '../text'
import { cambiosDe, crearEsquema, valoresIniciales, valorSchema, type ValoresFormulario } from '../validation'

const text = parametrosText
const FORM_ID = 'formulario-parametros'

type Aviso = { tipo: 'ok' | 'error' | 'conflicto'; texto: string } | null

/** Parámetros del módulo: solo el Gestor de Talento ve y guarda los seis valores, todos juntos con un único «Guardar». */
export function ParametrosPage() {
  const { data: usuario } = useCurrentUser()
  const esGestor = usuario?.role === 'admin'
  const consulta = useParametros(esGestor)

  let contenido
  if (!esGestor) {
    contenido = <EmptyState icon={<IconAward size={24} />} title={text.title} description={text.states.noPermission} />
  } else if (consulta.isPending) {
    contenido = (
      <div role="status" aria-label={text.states.loading} className="flex flex-col gap-3">
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-40 w-full" />
      </div>
    )
  } else if (consulta.isError) {
    contenido = (
      <ErrorState title={text.states.errorTitle} description={text.states.errorDescription} retryLabel={text.retry} onRetry={() => void consulta.refetch()} />
    )
  } else {
    contenido = <FormularioDeParametros parametros={consulta.data} onRecargar={() => consulta.refetch()} />
  }

  return (
    <PestanasCompensaciones>
      <div className="mx-auto flex max-w-275 flex-col gap-5 pt-5">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
        </div>
        {contenido}
      </div>
    </PestanasCompensaciones>
  )
}

interface FormularioProps {
  parametros: Parametro[]
  onRecargar: () => Promise<unknown>
}

function FormularioDeParametros({ parametros, onRecargar }: FormularioProps) {
  const guardar = useGuardarParametros()
  const [aviso, setAviso] = useState<Aviso>(null)
  const esquema = useMemo(() => crearEsquema(parametros), [parametros])
  const iniciales = useMemo(() => valoresIniciales(parametros), [parametros])

  const {
    register,
    control,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<ValoresFormulario>({ values: iniciales, resolver: zodResolver(esquema) })

  const valores = useWatch({ control })
  const actuales = { ...iniciales, ...valores } as ValoresFormulario
  const hayCambios = cambiosDe(parametros, actuales).length > 0
  const porClave = new Map(parametros.map((p) => [p.clave, p]))

  const descartar = () => {
    reset(iniciales)
    setAviso(null)
  }

  const recargar = async () => {
    await onRecargar()
    setAviso(null)
  }

  const alGuardar = handleSubmit((datos) => {
    const cambios = cambiosDe(parametros, datos)
    if (cambios.length === 0) {
      setAviso({ tipo: 'ok', texto: text.states.unchanged })
      return
    }
    setAviso(null)
    guardar.mutate(cambios, {
      onSuccess: () => setAviso({ tipo: 'ok', texto: text.states.saved }),
      onError: (error) => {
        const api = toApiError(error)
        if (api.status === 409) {
          setAviso({ tipo: 'conflicto', texto: text.states.conflict })
          return
        }
        if (api.status === 422) {
          // Los errores llegan como `cambios.N.valor`: N es la posición del cambio enviado.
          for (const [campo, mensajes] of Object.entries(api.fieldErrors)) {
            const indice = Number(/^cambios\.(\d+)\./.exec(campo)?.[1])
            const clave = cambios[indice]?.clave
            if (clave && mensajes[0]) setError(clave, { type: 'server', message: mensajes[0] })
          }
        }
        setAviso({ tipo: 'error', texto: text.states.saveError })
      },
    })
  })

  const numericos = CLAVES.filter((clave): clave is Exclude<ClaveParametro, 'sensibilidad_radar'> => clave !== 'sensibilidad_radar')
  const puntos = porClave.get('puntos_por_colaborador')
  const textoPuntos = actuales.puntos_por_colaborador
  const puntosValidos = puntos ? valorSchema(puntos.minimo, puntos.maximo).safeParse(textoPuntos).success : false

  return (
    <form id={FORM_ID} onSubmit={alGuardar} noValidate className="flex flex-col gap-4">
      <div role="status" aria-live="polite">
        {aviso?.tipo === 'ok' && <Alert tone="neutral">{aviso.texto}</Alert>}
      </div>
      {aviso?.tipo === 'error' && <Alert>{aviso.texto}</Alert>}
      {aviso?.tipo === 'conflicto' && (
        <Alert>
          <span className="flex flex-wrap items-center justify-between gap-3">
            <span>{aviso.texto}</span>
            <Button type="button" variant="secondary" onClick={() => void recargar()}>
              {text.reload}
            </Button>
          </span>
        </Alert>
      )}

      {numericos.map((clave) => {
        const parametro = porClave.get(clave)
        if (!parametro) return null
        return (
          <TarjetaParametro key={clave} parametro={{ ...parametro, clave }} registro={register(clave)} error={errors[clave]?.message}>
            {clave === 'puntos_por_colaborador' && (
              <VistaPreviaPuntos
                actual={parametro.valor}
                nuevo={puntosValidos ? Number(textoPuntos.trim()) : null}
                vigenteDesde={parametro.valor_programado?.vigente_desde ?? primeroDelMesSiguiente()}
              />
            )}
          </TarjetaParametro>
        )
      })}

      {porClave.get('sensibilidad_radar') && (
        <TarjetaSensibilidad
          parametro={porClave.get('sensibilidad_radar') as Parametro}
          registro={register('sensibilidad_radar')}
          error={errors.sensibilidad_radar?.message}
        />
      )}

      <div className="flex justify-end gap-3">
        <Button type="button" variant="secondary" onClick={descartar} disabled={!hayCambios || guardar.isPending}>
          {text.discard}
        </Button>
        <Button type="submit" loading={guardar.isPending} disabled={!hayCambios}>
          {guardar.isPending ? text.saving : text.save}
        </Button>
      </div>
    </form>
  )
}
