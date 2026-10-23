import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Stepper } from '../../../../shared/ui/Stepper'
import { TextField } from '../../../../shared/ui/TextField'
import type { AccionVinculable, Pdi } from '../api'
import { aCuerpo, compromisoDesdeSugerido, compromisoVacio, type CompromisoForm, type DatosAsistente } from '../asistente'
import { useCrearPdi, useActualizarPdi } from '../hooks/useMutacionesPdi'
import { useOpciones, useOpcionesDeColaborador, usePrecarga } from '../hooks/usePdi'
import { pdiText } from '../text'
import { pasoDelCampo, validarPaso1, validarPaso2, validarTodo, type ErroresFormulario } from '../validation'
import { CalendarioDeHitos } from './CalendarioDeHitos'
import { SelectorDeAccionVinculable } from './SelectorDeAccionVinculable'
import { SelectorDeColaboradorYTipo } from './SelectorDeColaboradorYTipo'
import { TarjetaDeCompromiso } from './TarjetaDeCompromiso'

const t = pdiText.asistente

interface AsistenteDePdiProps {
  /** Plan en Borrador que se edita; sin él se crea uno nuevo. */
  existente?: Pdi
  inicial: DatosAsistente
  /** Se llama con el plan guardado. */
  onTerminar: (plan: Pdi) => void
  /** Recarga la versión vigente del plan tras un conflicto. */
  onRecargar?: () => void
}

function hoyIso(): string {
  return new Date().toISOString().slice(0, 10)
}

function sumarMeses(iso: string, meses: number): string {
  const fecha = new Date(`${iso}T00:00:00Z`)
  fecha.setUTCMonth(fecha.getUTCMonth() + meses)

  return fecha.toISOString().slice(0, 10)
}

/**
 * Asistente de tres pasos (Colaborador y tipo; Compromisos y acciones; Hitos, responsable y periodo) que guarda el plan en
 * Borrador. El servidor valida de nuevo todo; los errores por campo se llevan al paso que corresponde.
 */
