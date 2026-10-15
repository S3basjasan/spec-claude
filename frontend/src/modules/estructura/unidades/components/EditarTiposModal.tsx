import { useState, type DragEvent } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconArrowDown, IconArrowUp, IconGripVertical, IconLock } from '../../../../shared/ui/icons'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextField } from '../../../../shared/ui/TextField'
import { MAX_NOMBRE_TIPO, unidadesEnConflictoDe, type BorradorDeTipo, type CadenaDeTipos, type UnidadEnConflicto } from '../api'
import { useCadenaFresca, useGuardarCadena } from '../hooks/useTiposUnidad'
import { unidadesText } from '../text'
import { claveDeNombre } from '../validation'
import { BloqueoDeCadenaModal } from './BloqueoDeCadenaModal'
import { VistaPreviaDeCadena } from './VistaPreviaDeCadena'

const text = unidadesText.editTypes

interface EditarTiposModalProps {
  onCerrar: () => void
  onGuardada: () => void
  /** "Ver en el árbol": el llamador cierra los modales y busca la unidad por su código. */
  onVerEnArbol: (codigo: string) => void
}

/** Modal "Editar tipos": carga la cadena con una lectura nueva y la edita sobre un borrador. */
export function EditarTiposModal({ onCerrar, onGuardada, onVerEnArbol }: EditarTiposModalProps) {
  const cadena = useCadenaFresca()
  const [recarga, setRecarga] = useState(0)

  if (cadena.isError) {
    return (
      <Modal open size="lg" title={text.title} onClose={onCerrar} footer={<Button variant="secondary" onClick={onCerrar}>{text.cancel}</Button>}>
        <ErrorState title={text.loadErrorTitle} description="" retryLabel={text.retry} onRetry={() => void cadena.refetch()} />
      </Modal>
    )
  }

  if (cadena.data === undefined || !cadena.isFetchedAfterMount) {
    return (
      <Modal open size="lg" title={text.title} onClose={onCerrar}>
        <div role="status" aria-label={text.loadingLabel} className="flex flex-col gap-3">
          <Skeleton className="h-11" />
          <Skeleton className="h-11" />
          <Skeleton className="h-11" />
        </div>
      </Modal>
    )
  }

  return (
    <Editor
      key={recarga}
      cadena={cadena.data}
      onCerrar={onCerrar}
      onGuardada={onGuardada}
      onVerEnArbol={onVerEnArbol}
      onRecargar={async () => {
        await cadena.refetch()
        setRecarga((n) => n + 1)
      }}
    />
  )
}

interface EditorProps {
  cadena: CadenaDeTipos
  onCerrar: () => void
  onGuardada: () => void
  onVerEnArbol: (codigo: string) => void
  onRecargar: () => Promise<void>
}

