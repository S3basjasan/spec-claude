import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Combobox } from '../../../../shared/ui/Combobox'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconX } from '../../../../shared/ui/icons'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextField } from '../../../../shared/ui/TextField'
import { MAX_TITULO, TIPOS_MICRO, type CompetenciaMicro, type Micro, type OpcionesMicro, type PreguntaBorrador, type TipoMicro } from '../api'
import { useCambiarEstado, useGuardarMicro } from '../hooks/useMutacionesMicro'
import { useMicro, useOpcionesMicro } from '../hooks/useMicros'
import { microText } from '../text'
import { duracionDe, validarMicro, type BorradorMicro, type ErroresMicro } from '../validation'
import { AvisoConocimientos } from './AvisoConocimientos'
import { ConstructorDeQuiz } from './ConstructorDeQuiz'
import { VistaPreviaMicro } from './VistaPreviaMicro'

const text = microText.form

interface FormularioMicroProps {
  /** Id del contenido a editar; `null` para crear uno nuevo. */
  id: number | null
  onCerrar: () => void
}

/** Convierte el detalle guardado en el borrador editable del formulario. */
function borradorDe(micro: Micro | null): BorradorMicro {
  return {
    titulo: micro?.titulo ?? '',
    tipo: micro?.tipo ?? 'Video',
    duracion: micro ? String(micro.duracion_minutos) : '',
    url: micro?.url ?? '',
    miniatura_url: micro?.miniatura_url ?? '',
    competencia_ids: micro?.competencias.map((c) => c.id) ?? [],
    preguntas: (micro?.preguntas ?? []).map((p) => ({
      enunciado: p.enunciado,
      opciones: p.opciones.map((o) => ({ texto: o.texto, es_correcta: o.es_correcta === true })),
    })),
  }
}

/** Micro-contenido provisional para la vista previa de lo que aún no se guarda. */
function microDesdeBorrador(b: BorradorMicro, competencias: CompetenciaMicro[]): Micro {
  const tipo = (TIPOS_MICRO.includes(b.tipo as TipoMicro) ? b.tipo : 'Video') as TipoMicro
  const minutos = duracionDe(b.duracion)
  return {
    id: 0,
    codigo: '',
    titulo: b.titulo,
    tipo,
    estado: 'Borrador',
    url: b.url.trim() === '' ? null : b.url.trim(),
    miniatura_url: null,
    duracion_minutos: Number.isNaN(minutos) || minutos < 1 ? 1 : minutos,
    competencias: b.competencia_ids.map((id) => competencias.find((c) => c.id === id)).filter((c): c is CompetenciaMicro => c !== undefined),
    apto_conocimientos: true,
    preguntas: b.preguntas.map((p: PreguntaBorrador, i) => ({
      id: i + 1,
      enunciado: p.enunciado,
      orden: i + 1,
      opciones: p.opciones.map((o, j) => ({ id: (i + 1) * 100 + j + 1, texto: o.texto, orden: j + 1, es_correcta: o.es_correcta })),
    })),
  }
}

/** Errores del servidor por campo; los de `competencia_ids.N` se muestran en el selector. */
function erroresDelServidor(error: unknown): { campos: ErroresMicro; general?: string; faltantes: string[] } {
  const api = toApiError(error)
  const campos: ErroresMicro = {}
  for (const [clave, mensajes] of Object.entries(api.fieldErrors)) {
    const destino = clave.startsWith('competencia_ids') ? 'competencia_ids' : clave
    if (mensajes[0] && !(destino in campos)) campos[destino] = mensajes[0]
  }
  const faltantes = Array.isArray(api.data?.faltantes) ? (api.data.faltantes as string[]) : []
  const sinCampo = Object.keys(campos).length === 0
  return { campos, general: sinCampo || api.code === 'quiz_con_intentos' ? (api.message ?? text.saveFailed) : undefined, faltantes }
}

interface CamposProps {
  inicial: Micro | null
  opciones: OpcionesMicro
  onCerrar: () => void
}

