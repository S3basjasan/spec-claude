import { useId, useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Button } from '../../../../shared/ui/Button'
import { TextField } from '../../../../shared/ui/TextField'
import { MAX_NOMBRE_AREA } from '../api'
import { useAreas } from '../hooks/useCatalogo'
import { useCrearArea, useRenombrarArea } from '../hooks/useGuardarConfiguracion'
import { catalogoText } from '../text'
import { nombreAreaSchema } from '../validation'
import { useDialogoAccesible } from './useDialogoAccesible'

const text = catalogoText.areas

/** Mensaje de error de un nombre de área: primero la validación local y luego lo que responda el servidor. */
function mensajeDeError(error: unknown): string {
  const api = toApiError(error)
  return api.fieldErrors.nombre?.[0] ?? api.message ?? text.errors.generic
}

/** Diálogo para crear y renombrar áreas formativas. Las áreas no se eliminan. */
export function GestionAreasDialog({ onCerrar }: { onCerrar: () => void }) {
  const tituloId = useId()
  const areas = useAreas()
  const crear = useCrearArea()
  const renombrar = useRenombrarArea()
  const { ref, onKeyDown } = useDialogoAccesible(onCerrar, crear.isPending || renombrar.isPending)

  const [nuevo, setNuevo] = useState('')
  const [errorNuevo, setErrorNuevo] = useState<string>()
  const [editando, setEditando] = useState<number | null>(null)
  const [nombre, setNombre] = useState('')
  const [errorNombre, setErrorNombre] = useState<string>()
  const [aviso, setAviso] = useState<string>()

  const onCrear = () => {
    const resultado = nombreAreaSchema.safeParse(nuevo)
    if (!resultado.success) return setErrorNuevo(resultado.error.issues[0]?.message)
    setErrorNuevo(undefined)
    crear.mutate(resultado.data, {
      onSuccess: () => {
        setNuevo('')
        setAviso(text.created)
      },
      onError: (error) => setErrorNuevo(mensajeDeError(error)),
    })
  }

  const onRenombrar = (id: number) => {
    const resultado = nombreAreaSchema.safeParse(nombre)
    if (!resultado.success) return setErrorNombre(resultado.error.issues[0]?.message)
    setErrorNombre(undefined)
    renombrar.mutate(
      { id, nombre: resultado.data },
      {
        onSuccess: () => {
          setEditando(null)
          setAviso(text.renamed)
        },
        onError: (error) => setErrorNombre(mensajeDeError(error)),
      },
    )
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div
        ref={ref}
        role="dialog"
        aria-modal="true"
        aria-labelledby={tituloId}
        tabIndex={-1}
        onKeyDown={onKeyDown}
        className="flex max-h-full w-full max-w-lg flex-col gap-4 overflow-y-auto rounded-xl border border-border bg-surface p-6 shadow-[0_12px_32px_rgba(0,0,0,0.14)]"
      >
        <div>
          <h2 id={tituloId} className="text-lg font-bold">
            {text.title}
          </h2>
          <p className="text-sm text-muted">{text.description}</p>
        </div>

        {aviso && (
          <p role="status" className="rounded-lg bg-soft px-3.5 py-3 text-sm font-semibold text-accent">
            {aviso}
          </p>
        )}

        <form
          className="flex flex-col gap-2"
          onSubmit={(event) => {
            event.preventDefault()
            onCrear()
          }}
        >
          <TextField
            id="area-nueva"
            label={text.newLabel}
            value={nuevo}
            maxLength={MAX_NOMBRE_AREA + 20}
            onChange={(event) => {
              setNuevo(event.target.value)
              setAviso(undefined)
            }}
            error={errorNuevo}
            hint={text.counter(nuevo.trim().length)}
          />
          <Button type="submit" loading={crear.isPending} className="self-start">
            {text.create}
          </Button>
        </form>

        {areas.isPending ? null : (areas.data ?? []).length === 0 ? (
          <p className="text-sm text-muted">{text.empty}</p>
        ) : (
          <ul aria-label={text.title} className="m-0 flex list-none flex-col gap-2 p-0">
            {(areas.data ?? []).map((area) => (
              <li key={area.id} className="rounded-lg border border-border px-4 py-3">
                {editando === area.id ? (
                  <form
                    className="flex flex-col gap-2"
                    onSubmit={(event) => {
                      event.preventDefault()
                      onRenombrar(area.id)
                    }}
                  >
                    <TextField
                      id={`area-${area.id}`}
                      label={text.nameLabel}
                      value={nombre}
                      onChange={(event) => setNombre(event.target.value)}
                      error={errorNombre}
                      hint={text.counter(nombre.trim().length)}
                    />
                    <div className="flex gap-3">
                      <Button type="submit" loading={renombrar.isPending}>
                        {text.save}
                      </Button>
                      <Button variant="secondary" onClick={() => setEditando(null)}>
                        {text.cancel}
                      </Button>
                    </div>
                  </form>
                ) : (
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <span className="text-[15px] font-semibold">{area.nombre}</span>
                    <Button
                      variant="secondary"
                      aria-label={text.renameOf(area.nombre)}
                      onClick={() => {
                        setEditando(area.id)
                        setNombre(area.nombre)
                        setErrorNombre(undefined)
                        setAviso(undefined)
                      }}
                    >
                      {text.rename}
                    </Button>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}

        <div className="flex justify-end">
          <Button variant="secondary" onClick={onCerrar}>
            {text.close}
          </Button>
        </div>
      </div>
    </div>
  )
}