function Editor({ cadena, onCerrar, onGuardada, onVerEnArbol, onRecargar }: EditorProps) {
  const guardar = useGuardarCadena()
  const [borrador, setBorrador] = useState<BorradorDeTipo[]>(() => cadena.tipos.map((tipo) => ({ id: tipo.id, nombre: tipo.nombre })))
  const [errores, setErrores] = useState<Record<number, string>>({})
  const [problema, setProblema] = useState<'red' | 'conflicto' | 'inesperado' | 'revisar' | null>(null)
  const [conflictos, setConflictos] = useState<UnidadEnConflicto[]>([])
  const [anuncio, setAnuncio] = useState('')
  const [arrastrando, setArrastrando] = useState<number | null>(null)

  const hayCambios = borrador.some((item, indice) => item.id !== cadena.tipos[indice].id || item.nombre.trim() !== cadena.tipos[indice].nombre)

  const mover = (desde: number, hasta: number) => {
    // El tipo raíz no se mueve y nada puede quedar por encima de él.
    if (desde < 1 || hasta < 1 || hasta >= borrador.length || desde === hasta) return
    const siguiente = [...borrador]
    const [item] = siguiente.splice(desde, 1)
    siguiente.splice(hasta, 0, item)
    setBorrador(siguiente)
    setAnuncio(text.moved(item.nombre.trim() || '…', hasta + 1, siguiente.length))
  }

  const renombrar = (indice: number, nombre: string) => {
    setBorrador((actual) => actual.map((item, i) => (i === indice ? { ...item, nombre } : item)))
    setErrores((actuales) => {
      const resto = { ...actuales }
      delete resto[borrador[indice].id]
      return resto
    })
  }

  const validar = (): Record<number, string> => {
    const resultado: Record<number, string> = {}
    borrador.forEach((item, indice) => {
      const nombre = item.nombre.trim()
      if (nombre === '') resultado[item.id] = unidadesText.validation.nameRequired
      else if (nombre.length > MAX_NOMBRE_TIPO) resultado[item.id] = unidadesText.validation.nameTooLong(MAX_NOMBRE_TIPO)
      else if (borrador.some((otro, j) => j !== indice && claveDeNombre(otro.nombre) === claveDeNombre(nombre))) {
        resultado[item.id] = unidadesText.validation.typeNameDuplicated
      }
    })

    return resultado
  }

  const enviar = () => {
    if (guardar.isPending || !hayCambios) return
    setProblema(null)
    const locales = validar()
    setErrores(locales)
    if (Object.keys(locales).length > 0) return setProblema('revisar')

    const tipos = borrador.map((item) => ({ id: item.id, nombre: item.nombre.trim() }))
    guardar.mutate(
      { version: cadena.version, tipos },
      {
        onSuccess: onGuardada,
        onError: (fallo) => {
          const api = toApiError(fallo)
          if (api.isNetworkError) return setProblema('red')
          if (api.status === 409) return setProblema('conflicto')
          if (api.status === 422) {
            const unidades = unidadesEnConflictoDe(fallo)
            if (unidades.length > 0) return setConflictos(unidades)
            const delServidor: Record<number, string> = {}
            for (const [clave, mensajes] of Object.entries(api.fieldErrors)) {
              const indice = /^tipos\.(\d+)\.nombre$/.exec(clave)?.[1]
              if (indice !== undefined && tipos[Number(indice)]) delServidor[tipos[Number(indice)].id] = mensajes[0]
            }
            if (Object.keys(delServidor).length > 0) {
              setErrores(delServidor)
              return setProblema('revisar')
            }
          }
          setProblema('inesperado')
        },
      },
    )
  }

  const total = borrador.length

  return (
    <>
      <Modal
        open
        size="lg"
        title={text.title}
        description={text.description}
        onClose={onCerrar}
        busy={guardar.isPending}
        footer={
          <>
            <Button variant="secondary" onClick={onCerrar} disabled={guardar.isPending}>
              {text.cancel}
            </Button>
            <Button onClick={enviar} loading={guardar.isPending} disabled={!hayCambios}>
              {guardar.isPending ? text.saving : text.save}
            </Button>
          </>
        }
      >
        {problema === 'revisar' && <Alert>{text.reviewFields}</Alert>}
        {problema === 'red' && <Alert>{text.networkError}</Alert>}
        {problema === 'inesperado' && <Alert>{text.unexpectedError}</Alert>}
        {problema === 'conflicto' && (
          <Alert>
            <p>{text.conflict}</p>
            <Button variant="secondary" className="mt-2" onClick={() => void onRecargar()}>
              {text.reload}
            </Button>
          </Alert>
        )}

        <ol aria-label={text.listLabel} className="m-0 flex list-none flex-col gap-2 p-0">
          {borrador.map((item, indice) => {
            const esRaiz = indice === 0
            return (
              <li
                key={item.id}
                onDragOver={(evento: DragEvent) => {
                  if (arrastrando !== null && indice >= 1) evento.preventDefault()
                }}
                onDrop={(evento: DragEvent) => {
                  evento.preventDefault()
                  if (arrastrando !== null) mover(arrastrando, indice)
                  setArrastrando(null)
                }}
                className={`flex items-start gap-2 rounded-lg border bg-surface px-2 py-1.5 ${arrastrando === indice ? 'border-accent opacity-60' : 'border-border'}`}
              >
                <span className="flex h-11 w-16 shrink-0 items-center text-[13px] font-semibold text-muted">{text.levelPrefix(indice + 1)}</span>
                <div className="min-w-0 flex-1">
                  <TextField
                    id={`tipo-${item.id}`}
                    label={text.name(indice + 1)}
                    hideLabel
                    maxLength={MAX_NOMBRE_TIPO}
                    value={item.nombre}
                    onChange={(evento) => renombrar(indice, evento.target.value)}
                    error={errores[item.id]}
                  />
                </div>
                {esRaiz ? (
                  <span role="img" aria-label={text.rootLocked} title={text.rootLocked} className="flex h-11 w-[132px] shrink-0 items-center justify-end gap-2 text-muted">
                    <IconLock size={18} />
                    <span className="text-xs">{text.rootLocked}</span>
                  </span>
                ) : (
                  <div className="flex shrink-0 items-center">
                    <span
                      draggable
                      aria-hidden="true"
                      title={text.dragHandle(item.nombre)}
                      onDragStart={(evento) => {
                        evento.dataTransfer.setData('text/plain', String(indice))
                        evento.dataTransfer.effectAllowed = 'move'
                        setArrastrando(indice)
                      }}
                      onDragEnd={() => setArrastrando(null)}
                      className="flex size-11 cursor-grab items-center justify-center text-muted"
                    >
                      <IconGripVertical size={18} />
                    </span>
                    <button
                      type="button"
                      aria-label={text.up(item.nombre)}
                      disabled={indice <= 1}
                      onClick={() => mover(indice, indice - 1)}
                      className="inline-flex size-11 cursor-pointer items-center justify-center rounded-lg hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45"
                    >
                      <IconArrowUp size={18} />
                    </button>
                    <button
                      type="button"
                      aria-label={text.down(item.nombre)}
                      disabled={indice >= total - 1}
                      onClick={() => mover(indice, indice + 1)}
                      className="inline-flex size-11 cursor-pointer items-center justify-center rounded-lg hover:bg-soft disabled:cursor-not-allowed disabled:opacity-45"
                    >
                      <IconArrowDown size={18} />
                    </button>
                  </div>
                )}
              </li>
            )
          })}
        </ol>
        <p role="status" aria-live="polite" className="min-h-5 text-[13px] text-muted">
          {anuncio}
        </p>

        {hayCambios ? <VistaPreviaDeCadena actual={cadena.tipos} resultante={borrador} /> : <p className="text-sm text-muted">{text.unchanged}</p>}
      </Modal>

      {conflictos.length > 0 && (
        <BloqueoDeCadenaModal unidades={conflictos} onVer={(unidad) => onVerEnArbol(unidad.codigo)} onVolver={() => setConflictos([])} />
      )}
    </>
  )
}