function Campos({ inicial, opciones, onCerrar }: CamposProps) {
  const [borrador, setBorrador] = useState<BorradorMicro>(() => borradorDe(inicial))
  const [errores, setErrores] = useState<ErroresMicro>({})
  const [general, setGeneral] = useState<string>()
  const [faltantes, setFaltantes] = useState<string[]>([])
  const [vistaPrevia, setVistaPrevia] = useState<Micro | null>(null)
  const guardar = useGuardarMicro(inicial?.id ?? null)
  const cambiarEstado = useCambiarEstado()

  const editando = inicial !== null
  const esQuiz = borrador.tipo === 'Quiz'
  const ocupado = guardar.isPending || cambiarEstado.isPending
  const publicado = inicial?.estado === 'Publicado'
  const bloqueado = inicial?.tiene_intentos === true
  const conocidas = [...opciones.competencias, ...(inicial?.competencias ?? [])]
  const nombreDe = (id: number) => conocidas.find((c) => c.id === id)
  const disponibles = opciones.competencias_activas.filter((c) => !borrador.competencia_ids.includes(c.id))

  const cambiar = (cambios: Partial<BorradorMicro>) => setBorrador((actual) => ({ ...actual, ...cambios }))

  const enviar = async (publicar: boolean) => {
    setGeneral(undefined)
    setFaltantes([])
    // Un contenido ya publicado debe seguir cumpliendo los requisitos de publicación al editarlo.
    const resultado = validarMicro(borrador, publicar || publicado)
    if ('errores' in resultado) {
      setErrores(resultado.errores)
      return
    }
    setErrores({})

    try {
      const micro = await guardar.mutateAsync(resultado.datos)
      if (publicar) await cambiarEstado.mutateAsync({ id: micro.id, estado: 'Publicado' })
      onCerrar()
    } catch (e) {
      const servidor = erroresDelServidor(e)
      setErrores(servidor.campos)
      setFaltantes(servidor.faltantes)
      setGeneral(servidor.general ?? (Object.keys(servidor.campos).length === 0 ? text.saveFailed : undefined))
    }
  }

  return (
    <Modal
      open
      title={editando ? text.editTitle : text.createTitle}
      onClose={onCerrar}
      size="lg"
      busy={ocupado}
      footer={
        <>
          <Button variant="secondary" onClick={() => setVistaPrevia(microDesdeBorrador(borrador, conocidas))} disabled={ocupado}>
            {text.preview}
          </Button>
          <Button variant="secondary" onClick={onCerrar} disabled={ocupado}>
            {text.cancel}
          </Button>
          <Button variant={editando && inicial?.estado !== 'Borrador' ? 'primary' : 'secondary'} onClick={() => void enviar(false)} loading={guardar.isPending && !cambiarEstado.isPending} disabled={ocupado}>
            {editando ? text.saveChanges : text.saveDraft}
          </Button>
          {(!editando || inicial?.estado === 'Borrador') && (
            <Button onClick={() => void enviar(true)} loading={cambiarEstado.isPending} disabled={ocupado}>
              {text.publish}
            </Button>
          )}
        </>
      }
    >
      <form
        className="flex flex-col gap-4"
        noValidate
        onSubmit={(evento) => {
          evento.preventDefault()
          void enviar(false)
        }}
      >
        {general && <Alert>{general}</Alert>}
        {faltantes.length > 0 && (
          <Alert>
            {microText.card.missing} {faltantes.map((f) => microText.card.missingItems[f] ?? f).join(', ')}.
          </Alert>
        )}
        <AvisoConocimientos />

        {inicial && (
          <p className="text-sm text-muted">
            {text.codeLabel}: <span className="font-semibold text-text">{inicial.codigo}</span>
          </p>
        )}

        <TextField
          id="micro-titulo"
          label={text.fields.title}
          value={borrador.titulo}
          maxLength={MAX_TITULO}
          error={errores.titulo}
          onChange={(evento) => cambiar({ titulo: evento.target.value })}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField
            id="micro-tipo"
            label={text.fields.type}
            value={borrador.tipo}
            disabled={editando}
            error={errores.tipo}
            onChange={(evento) => cambiar({ tipo: evento.target.value })}
            options={TIPOS_MICRO.map((t) => ({ value: t, label: microText.tipo[t] }))}
          />
          <TextField
            id="micro-duracion"
            label={text.fields.duration}
            type="number"
            inputMode="numeric"
            min={1}
            value={borrador.duracion}
            error={errores.duracion_minutos}
            onChange={(evento) => cambiar({ duracion: evento.target.value })}
          />
        </div>
        {editando && <p className="-mt-2 text-[13px] text-muted">{text.typeLocked}</p>}

        <div className="flex flex-col gap-2">
          <Combobox
            id="micro-competencias"
            label={text.fields.competences}
            options={disponibles.map((c) => ({ value: String(c.id), label: `${c.codigo} · ${c.nombre}` }))}
            value={null}
            placeholder={text.fields.competencePlaceholder}
            error={errores.competencia_ids}
            onChange={(valor) => {
              if (valor !== null) cambiar({ competencia_ids: [...borrador.competencia_ids, Number(valor)] })
            }}
          />
          {borrador.competencia_ids.length > 0 && (
            <ul className="flex flex-wrap gap-2" aria-label={text.fields.competences}>
              {borrador.competencia_ids.map((competenciaId) => {
                const c = nombreDe(competenciaId)
                const nombre = c?.nombre ?? `#${competenciaId}`
                return (
                  <li key={competenciaId} className="flex items-center gap-1 rounded-full bg-soft py-0.5 pl-3 pr-1 text-[13px] font-semibold text-accent">
                    <span>
                      {nombre}
                      {c?.estado === 'Inactivo' && <span className="ml-1 font-normal text-muted">({text.inactiveCompetence})</span>}
                    </span>
                    <button
                      type="button"
                      aria-label={text.removeCompetence(nombre)}
                      className="inline-flex size-8 items-center justify-center rounded-full hover:bg-surface"
                      onClick={() => cambiar({ competencia_ids: borrador.competencia_ids.filter((x) => x !== competenciaId) })}
                    >
                      <IconX size={14} />
                    </button>
                  </li>
                )
              })}
            </ul>
          )}
        </div>

        {esQuiz ? (
          <ConstructorDeQuiz preguntas={borrador.preguntas} onChange={(preguntas) => cambiar({ preguntas })} errores={errores} bloqueado={bloqueado} />
        ) : (
          <TextField
            id="micro-url"
            label={text.fields.url}
            type="url"
            inputMode="url"
            placeholder="https://"
            value={borrador.url}
            error={errores.url}
            onChange={(evento) => cambiar({ url: evento.target.value })}
          />
        )}

        <TextField
          id="micro-miniatura"
          label={text.fields.thumbnail}
          type="url"
          inputMode="url"
          placeholder="https://"
          value={borrador.miniatura_url}
          error={errores.miniatura_url}
          onChange={(evento) => cambiar({ miniatura_url: evento.target.value })}
        />
      </form>

      {vistaPrevia && <VistaPreviaMicro micro={vistaPrevia} onCerrar={() => setVistaPrevia(null)} />}
    </Modal>
  )
}

