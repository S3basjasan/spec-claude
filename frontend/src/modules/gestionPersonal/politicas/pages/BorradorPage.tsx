import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconFileText, IconPlus } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TIPOS, type Definicion, type TipoPolitica, type Version } from '../api'
import { CondicionesFijas } from '../components/CondicionesFijas'
import { DialogoDePublicacion } from '../components/DialogoDePublicacion'
import { FormularioDeParametros } from '../components/FormularioDeParametros'
import { useCrearBorrador, useDescartarBorrador, useEditarBorrador } from '../hooks/useGuardarPolitica'
import { usePolitica } from '../hooks/usePoliticas'
import { politicasText } from '../text'
import {
  descripcionError,
  erroresDeParametros,
  LIMITES,
  parametrosEnEdicion,
  parametrosParaEnviar,
  problemaDeGuardado,
  type AvisoDeGuardado,
  type ParametrosEnEdicion,
} from '../validation'

const text = politicasText.editor

const AVISOS: Partial<Record<Exclude<AvisoDeGuardado, null>, string>> = {
  noExiste: text.notFound,
  deshabilitada: text.writeDisabled,
  red: text.network,
  inesperado: text.unexpected,
}

interface EditorProps {
  tipo: TipoPolitica
  definicion: Definicion
  inicial: Version
  /** Vuelve a consultar la política; el editor se reinicia con el borrador vigente. */
  onRecargar: () => void
}

/** Edición de un borrador: los cambios pendientes se conservan ante un conflicto hasta que se elija recargar. */
function EditorDeBorrador({ tipo, definicion, inicial, onRecargar }: EditorProps) {
  const navigate = useNavigate()
  const editar = useEditarBorrador(tipo)
  const descartar = useDescartarBorrador(tipo)
  const [guardada, setGuardada] = useState<Version>(inicial)
  const [descripcion, setDescripcion] = useState(inicial.descripcion)
  const [valores, setValores] = useState<ParametrosEnEdicion>(() => parametrosEnEdicion(inicial.parametros))
  const [errores, setErrores] = useState<Record<string, string>>({})
  const [aviso, setAviso] = useState<AvisoDeGuardado>(null)
  const [conflicto, setConflicto] = useState(false)
  const [mensaje, setMensaje] = useState<string | null>(null)
  const [dialogo, setDialogo] = useState<'publicar' | 'descartar' | null>(null)
  const [errorDeDescarte, setErrorDeDescarte] = useState<string | undefined>(undefined)
  const [ultimo, setUltimo] = useState(() => JSON.stringify({ d: inicial.descripcion, p: inicial.parametros }))

  const sucio = JSON.stringify({ d: descripcion, p: parametrosParaEnviar(definicion, valores) }) !== ultimo

  /** Valida en el cliente y guarda; devuelve si el borrador quedó guardado con la versión vigente. */
  const guardar = async (): Promise<boolean> => {
    if (editar.isPending) return false
    setAviso(null)
    setConflicto(false)
    setMensaje(null)

    const locales: Record<string, string> = {}
    const errorDescripcion = descripcionError(descripcion)
    if (errorDescripcion) locales.descripcion = errorDescripcion
    Object.assign(locales, erroresDeParametros(definicion, valores))
    setErrores(locales)
    if (Object.keys(locales).length > 0) return false

    const parametros = parametrosParaEnviar(definicion, valores)
    try {
      const version = await editar.mutateAsync({ id: guardada.id, cambios: { version: guardada.version, descripcion: descripcion.trim(), parametros } })
      setGuardada(version)
      setUltimo(JSON.stringify({ d: descripcion.trim(), p: parametros }))
      setMensaje(text.saved)
      return true
    } catch (error) {
      const problema = problemaDeGuardado(error)
      setErrores(problema.campos)
      setConflicto(problema.aviso === 'conflicto')
      setAviso(problema.aviso === 'conflicto' ? null : problema.aviso)
      return false
    }
  }

  const revisarYPublicar = async () => {
    if (!sucio || (await guardar())) setDialogo('publicar')
  }

  const confirmarDescarte = () => {
    setErrorDeDescarte(undefined)
    descartar.mutate(
      { id: guardada.id, version: guardada.version },
      {
        onSuccess: () => void navigate(`/politicas-personal/${tipo}`, { state: { aviso: text.discarded } }),
        onError: (error) => {
          const problema = problemaDeGuardado(error)
          setErrorDeDescarte(problema.aviso === 'conflicto' ? text.conflict : (AVISOS[problema.aviso ?? 'inesperado'] ?? text.unexpected))
        },
      },
    )
  }

  const bloqueado = editar.isPending

  return (
    <div className="mx-auto flex max-w-225 flex-col gap-5">
      <Breadcrumb
        label={politicasText.detail.breadcrumbLabel}
        items={[
          { label: politicasText.navLabel, to: '/politicas-personal' },
          { label: politicasText.tipos[tipo], to: `/politicas-personal/${tipo}` },
          { label: politicasText.estados.Borrador },
        ]}
      />
      <div>
        <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title(politicasText.tipos[tipo])}</h1>
        <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
      </div>

      {conflicto && (
        <Alert>
          <p className="font-semibold">{text.conflictTitle}</p>
          <p>{text.conflict}</p>
          <Button variant="secondary" className="mt-2" onClick={onRecargar}>
            {text.reload}
          </Button>
        </Alert>
      )}
      {aviso !== null && AVISOS[aviso] && <Alert>{AVISOS[aviso]}</Alert>}
      {mensaje && (
        <p role="status" className="rounded-lg bg-soft px-3.5 py-3 text-sm font-semibold text-accent">
          {mensaje}
        </p>
      )}

      <section className="flex flex-col gap-5 rounded-xl border border-border bg-surface p-5">
        <TextareaField
          id="borrador-descripcion"
          label={text.descriptionLabel}
          hint={text.descriptionHint}
          value={descripcion}
          onChange={(e) => setDescripcion(e.target.value)}
          currentLength={descripcion.length}
          maxLength={LIMITES.descripcion}
          error={errores.descripcion}
          disabled={bloqueado}
        />
        <div className="flex flex-col gap-3">
          <h2 className="text-lg font-bold">{text.parametersTitle}</h2>
          <FormularioDeParametros id="borrador" definicion={definicion} valores={valores} onChange={setValores} errores={errores} disabled={bloqueado} />
        </div>
      </section>

      <CondicionesFijas reglas={definicion.reglas_fijas} />

      <div className="flex flex-wrap items-center gap-3">
        <Button variant="secondary" onClick={() => void guardar()} loading={editar.isPending}>
          {editar.isPending ? text.saving : text.save}
        </Button>
        <Button onClick={() => void revisarYPublicar()} disabled={bloqueado}>
          {text.reviewAndPublish}
        </Button>
        <Button variant="secondary" onClick={() => setDialogo('descartar')} disabled={bloqueado}>
          {text.discard}
        </Button>
        {sucio && <span className="text-sm text-muted">{text.unsaved}</span>}
      </div>

      {dialogo === 'publicar' && (
        <DialogoDePublicacion
          tipo={tipo}
          borrador={guardada}
          onCerrar={() => setDialogo(null)}
          onPublicada={(version) => void navigate(`/politicas-personal/${tipo}`, { state: { aviso: politicasText.detail.publishedNotice(version.numero) } })}
        />
      )}
      <ConfirmDialog
        open={dialogo === 'descartar'}
        title={text.discardTitle}
        description={text.discardDescription}
        confirmLabel={text.discardConfirm}
        cancelLabel={text.discardCancel}
        onConfirm={confirmarDescarte}
        onCancel={() => setDialogo(null)}
        busy={descartar.isPending}
        error={errorDeDescarte}
      />
    </div>
  )
}

