import { useState } from 'react'
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Badge } from '../../../../shared/ui/Badge'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { Button } from '../../../../shared/ui/Button'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconPlus } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TIPOS, type SituacionPolitica, type TipoPolitica, type Version } from '../api'
import { CondicionesFijas } from '../components/CondicionesFijas'
import { DialogoDeRetiro } from '../components/DialogoDeRetiro'
import { LineaDeVersiones } from '../components/LineaDeVersiones'
import { ResumenDeParametros } from '../components/ResumenDeParametros'
import { useCrearBorrador } from '../hooks/useGuardarPolitica'
import { usePolitica } from '../hooks/usePoliticas'
import { politicasText } from '../text'
import { formatearInstanteLocal, problemaDeGuardado } from '../validation'

const text = politicasText.detail

const VARIANTES: Record<SituacionPolitica, 'default' | 'neutral' | 'warning'> = {
  SinConfigurar: 'warning',
  Vigente: 'default',
  NoAdmiteSolicitudes: 'neutral',
}

/** Detalle de un tipo: condiciones vigentes, reglas fijas, versión programada, borrador y versiones anteriores. */
export function PoliticaDetallePage() {
  const { tipo: tipoParam } = useParams()
  const tipo = TIPOS.find((t) => t === tipoParam) ?? null
  const location = useLocation()
  const navigate = useNavigate()
  const consulta = usePolitica(tipo)
  const crear = useCrearBorrador((tipo ?? 'permisos') as TipoPolitica)
  const [retirando, setRetirando] = useState<Version | null>(null)
  const [creandoDesde, setCreandoDesde] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [avisoLocal, setAvisoLocal] = useState<string | null>(null)
  const aviso = avisoLocal ?? (location.state as { aviso?: string } | null)?.aviso

  if (tipo === null) {
    return (
      <div className="mx-auto max-w-225 rounded-xl border border-border bg-surface">
        <ErrorState title={text.notFoundTitle} description={text.notFoundDescription} retryLabel={text.backToList} onRetry={() => void navigate('/politicas-personal')} />
      </div>
    )
  }

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={text.loading} className="mx-auto flex max-w-225 flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2, 3].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  }

  if (consulta.isError) {
    const noExiste = toApiError(consulta.error).status === 404
    return (
      <div className="mx-auto max-w-225 rounded-xl border border-border bg-surface">
        <ErrorState
          title={noExiste ? text.notFoundTitle : text.errorTitle}
          description={noExiste ? text.notFoundDescription : text.errorDescription}
          retryLabel={politicasText.retry}
          onRetry={() => void consulta.refetch()}
        />
        <p className="pb-6 text-center">
          <Link to="/politicas-personal" className="font-semibold text-accent underline">
            {text.backToList}
          </Link>
        </p>
      </div>
    )
  }

  const { resumen, definicion, vigente, programada, borrador, versiones } = consulta.data
  const nombre = politicasText.tipos[tipo]

  const crearBorrador = (base?: Version) => {
    setError(null)
    setCreandoDesde(base?.id ?? null)
    crear.mutate(base?.id, {
      onSuccess: () => void navigate(`/politicas-personal/${tipo}/borrador`),
      onError: (fallo) => {
        const problema = problemaDeGuardado(fallo)
        setError(problema.aviso === 'conflicto' ? text.existingDraft : problema.aviso === 'deshabilitada' ? text.writeDisabled : text.createFailed)
        setCreandoDesde(null)
      },
    })
  }

  return (
    <div className="mx-auto flex max-w-225 flex-col gap-5">
      <Breadcrumb label={text.breadcrumbLabel} items={[{ label: politicasText.navLabel, to: '/politicas-personal' }, { label: nombre }]} />

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{nombre}</h1>
          <p className="mt-1.5 flex flex-wrap items-center gap-3 text-base text-muted">
            <Badge variant={VARIANTES[resumen.situacion]}>{politicasText.situaciones[resumen.situacion]}</Badge>
          </p>
        </div>
        <div className="flex flex-wrap gap-3">
          {borrador ? (
            <Link
              to={`/politicas-personal/${tipo}/borrador`}
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-accent px-5 text-[15px] font-semibold text-onaccent hover:bg-accent-hover"
            >
              {text.continueDraft}
            </Link>
          ) : (
            <Button onClick={() => crearBorrador()} loading={crear.isPending && creandoDesde === null}>
              <IconPlus size={18} />
              {text.createDraft}
            </Button>
          )}
        </div>
      </div>

      {aviso && (
        <p role="status" className="rounded-lg bg-soft px-3.5 py-3 text-sm font-semibold text-accent">
          {aviso}
        </p>
      )}
      {error && <Alert>{error}</Alert>}

      <section aria-labelledby="condiciones-vigentes" className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        <h2 id="condiciones-vigentes" className="text-lg font-bold">
          {text.current}
        </h2>
        {vigente === null ? (
          <Alert tone="neutral">
            <p className="font-semibold">{text.notConfiguredTitle}</p>
            <p>{text.notConfiguredDescription}</p>
          </Alert>
        ) : (
          <>
            <p className="text-sm text-muted">
              {politicasText.version.number(vigente.numero)} · {formatearInstanteLocal(vigente.inicio_vigencia_local)}
            </p>
            {vigente.descripcion && <p className="text-[15px]">{vigente.descripcion}</p>}
            {resumen.situacion === 'NoAdmiteSolicitudes' && <Alert tone="neutral">{text.notAcceptingNote}</Alert>}
            <ResumenDeParametros parametros={vigente.parametros} label={text.current} />
          </>
        )}
      </section>

      {programada && (
        <section aria-labelledby="version-programada" className="flex flex-col gap-2 rounded-xl border border-border bg-surface p-5">
          <h2 id="version-programada" className="text-lg font-bold">
            {text.scheduled}
          </h2>
          <p className="text-[15px]">{text.scheduledNote(programada.numero, formatearInstanteLocal(programada.inicio_vigencia_local))}</p>
        </section>
      )}

      {borrador && (
        <section aria-labelledby="borrador-activo" className="flex flex-col gap-2 rounded-xl border border-border bg-surface p-5">
          <h2 id="borrador-activo" className="text-lg font-bold">
            {text.draft}
          </h2>
          <p className="text-[15px]">{borrador.descripcion || politicasText.notDefined}</p>
        </section>
      )}

      <CondicionesFijas reglas={definicion.reglas_fijas} />

      <section aria-labelledby="versiones" className="flex flex-col gap-3">
        <h2 id="versiones" className="text-lg font-bold">
          {text.versions}
        </h2>
        <LineaDeVersiones
          tipo={tipo}
          versiones={versiones}
          hayBorrador={borrador !== null}
          creandoDesdeId={crear.isPending ? creandoDesde : null}
          onCrearDesde={crearBorrador}
          onRetirar={(version) => {
            setAvisoLocal(null)
            setRetirando(version)
          }}
        />
      </section>

      {retirando && (
        <DialogoDeRetiro
          tipo={tipo}
          version={retirando}
          onCerrar={() => setRetirando(null)}
          onRetirada={(version) => {
            setRetirando(null)
            setAvisoLocal(text.retiredNotice(version.numero))
          }}
        />
      )}
    </div>
  )
}