/** Formulario de creación y edición (Modal): carga el detalle y las competencias y luego muestra los campos. */
export function FormularioMicro({ id, onCerrar }: FormularioMicroProps) {
  const detalle = useMicro(id, true)
  const opciones = useOpcionesMicro()

  if (opciones.isError || (id !== null && detalle.isError)) {
    return (
      <Modal open title={id === null ? text.createTitle : text.editTitle} onClose={onCerrar}>
        <ErrorState
          title={text.loadFailed}
          description={microText.errorDescription}
          retryLabel={microText.retry}
          onRetry={() => {
            void opciones.refetch()
            if (id !== null) void detalle.refetch()
          }}
        />
      </Modal>
    )
  }

  if (opciones.isPending || (id !== null && detalle.isPending)) {
    return (
      <Modal open title={id === null ? text.createTitle : text.editTitle} onClose={onCerrar}>
        <div role="status" aria-label={microText.loadingLabel} className="flex flex-col gap-3">
          <Skeleton className="h-11" />
          <Skeleton className="h-11" />
          <Skeleton className="h-24" />
        </div>
      </Modal>
    )
  }

  return <Campos key={id ?? 'nuevo'} inicial={id === null ? null : (detalle.data ?? null)} opciones={opciones.data} onCerrar={onCerrar} />
}
