import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { TextField } from '../../../../shared/ui/TextField'
import type { Parametros } from '../api'
import { useActualizarParametros } from '../hooks/useParametros'
import { adecuacionText } from '../text'
import { sumaDePesos, validarParametros, type BorradorDeParametros, type CampoDeParametros } from '../validation'

const t = adecuacionText.parametros

function borradorDe(p: Parametros): BorradorDeParametros {
  return {
    competencias: String(p.pesos.Competencia),
    conocimientos: String(p.pesos.Conocimiento),
    experiencia: String(p.pesos.Experiencia),
    umbral: String(p.umbral_meses),
  }
}

/** Pesos por dimensión (suma 100) y umbral de preparación; conserva lo escrito si el servidor rechaza el envío. */
export function FormularioDeParametros({ parametros }: { parametros: Parametros }) {
  const guardado = borradorDe(parametros)
  const [borrador, setBorrador] = useState<BorradorDeParametros>(guardado)
  const [errores, setErrores] = useState<Partial<Record<CampoDeParametros, string>>>({})
  const [errorGeneral, setErrorGeneral] = useState<string | undefined>()
  const [mensaje, setMensaje] = useState<string | undefined>()
  const actualizar = useActualizarParametros()

  const sinCambios = (Object.keys(guardado) as (keyof BorradorDeParametros)[]).every((k) => guardado[k] === borrador[k])
  const suma = sumaDePesos(borrador)

  const cambiar = (campo: keyof BorradorDeParametros, valor: string) => {
    setBorrador((actual) => ({ ...actual, [campo]: valor }))
    setMensaje(undefined)
    setErrores({})
    setErrorGeneral(undefined)
  }

  const enviar = () => {
    const encontrados = validarParametros(borrador)
    setErrores(encontrados)
    setErrorGeneral(undefined)
    setMensaje(undefined)
    if (Object.keys(encontrados).length > 0) return

    actualizar.mutate(
      {
        pesos: { Competencia: Number(borrador.competencias), Conocimiento: Number(borrador.conocimientos), Experiencia: Number(borrador.experiencia) },
        umbral_meses: Number(borrador.umbral),
      },
      {
        onSuccess: () => setMensaje(t.guardado),
        onError: (error) => {
          const e = toApiError(error)
          const delServidor: Partial<Record<CampoDeParametros, string>> = {
            pesos: Object.entries(e.fieldErrors).find(([campo]) => campo.startsWith('pesos'))?.[1][0],
            umbral: e.fieldErrors['umbral_meses']?.[0],
          }
          setErrores(delServidor)
          setErrorGeneral(delServidor.pesos !== undefined || delServidor.umbral !== undefined ? undefined : (e.message ?? t.errorGuardar))
        },
      },
    )
  }

  return (
    <form
      aria-labelledby="parametros-titulo"
      className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-5"
      onSubmit={(e) => {
        e.preventDefault()
        enviar()
      }}
      noValidate
    >
      <div>
        <h2 id="parametros-titulo" className="m-0 text-lg font-semibold">
          {t.titulo}
        </h2>
        <p className="m-0 mt-1 text-sm text-muted">{t.descripcion}</p>
      </div>

      <div className="flex flex-wrap items-end gap-4">
        {(
          [
            ['competencias', t.competencias],
            ['conocimientos', t.conocimientos],
            ['experiencia', t.experiencia],
          ] as const
        ).map(([campo, etiqueta]) => (
          <div key={campo} className="w-40">
            <TextField id={`parametro-${campo}`} label={etiqueta} type="number" inputMode="numeric" min={0} max={100} value={borrador[campo]} aria-invalid={errores.pesos !== undefined} onChange={(e) => cambiar(campo, e.target.value)} />
          </div>
        ))}
        <p className={`m-0 pb-3 text-sm font-semibold ${errores.pesos === undefined ? '' : 'text-danger'}`} aria-live="polite">
          {t.suma(suma)}
        </p>
      </div>
      {errores.pesos !== undefined && (
        <p role="alert" className="m-0 text-sm text-danger">
          {errores.pesos}
        </p>
      )}

      <div className="max-w-xs">
        <TextField id="parametro-umbral" label={t.umbral} hint={t.umbralAyuda} type="number" inputMode="numeric" min={1} max={120} value={borrador.umbral} error={errores.umbral} onChange={(e) => cambiar('umbral', e.target.value)} />
      </div>

      {errorGeneral !== undefined && <Alert>{errorGeneral}</Alert>}
      {mensaje !== undefined && <Alert tone="neutral">{mensaje}</Alert>}

      <div>
        <Button type="submit" loading={actualizar.isPending} disabled={actualizar.isPending || sinCambios}>
          {actualizar.isPending ? t.guardando : t.guardar}
        </Button>
        {sinCambios && mensaje === undefined && <span className="ml-3 text-[13px] text-muted">{t.sinCambios}</span>}
      </div>
    </form>
  )
}
