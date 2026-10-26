import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { Combobox } from '../../../../shared/ui/Combobox'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextareaField } from '../../../../shared/ui/TextareaField'
import { TextField } from '../../../../shared/ui/TextField'
import { CondicionesAplicables, usePoliticaAplicable } from '../../politicas'
import type { TipoDeMovimiento } from '../api'
import { AvisoDeSituacionDePolitica } from '../components/AvisoDeSituacionDePolitica'
import { SelectorDeColaborador } from '../components/SelectorDeColaborador'
import { SelectorDeRespaldos } from '../components/SelectorDeRespaldos'
import { problemaDeActuacion, type ProblemaDeActuacion } from '../errores'
import { useProponerMovimiento } from '../hooks/useAccionesDeActuacion'
import { useContextoDeMovimiento, useRespaldosDeAdministrador } from '../hooks/useActuaciones'
import { useClaveDeOperacion } from '../hooks/useClaveDeOperacion'
import { politicaDe, situacionDeFormulario } from '../politica'
import { movimientosText } from '../text'
import { esquemaDeMovimiento, MOTIVO_MAXIMO } from '../validation'

const text = movimientosText.propose
const RETARDO_BUSQUEDA_MS = 300

/** `/movimientos-personal/proponer`: propuesta de ascenso o traslado (solo Administrador); no modifica asignaciones. */
export function ProponerMovimientoPage() {
  const navigate = useNavigate()
  const [colaboradorId, setColaboradorId] = useState<number | null>(null)
  const [tipo, setTipo] = useState<TipoDeMovimiento>('Ascenso')
  const [destinoId, setDestinoId] = useState<number | null>(null)
  const [busqueda, setBusqueda] = useState('')
  const [consulta, setConsulta] = useState('')
  const [fechaEfectiva, setFechaEfectiva] = useState('')
  const [motivo, setMotivo] = useState('')
  const [respaldos, setRespaldos] = useState<number[]>([])
  const [errores, setErrores] = useState<Record<string, string>>({})
  const [problema, setProblema] = useState<ProblemaDeActuacion | null>(null)
  const claveDe = useClaveDeOperacion()

  useEffect(() => {
    const espera = setTimeout(() => setConsulta(busqueda.trim()), RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [busqueda])

  const contexto = useContextoDeMovimiento(colaboradorId, consulta)
  const politica = usePoliticaAplicable(politicaDe(tipo))
  const { situacion, requisitos } = situacionDeFormulario(politica)
  const documentos = useRespaldosDeAdministrador(colaboradorId)
  const proponer = useProponerMovimiento()

  const opciones = useMemo(
    () =>
      (contexto.data?.destinos.items ?? []).map((d) => ({
        value: String(d.id),
        label: `${d.puesto} · ${d.unidad} (${d.id})`,
        description: d.jefatura ? `Jefatura: ${d.jefatura}` : undefined,
      })),
    [contexto.data],
  )

  const sinVinculo = contexto.data !== undefined && !contexto.data.vinculo_vigente
  const documentoExigido = requisitos?.documento_adicional_obligatorio === true

  const cambiarPersona = (id: number | null) => {
    setColaboradorId(id)
    setDestinoId(null)
    setRespaldos([])
    setProblema(null)
    setErrores((actuales) => ({ ...actuales, colaborador_id: '' }))
  }

  const enviar = () => {
    setProblema(null)
    const resultado = esquemaDeMovimiento.safeParse({
      colaborador_id: colaboradorId ?? undefined,
      tipo,
      posicion_destino_id: destinoId ?? undefined,
      fecha_efectiva: fechaEfectiva,
      motivo,
    })
    const nuevos: Record<string, string> = {}
    if (!resultado.success) {
      for (const problemaDeCampo of resultado.error.issues) nuevos[String(problemaDeCampo.path[0])] ??= problemaDeCampo.message
    }
    if (documentoExigido && respaldos.length === 0) nuevos.documento_ids = movimientosText.errors.supportRequired
    setErrores(nuevos)
    if (!resultado.success || Object.keys(nuevos).length > 0) return

    const datos = { tipo, posicion_destino_id: destinoId as number, fecha_efectiva: fechaEfectiva, motivo: motivo.trim(), documento_ids: respaldos }
    proponer.mutate(
      { colaboradorId: colaboradorId as number, datos, clave: claveDe(JSON.stringify([colaboradorId, datos])) },
      {
        onSuccess: (detalle) => navigate(`/movimientos-personal/${detalle.id}`, { state: { aviso: text.success } }),
        onError: (error) => {
          const p = problemaDeActuacion(error)
          setProblema(p)
          setErrores(Object.fromEntries(Object.entries(p.campos).map(([campo, mensaje]) => [campo === 'posicion_destino_id' ? 'posicion_destino_id' : campo, mensaje])))
        },
      },
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <Breadcrumb label="Ruta de navegación" items={[{ label: movimientosText.list.title, to: '/movimientos-personal' }, { label: text.breadcrumb }]} />
      <header>
        <h1 className="text-2xl font-bold">{text.title}</h1>
        <p className="text-muted">{text.subtitle}</p>
      </header>

      <CondicionesAplicables tipo={politicaDe(tipo)} />
      <AvisoDeSituacionDePolitica situacion={situacion} />

      <form
        noValidate
        onSubmit={(evento) => {
          evento.preventDefault()
          enviar()
        }}
        className="flex flex-col gap-5 rounded-xl border border-border bg-surface p-5"
      >
        {problema && !problema.conflicto && <Alert>{problema.mensaje}</Alert>}
        {problema?.conflicto && (
          <Alert>
            <p className="font-semibold">{movimientosText.errors.conflictTitle}</p>
            <p>{movimientosText.errors.conflict}</p>
          </Alert>
        )}

        <SelectorDeColaborador id="movimiento-colaborador" label={text.person} placeholder={text.personPlaceholder} valor={colaboradorId} onChange={cambiarPersona} error={errores.colaborador_id || undefined} hint={text.personHint} />

        {colaboradorId !== null && contexto.isPending && <Skeleton className="h-20" />}
        {contexto.isError && <Alert>{movimientosText.errors.structureUnavailable}</Alert>}
        {sinVinculo && <Alert>{text.noActiveBond}</Alert>}

        {contexto.data && !sinVinculo && (
          <dl aria-label={text.currentSituation} className="grid grid-cols-1 gap-3 rounded-lg border border-border bg-soft p-3.5 sm:grid-cols-2">
            <div className="flex flex-col gap-0.5">
              <dt className="text-[13px] font-semibold text-muted">{text.currentPosition}</dt>
              <dd className="text-[15px]">
                {contexto.data.posicion_actual ? `${contexto.data.posicion_actual.puesto} · ${contexto.data.posicion_actual.unidad ?? movimientosText.none}` : text.noCurrentPosition}
              </dd>
            </div>
          </dl>
        )}

        <SelectField
          id="movimiento-tipo"
          label={text.type}
          value={tipo}
          onChange={(e) => {
            setTipo(e.target.value as TipoDeMovimiento)
            setProblema(null)
          }}
          options={[
            { value: 'Ascenso', label: movimientosText.tipos.Ascenso },
            { value: 'Traslado', label: movimientosText.tipos.Traslado },
          ]}
        />

        {contexto.data && !sinVinculo && (
          <div className="flex flex-col gap-2">
            <SearchField id="movimiento-busqueda" label={text.destinationSearch} placeholder={text.destinationSearchPlaceholder} value={busqueda} onChange={(e) => setBusqueda(e.target.value)} maxLength={50} />
            <Combobox
              id="movimiento-destino"
              label={text.destination}
              options={opciones}
              value={destinoId === null ? null : String(destinoId)}
              onChange={(v) => setDestinoId(v === null ? null : Number(v))}
              placeholder={text.destinationPlaceholder}
              emptyText={text.noDestinations}
              error={errores.posicion_destino_id}
            />
            {contexto.data.destinos.total > contexto.data.destinos.items.length && (
              <p className="text-[13px] text-muted">{text.destinationMore(contexto.data.destinos.items.length, contexto.data.destinos.total)}</p>
            )}
          </div>
        )}

        <TextField id="movimiento-fecha" type="date" label={text.effectiveDate} value={fechaEfectiva} onChange={(e) => setFechaEfectiva(e.target.value)} error={errores.fecha_efectiva} hint={typeof requisitos?.anticipacion_dias === 'number' ? movimientosText.novelty.advance(requisitos.anticipacion_dias) : undefined} />
        <TextareaField id="movimiento-motivo" label={text.reason} hint={text.reasonHint} value={motivo} onChange={(e) => setMotivo(e.target.value)} maxLength={MOTIVO_MAXIMO} currentLength={motivo.length} error={errores.motivo} />

        <SelectorDeRespaldos respaldos={documentos.data} cargando={documentos.isPending && colaboradorId !== null} fallo={documentos.isError} elegidos={respaldos} onChange={setRespaldos} exigido={documentoExigido} error={errores.documento_ids} />

        <p className="text-sm text-muted">{text.confirmWarning}</p>
        <div className="flex justify-end">
          <Button type="submit" loading={proponer.isPending} disabled={situacion !== 'vigente' || sinVinculo}>
            {text.submit}
          </Button>
        </div>
      </form>
    </div>
  )
}