/** Editor de borrador y publicación (ruta del Administrador). */
export function BorradorPage() {
  const { tipo: tipoParam } = useParams()
  const tipo = TIPOS.find((t) => t === tipoParam) ?? null
  const navigate = useNavigate()
  const consulta = usePolitica(tipo)
  const crear = useCrearBorrador((tipo ?? 'permisos') as TipoPolitica)
  const [recarga, setRecarga] = useState(0)
  const [errorCrear, setErrorCrear] = useState<string | null>(null)

  if (tipo === null) {
    return (
      <div className="mx-auto max-w-225 rounded-xl border border-border bg-surface">
        <ErrorState
          title={politicasText.detail.notFoundTitle}
          description={politicasText.detail.notFoundDescription}
          retryLabel={politicasText.detail.backToList}
          onRetry={() => void navigate('/politicas-personal')}
        />
      </div>
    )
  }

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={politicasText.detail.loading} className="mx-auto flex max-w-225 flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  }

  if (consulta.isError) {
    return (
      <div className="mx-auto max-w-225 rounded-xl border border-border bg-surface">
        <ErrorState
          title={politicasText.detail.errorTitle}
          description={politicasText.detail.errorDescription}
          retryLabel={politicasText.retry}
          onRetry={() => void consulta.refetch()}
        />
      </div>
    )
  }

  const { borrador, definicion } = consulta.data

  if (borrador === null) {
    return (
      <div className="mx-auto flex max-w-225 flex-col gap-3 rounded-xl border border-border bg-surface">
        <EmptyState
          icon={<IconFileText size={24} />}
          title={text.noDraftTitle}
          description={text.noDraftDescription}
          action={
            <Button
              loading={crear.isPending}
              onClick={() => {
                setErrorCrear(null)
                crear.mutate(undefined, {
                  onSuccess: () => setRecarga((n) => n + 1),
                  onError: (fallo) => setErrorCrear(problemaDeGuardado(fallo).aviso === 'deshabilitada' ? text.writeDisabled : politicasText.detail.createFailed),
                })
              }}
            >
              <IconPlus size={18} />
              {politicasText.detail.createDraft}
            </Button>
          }
        />
        {errorCrear && <Alert>{errorCrear}</Alert>}
        <p className="pb-6 text-center">
          <Link to={`/politicas-personal/${tipo}`} className="font-semibold text-accent underline">
            {text.back}
          </Link>
        </p>
      </div>
    )
  }

  return (
    <EditorDeBorrador
      key={`${borrador.id}-${recarga}`}
      tipo={tipo}
      definicion={definicion}
      inicial={borrador}
      onRecargar={() => {
        void consulta.refetch().then(() => setRecarga((n) => n + 1))
      }}
    />
  )
}
