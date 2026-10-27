import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Alert } from '../../../../shared/ui/Alert'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { SelectField } from '../../../../shared/ui/SelectField'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TextField } from '../../../../shared/ui/TextField'
import { MOTIVOS, type MotivoDeSalida } from '../api'
import { SelectorDeColaborador } from '../components/SelectorDeColaborador'
import { SelectorDeRespaldos } from '../components/SelectorDeRespaldos'
import { problemaDeSalida, type ProblemaDeSalida } from '../errores'
import { useClaveDeOperacion } from '../hooks/useClaveDeOperacion'
import { useContextoDeSalida, useFormalizarSalida, useRespaldosDeSalida } from '../hooks/useOffboarding'
import { offboardingText } from '../text'
import { esquemaDeSalida } from '../validation'

const text = offboardingText.formalize

/** `/offboarding/formalizar`: formaliza la salida (solo Administrador). Fija la política y copia su plantilla; no toca el vínculo. */
export function FormalizarSalidaPage() {
  const navigate = useNavigate()
  const [colaboradorId, setColaboradorId] = useState<number | null>(null)
  const [motivo, setMotivo] = useState<MotivoDeSalida | ''>('')
  const [fechaEfectiva, setFechaEfectiva] = useState('')
  const [respaldos, setRespaldos] = useState<number[]>([])
  const [errores, setErrores] = useState<Record<string, string>>({})
  const [problema, setProblema] = useState<ProblemaDeSalida | null>(null)
  const claveDe = useClaveDeOperacion()

  const contexto = useContextoDeSalida(colaboradorId)
  const documentos = useRespaldosDeSalida(colaboradorId)
  const formalizar = useFormalizarSalida()

  const sinVinculo = contexto.data !== undefined && contexto.data.vinculo_vigente === null
  const existente = contexto.data?.salida_existente ?? null
  const politica = contexto.data?.politica
  const vigente = politica?.situacion === 'Vigente'
  const bloqueado = contexto.data !== undefined && (sinVinculo || existente !== null || !vigente)

  const cambiarPersona = (id: number | null) => {
    setColaboradorId(id)
    setRespaldos([])
    setProblema(null)
    setErrores((actuales) => ({ ...actuales, colaborador_id: '' }))
  }

  const enviar = () => {
    setProblema(null)
    const resultado = esquemaDeSalida.safeParse({ colaborador_id: colaboradorId ?? undefined, motivo: motivo === '' ? undefined : motivo, fecha_efectiva: fechaEfectiva })
    const nuevos: Record<string, string> = {}
    if (!resultado.success) {
      for (const incidencia of resultado.error.issues) nuevos[String(incidencia.path[0])] ??= incidencia.message
    }
    setErrores(nuevos)
    if (!resultado.success) return

    const datos = { colaborador_id: colaboradorId as number, motivo: motivo as MotivoDeSalida, fecha_efectiva: fechaEfectiva, documento_ids: respaldos }
    formalizar.mutate(
      { datos, clave: claveDe(JSON.stringify(datos)) },
      {
        onSuccess: (detalle) => navigate(`/offboarding/${detalle.id}`, { state: { aviso: text.success } }),
        onError: (error) => {
          const p = problemaDeSalida(error)
          setProblema(p)
          setErrores(p.campos)
        },
      },
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <Breadcrumb label="Ruta de navegación" items={[{ label: offboardingText.list.title, to: '/offboarding' }, { label: text.breadcrumb }]} />
      <header>
        <h1 className="text-2xl font-bold">{text.title}</h1>
        <p className="text-muted">{text.subtitle}</p>
      </header>

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
            <p className="font-semibold">{offboardingText.errors.conflictTitle}</p>
            <p>{offboardingText.errors.conflict}</p>
          </Alert>
        )}

        <SelectorDeColaborador id="salida-colaborador" label={text.person} placeholder={text.personPlaceholder} valor={colaboradorId} onChange={cambiarPersona} error={errores.colaborador_id || undefined} hint={text.personHint} />

        {colaboradorId !== null && contexto.isPending && <Skeleton className="h-20" />}
        {contexto.isError && <Alert>{offboardingText.errors.unexpected}</Alert>}
        {sinVinculo && <Alert>{text.noBond}</Alert>}
        {existente && (
          <Alert tone="neutral">
            <p>{text.existing(offboardingText.estados[existente.estado])}</p>
            <Link to={`/offboarding/${existente.id}`} className="font-semibold text-accent underline">
              {text.openExisting}
            </Link>
          </Alert>
        )}

        {politica && (
          <section aria-label={text.policyTitle} className="flex flex-col gap-2 rounded-lg border border-border bg-soft p-3.5">
            <h2 className="text-sm font-semibold">{text.policyTitle}</h2>
            {politica.situacion === 'Vigente' ? (
              <>
                <p className="text-sm">{text.policy.Vigente(politica.numero)}</p>
                <p className="text-[13px] font-semibold text-muted">{text.templateTitle}</p>
                <ol className="list-decimal pl-5 text-sm">
                  {politica.tareas.map((tarea) => (
                    <li key={tarea.orden}>
                      {tarea.nombre}
                      {tarea.exige_evidencia && <span className="text-muted"> · {text.templateEvidence}</span>}
                    </li>
                  ))}
                </ol>
                <p className="text-[13px] text-muted">{text.templateInterview}</p>
              </>
            ) : (
              <Alert>{text.policy[politica.situacion === 'NoAdmiteSolicitudes' ? 'NoAdmiteSolicitudes' : politica.situacion === 'NoDisponible' ? 'NoDisponible' : 'SinConfigurar']}</Alert>
            )}
          </section>
        )}

        <SelectField
          id="salida-motivo"
          label={text.reason}
          value={motivo}
          onChange={(e) => setMotivo(e.target.value as MotivoDeSalida | '')}
          options={[{ value: '', label: 'Elige el motivo' }, ...MOTIVOS.map((m) => ({ value: m, label: offboardingText.motivos[m] }))]}
          error={errores.motivo}
        />
        <p className="-mt-3 text-[13px] text-muted">{text.reasonHint}</p>
        <TextField id="salida-fecha" type="date" label={text.effectiveDate} value={fechaEfectiva} onChange={(e) => setFechaEfectiva(e.target.value)} error={errores.fecha_efectiva} hint={text.effectiveHint} />

        <SelectorDeRespaldos
          titulo={text.supports}
          ayuda={text.supportsHint}
          vacio={text.supportsNone}
          errorDeCarga={text.supportsError}
          respaldos={documentos.data}
          cargando={documentos.isPending && colaboradorId !== null}
          fallo={documentos.isError}
          elegidos={respaldos}
          onChange={setRespaldos}
          error={errores.documento_ids}
        />

        <p className="text-sm text-muted">{text.confirmWarning}</p>
        <div className="flex justify-end">
          <Button type="submit" loading={formalizar.isPending} disabled={bloqueado}>
            {text.submit}
          </Button>
        </div>
      </form>
    </div>
  )
}
