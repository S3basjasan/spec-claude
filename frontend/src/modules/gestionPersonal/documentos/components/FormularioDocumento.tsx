import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useRef, useState, type ChangeEvent } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { Modal } from '../../../../shared/ui/Modal'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextField } from '../../../../shared/ui/TextField'
import { categoriasKey, useCategoriasActivas } from '../../categorias'
import type { Documento } from '../api'
import { useCargarDocumento } from '../hooks/useGuardarDocumento'
import { documentosText } from '../text'
import { documentoSchema, errorDeArchivo, LIMITE_TITULO, problemaDeCarga, type DocumentoFormValues, type ProblemaDeCarga } from '../validation'

const text = documentosText.upload
const FORM_ID = 'formulario-documento'
const SIN_PROBLEMA: ProblemaDeCarga = { titulo: null, categoria: null, archivo: null, reseleccionar: false, aviso: null }

interface FormularioDocumentoProps {
  colaboradorId: number
  onCerrar: () => void
  onCargado: (documento: Documento) => void
}

function Aviso({ aviso }: { aviso: ProblemaDeCarga['aviso'] }) {
  const mensajes = {
    categoria: text.categoryGone,
    clave: text.keyReused,
    almacenamiento: text.storage,
    red: text.network,
    deshabilitada: text.disabled,
    noExiste: text.notFound,
    inesperado: text.unexpected,
  } as const

  return aviso === null ? null : <Alert>{mensajes[aviso]}</Alert>
}

/** Diálogo de carga: título, categoría activa, PDF y visibilidad (desmarcada por defecto). */
export function FormularioDocumento({ colaboradorId, onCerrar, onCargado }: FormularioDocumentoProps) {
  const queryClient = useQueryClient()
  const categorias = useCategoriasActivas()
  const cargar = useCargarDocumento(colaboradorId)
  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<DocumentoFormValues>({
    resolver: zodResolver(documentoSchema),
    defaultValues: { titulo: '', categoria_id: '', visible_colaborador: false },
  })
  const [archivo, setArchivo] = useState<File | null>(null)
  const [errorArchivo, setErrorArchivo] = useState<string | null>(null)
  const [problema, setProblema] = useState<ProblemaDeCarga>(SIN_PROBLEMA)
  const enviando = useRef(false)
  // La clave identifica este diálogo abierto: un reintento tras un fallo de red no duplica el documento.
  const clave = useRef<string>(crypto.randomUUID())
  const valores = useWatch({ control })

  const errorDe = (campo: 'titulo' | 'categoria_id') => errors[campo]?.message ?? (campo === 'titulo' ? problema.titulo : problema.categoria) ?? undefined
  const errorDelArchivo = errorArchivo ?? problema.archivo ?? undefined
  const hayErrores = Boolean(errors.titulo || errors.categoria_id || errorDelArchivo || problema.titulo || problema.categoria)
  const sinCategorias = categorias.isSuccess && categorias.data.length === 0

  const elegirArchivo = (evento: ChangeEvent<HTMLInputElement>) => {
    const elegido = evento.target.files?.[0] ?? null
    setArchivo(elegido)
    setErrorArchivo(elegido === null ? null : errorDeArchivo(elegido))
    setProblema((previo) => ({ ...previo, archivo: null, reseleccionar: false }))
  }

  const enviar = (formulario: DocumentoFormValues) => {
    const falla = errorDeArchivo(archivo)
    setErrorArchivo(falla)
    if (falla !== null || archivo === null || enviando.current) return

    enviando.current = true
    setProblema(SIN_PROBLEMA)
    cargar.mutate(
      {
        datos: { titulo: formulario.titulo.trim(), categoria_id: Number(formulario.categoria_id), visible_colaborador: formulario.visible_colaborador, archivo },
        clave: clave.current,
      },
      {
        onSuccess: onCargado,
        onError: (error) => {
          const nuevo = problemaDeCarga(error)
          setProblema(nuevo)
          if (nuevo.reseleccionar) setArchivo(null)
          if (nuevo.aviso === 'clave') clave.current = crypto.randomUUID()
          if (nuevo.aviso === 'categoria') void queryClient.invalidateQueries({ queryKey: categoriasKey })
        },
        onSettled: () => {
          enviando.current = false
        },
      },
    )
  }

  return (
    <Modal
      open
      size="lg"
      title={text.title}
      description={text.subtitle}
      busy={cargar.isPending}
      closeLabel={text.close}
      onClose={onCerrar}
      footer={
        <>
          <Button variant="secondary" disabled={cargar.isPending} onClick={onCerrar}>
            {text.cancel}
          </Button>
          <Button type="submit" form={FORM_ID} loading={cargar.isPending} disabled={sinCategorias}>
            {text.submit}
          </Button>
        </>
      }
    >
      <form id={FORM_ID} noValidate onSubmit={(evento) => void handleSubmit(enviar, () => setErrorArchivo(errorDeArchivo(archivo)))(evento)} className="flex flex-col gap-4">
        {hayErrores && <Alert>{text.errorsAlert}</Alert>}
        <Aviso aviso={problema.aviso} />
        {problema.reseleccionar && <Alert tone="neutral">{text.selectAgain}</Alert>}
        {!problema.reseleccionar && problema.aviso !== null && archivo !== null && <Alert tone="neutral">{text.fileKept}</Alert>}

        {categorias.isPending && <Skeleton className="h-11" />}

        {sinCategorias && (
          <Alert tone="neutral">
            <p className="font-semibold">{text.noCategoriesTitle}</p>
            <p>{text.noCategoriesDescription}</p>
            <Link to="/categorias-documentales" className="mt-1 inline-block font-semibold text-accent underline">
              {text.goToCategories}
            </Link>
          </Alert>
        )}

        <TextField
          id="documento-titulo"
          label={text.titleLabel}
          error={errorDe('titulo')}
          hint={`${String(valores.titulo ?? '').length}/${LIMITE_TITULO}`}
          autoComplete="off"
          {...register('titulo')}
        />

        <SelectField
          id="documento-categoria"
          label={text.categoryLabel}
          error={errorDe('categoria_id')}
          options={[
            { value: '', label: text.categoryPlaceholder },
            ...(categorias.data ?? []).map((categoria) => ({ value: String(categoria.id), label: categoria.nombre })),
          ]}
          {...register('categoria_id')}
        />

        <div className="flex flex-col gap-1.5">
          <label htmlFor="documento-archivo" className="text-sm font-semibold">
            {text.fileLabel}
          </label>
          <input
            key={archivo === null ? 'sin-archivo' : 'con-archivo'}
            id="documento-archivo"
            type="file"
            accept="application/pdf,.pdf"
            onChange={elegirArchivo}
            aria-invalid={errorDelArchivo ? true : undefined}
            aria-describedby="documento-archivo-ayuda"
            className={`rounded-lg border bg-surface px-3 py-2.5 text-[15px] ${errorDelArchivo ? 'border-danger' : 'border-input-border'}`}
          />
          <p id="documento-archivo-ayuda" className={`text-[13px] ${errorDelArchivo ? 'text-danger' : 'text-muted'}`}>
            {errorDelArchivo ?? text.fileHint}
          </p>
        </div>

        <label className="flex min-h-11 cursor-pointer items-start gap-3">
          <input type="checkbox" className="mt-1 size-5" {...register('visible_colaborador')} />
          <span>
            <span className="font-semibold">{text.visibleLabel}</span>
            <span className="block text-[13px] text-muted">{text.visibleHint}</span>
          </span>
        </label>
      </form>
    </Modal>
  )
}