export function AsistenteDePdi({ existente, inicial, onTerminar, onRecargar }: AsistenteDePdiProps) {
  const edicion = existente !== undefined
  const [datos, setDatos] = useState<DatosAsistente>(inicial)
  const [paso, setPaso] = useState(0)
  const [maximo, setMaximo] = useState(edicion ? 2 : 0)
  const [errores, setErrores] = useState<ErroresFormulario>({})
  const [errorGeneral, setErrorGeneral] = useState<string | null>(null)
  const [conflicto, setConflicto] = useState(false)
  const [usarPrecarga, setUsarPrecarga] = useState(true)
  const [vinculando, setVinculando] = useState<{ compromiso: string; accion: string } | null>(null)

  const crear = useCrearPdi()
  const actualizar = useActualizarPdi(existente?.id ?? 0)
  const opciones = useOpciones('')
  const deColaborador = useOpcionesDeColaborador(datos.colaborador_id)
  const precarga = usePrecarga(datos.colaborador_id, datos.tipo, datos.puesto_objetivo_id, !edicion)
  const ocupado = crear.isPending || actualizar.isPending

  const dimensiones = deColaborador.data?.dimensiones_disponibles ?? opciones.data?.dimensiones_disponibles ?? ['Competencia', 'Experiencia', 'Meta']
  const competencias = opciones.data?.competencias ?? []
  const planAbierto = edicion ? null : (deColaborador.data?.plan_abierto[datos.tipo] ?? null)

  const cambiar = (cambios: Partial<DatosAsistente>) => {
    setDatos((d) => ({ ...d, ...cambios }))
    setConflicto(false)
  }

  const irA = (indice: number) => {
    setPaso(indice)
    setMaximo((m) => Math.max(m, indice))
  }

  const sugeridos = (d: DatosAsistente): CompromisoForm[] => (precarga.data?.compromisos ?? []).map((s) => compromisoDesdeSugerido(s, d.fecha_inicio, d.fecha_fin))

  const continuarDelPaso1 = () => {
    const problemas = validarPaso1(datos)
    setErrores(problemas)
    if (Object.keys(problemas).length > 0 || planAbierto !== null) return

    let siguiente = datos
    if (siguiente.fecha_inicio === '' || siguiente.fecha_fin === '') {
      const inicio = hoyIso()
      siguiente = { ...siguiente, fecha_inicio: inicio, fecha_fin: sumarMeses(inicio, 6) }
    }
    if (!edicion && datos.compromisos.length === 0 && usarPrecarga && (precarga.data?.compromisos.length ?? 0) > 0) {
      siguiente = { ...siguiente, compromisos: sugeridos(siguiente), origen: precarga.data?.origen ? { tipo: precarga.data.origen.tipo, id: precarga.data.origen.id } : null }
    }
    setDatos(siguiente)
    irA(1)
  }

  const continuar = () => {
    if (paso === 0) return continuarDelPaso1()
    const problemas = validarPaso2(datos)
    setErrores(problemas)
    if (Object.keys(problemas).length === 0) irA(2)
  }

  const volverAPrecargar = () => {
    const nuevos = sugeridos(datos)
    cambiar({ compromisos: nuevos, origen: precarga.data?.origen ? { tipo: precarga.data.origen.tipo, id: precarga.data.origen.id } : null })
  }

  const agregarCompromiso = () => cambiar({ compromisos: [...datos.compromisos, compromisoVacio(datos.fecha_inicio, datos.fecha_fin, dimensiones[0])] })

  const cambiarCompromiso = (clave: string, cambios: Partial<CompromisoForm>) => cambiar({ compromisos: datos.compromisos.map((c) => (c.clave === clave ? { ...c, ...cambios } : c)) })

  const elegirVinculo = (accion: AccionVinculable) => {
    if (vinculando === null) return
    cambiarCompromiso(vinculando.compromiso, {
      acciones: datos.compromisos.find((c) => c.clave === vinculando.compromiso)?.acciones.map((a) => (a.clave === vinculando.accion ? { ...a, plan_accion_id: accion.plan_accion_id, vinculo_nombre: accion.nombre } : a)) ?? [],
    })
    setVinculando(null)
  }

  const guardar = async () => {
    setErrorGeneral(null)
    setConflicto(false)
    const problemas = validarTodo(datos)
    setErrores(problemas)
    const primero = Object.keys(problemas)[0]
    if (primero !== undefined) {
      irA(pasoDelCampo(primero))
      return
    }

    try {
      const cuerpo = aCuerpo(datos)
      const plan = existente ? await actualizar.mutateAsync({ cuerpo, version: existente.version }) : await crear.mutateAsync(cuerpo)
      onTerminar(plan)
    } catch (error) {
      const api = toApiError(error)
      if (api.status === 409) {
        setConflicto(true)
        return
      }
      const delServidor: ErroresFormulario = {}
      for (const [campo, mensajes] of Object.entries(api.fieldErrors)) delServidor[campo] = mensajes[0] ?? ''
      setErrores(delServidor)
      const campo = Object.keys(delServidor)[0]
      if (campo !== undefined) irA(pasoDelCampo(campo))
      setErrorGeneral(campo === undefined ? (api.message ?? t.errores.guardar) : (api.message ?? null))
    }
  }

  const periodoDefinido = datos.fecha_inicio !== '' && datos.fecha_fin !== ''
  const responsables = opciones.data?.responsables ?? []
  const vinculoDe = (clave: string | undefined) => datos.compromisos.flatMap((c) => c.acciones).find((a) => a.clave === clave)?.plan_accion_id ?? null

  return (
    <div className="flex flex-col gap-6">
      <Stepper label={t.stepperLabel} pasos={[...t.pasos]} actual={paso} maximo={maximo} onSelect={irA} />

      {conflicto && (
        <Alert>
          {t.errores.conflicto}{' '}
          {onRecargar !== undefined && (
            <button type="button" className="font-semibold underline" onClick={onRecargar}>
              {t.errores.recargar}
            </button>
          )}
        </Alert>
      )}
      {errorGeneral !== null && <Alert>{errorGeneral}</Alert>}

      <div className="rounded-xl border border-border bg-surface p-5">
        {paso === 0 && (
          <SelectorDeColaboradorYTipo
            datos={datos}
            errores={errores}
            opciones={opciones.data}
            deColaborador={deColaborador.data}
            precarga={precarga.data}
            usarPrecarga={usarPrecarga}
            bloqueado={edicion}
            onChange={cambiar}
            onUsarPrecarga={setUsarPrecarga}
          />
        )}

        {paso === 1 && (
          <div className="flex flex-col gap-4">
            <p className="m-0 text-muted">{t.paso2.ayuda}</p>
            {datos.compromisos.length === 0 && <p className="m-0">{t.paso2.sinCompromisos}</p>}
            {datos.compromisos.map((c, i) => (
              <TarjetaDeCompromiso
                key={c.clave}
                numero={i + 1}
                compromiso={c}
                errores={errores}
                dimensiones={dimensiones}
                competencias={competencias}
                puedeVincular={datos.colaborador_id !== null}
                onChange={(cambios) => cambiarCompromiso(c.clave, cambios)}
                onQuitar={() => cambiar({ compromisos: datos.compromisos.filter((x) => x.clave !== c.clave) })}
                onVincular={(accion) => setVinculando({ compromiso: c.clave, accion })}
              />
            ))}
            <div className="flex flex-wrap gap-3">
              <Button variant="secondary" onClick={agregarCompromiso}>
                {t.paso2.agregarCompromiso}
              </Button>
              {!edicion && (precarga.data?.compromisos.length ?? 0) > 0 && (
                <Button variant="secondary" onClick={volverAPrecargar}>
                  {t.paso2.volverAPrecargar}
                </Button>
              )}
            </div>
          </div>
        )}

        {paso === 2 && (
          <div className="flex flex-col gap-5">
            <SelectField
              id="pdi-responsable"
              label={t.paso3.responsable}
              options={[{ value: '', label: t.paso3.elegirResponsable }, ...responsables.map((r) => ({ value: String(r.id), label: r.nombre }))]}
              value={datos.responsable_id === null ? '' : String(datos.responsable_id)}
              onChange={(e) => cambiar({ responsable_id: e.target.value === '' ? null : Number(e.target.value) })}
              error={errores.responsable_id}
            />
            <div className="grid gap-3 sm:grid-cols-2">
              <TextField id="pdi-inicio" type="date" label={t.paso3.inicio} value={datos.fecha_inicio} onChange={(e) => cambiar({ fecha_inicio: e.target.value })} error={errores.fecha_inicio} />
              <TextField id="pdi-fin" type="date" label={t.paso3.fin} value={datos.fecha_fin} onChange={(e) => cambiar({ fecha_fin: e.target.value })} error={errores.fecha_fin} />
            </div>
            {!periodoDefinido && <p className="m-0 text-[13px] text-muted">{t.errores.fechas}</p>}
            <CalendarioDeHitos hitos={datos.hitos} errores={errores} onChange={(hitos) => cambiar({ hitos })} />
          </div>
        )}
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>{paso > 0 && <Button variant="secondary" onClick={() => irA(paso - 1)} disabled={ocupado}>{t.atras}</Button>}</div>
        <div className="flex flex-wrap gap-3">
          {paso < 2 ? (
            <Button onClick={continuar} disabled={ocupado || planAbierto !== null}>
              {t.continuar}
            </Button>
          ) : (
            <Button loading={ocupado} onClick={() => void guardar()}>
              {ocupado ? t.guardando : edicion ? t.guardar : t.crear}
            </Button>
          )}
        </div>
      </div>

      <SelectorDeAccionVinculable
        open={vinculando !== null}
        colaboradorId={datos.colaborador_id}
        actual={vinculando === null ? null : vinculoDe(vinculando.accion)}
        onElegir={elegirVinculo}
        onClose={() => setVinculando(null)}
      />
    </div>
  )
}
