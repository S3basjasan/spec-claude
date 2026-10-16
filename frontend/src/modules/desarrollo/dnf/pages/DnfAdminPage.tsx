import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toApiError } from '../../../../shared/api/httpClient'
import { Button } from '../../../../shared/ui/Button'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconChartBar } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { Stepper } from '../../../../shared/ui/Stepper'
import type { DnfGenerada } from '../api'
import { AvisosDnf } from '../components/AvisosDnf'
import { BotonGenerarPlan } from '../components/BotonGenerarPlan'
import { ConfirmarMasivaDialog } from '../components/ConfirmarMasivaDialog'
import { KpiDiagnostico } from '../components/KpiDiagnostico'
import { PanelDiagnostico } from '../components/PanelDiagnostico'
import { PasoSeleccion, type ErroresDelServidor } from '../components/PasoSeleccion'
import { ResumenPorColaborador } from '../components/ResumenPorColaborador'
import { useDetalleDnf } from '../hooks/useDetalleDnf'
import { useGenerarDnf } from '../hooks/useGenerarDnf'
import { useOpcionesDnf } from '../hooks/useOpcionesDnf'
import { SIN_FILTROS, type FiltrosDiagnostico } from '../orden'
import { dnfText } from '../text'
import type { Seleccion } from '../validation'

const text = dnfText.admin

function erroresDe(error: unknown): ErroresDelServidor {
  const api = toApiError(error)
  return {
    colaborador_id: api.fieldErrors.colaborador_id?.[0],
    departamento_id: api.fieldErrors.departamento_id?.[0],
    general: api.fieldErrors.colaborador_id || api.fieldErrors.departamento_id ? undefined : (api.message ?? text.error.generar),
  }
}

/** Asistente de generación de la DNF (Administrador): paso 1 selección y paso 2 diagnóstico. */
export function DnfAdminPage() {
  const opciones = useOpcionesDnf()
  const generar = useGenerarDnf()
  const [paso, setPaso] = useState<0 | 1>(0)
  const [resultado, setResultado] = useState<DnfGenerada | null>(null)
  const [errores, setErrores] = useState<ErroresDelServidor>({})
  const [pendiente, setPendiente] = useState<Seleccion | null>(null)
  const [elegido, setElegido] = useState<number | null>(null)
  const [filtros, setFiltros] = useState<FiltrosDiagnostico>(SIN_FILTROS)

  const dnf = resultado?.data
  const esMasiva = dnf?.alcance === 'Departamento'
  const detalle = useDetalleDnf(esMasiva && elegido !== null && dnf ? dnf.id : null, elegido)

  const ejecutar = (seleccion: Seleccion) => {
    setErrores({})
    const solicitud =
      seleccion.alcance === 'Individual'
        ? { alcance: 'Individual' as const, colaborador_id: seleccion.colaborador_id as number }
        : { alcance: 'Departamento' as const, departamento_id: seleccion.departamento_id as number }
    generar.mutate(solicitud, {
      onSuccess: (respuesta) => {
        setResultado(respuesta)
        setPendiente(null)
        setElegido(null)
        setFiltros(SIN_FILTROS)
        setPaso(1)
      },
      onError: (error) => setErrores(erroresDe(error)),
    })
  }

  const enviar = (seleccion: Seleccion) => {
    // La masiva pide confirmación antes de generar y avisa cuántos colaboradores se evaluarán.
    if (seleccion.alcance === 'Departamento') {
      setErrores({})
      setPendiente(seleccion)
      return
    }
    ejecutar(seleccion)
  }

  const departamentoPendiente = opciones.data?.departamentos.find((d) => d.id === pendiente?.departamento_id)
  const unico = dnf?.alcance === 'Individual' ? dnf.colaboradores[0] : undefined
  const seleccionado = dnf?.colaboradores.find((c) => c.colaborador_id === elegido)
  const brechasDelElegido = detalle.data?.data.colaboradores[0]?.brechas

  return (
    <div className="mx-auto flex max-w-275 flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
        </div>
        <Link to="/dnf/historial" className="font-semibold text-accent underline">
          {text.verHistorial}
        </Link>
      </div>

      <Stepper
        label={text.pasos.etiqueta}
        pasos={[text.pasos.seleccion, text.pasos.diagnostico]}
        actual={paso}
        maximo={resultado ? 1 : 0}
        onSelect={(indice) => setPaso(indice === 0 ? 0 : 1)}
      />

      {paso === 0 &&
        (opciones.isPending ? (
          <div role="status" aria-label={text.paso1.titulo}>
            <Skeleton className="h-64" />
          </div>
        ) : opciones.isError ? (
          <div className="rounded-xl border border-border bg-surface">
            <ErrorState
              title={text.error.title}
              description={text.error.description}
              retryLabel={text.error.retry}
              onRetry={() => void opciones.refetch()}
            />
          </div>
        ) : opciones.data.colaboradores.length === 0 && opciones.data.departamentos.length === 0 ? (
          <div className="rounded-xl border border-border bg-surface">
            <EmptyState
              icon={<IconChartBar size={24} />}
              title={text.paso1.sinOpciones.title}
              description={text.paso1.sinOpciones.description}
            />
          </div>
        ) : (
          <PasoSeleccion opciones={opciones.data} enviando={generar.isPending && pendiente === null} errores={errores} onSubmit={enviar} />
        ))}

      <ConfirmarMasivaDialog
        open={pendiente !== null}
        cantidad={departamentoPendiente?.colaboradores_evaluables ?? 0}
        departamento={departamentoPendiente?.nombre ?? ''}
        busy={generar.isPending}
        error={errores.general ?? errores.departamento_id}
        onConfirm={() => pendiente && ejecutar(pendiente)}
        onCancel={() => {
          setPendiente(null)
          setErrores({})
        }}
      />

      {paso === 1 && resultado && dnf && (
        <section aria-label={text.pasos.diagnostico} className="flex flex-col gap-5">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="text-xl font-bold">{text.paso2.titulo(unico?.nombre ?? dnf.departamento?.nombre ?? dnf.codigo)}</h2>
              <p className="mt-1 text-sm text-muted">
                {unico?.puesto ? `${unico.puesto.nombre} · ` : ''}
                {dnfText.fechaLabel}: {dnfText.fecha(dnf.fecha)} · {text.paso2.guardadaComo(dnf.codigo)}
              </p>
            </div>
            <Button variant="secondary" onClick={() => setPaso(0)}>
              {text.paso2.cambiarSeleccion}
            </Button>
          </div>

          <AvisosDnf avisos={resultado.avisos} />

          <BotonGenerarPlan
            dnfId={dnf.id}
            cantidadBrechas={dnf.resumen.cantidad_brechas}
            plan={dnf.plan_formacion}
            onGenerado={(plan) =>
              setResultado((actual) =>
                actual
                  ? {
                      ...actual,
                      data: {
                        ...actual.data,
                        plan_formacion: { id: plan.plan_formacion_id, generado_por: plan.generado_por, generado_en: plan.generado_en },
                      },
                    }
                  : actual,
              )
            }
          />

          {unico?.brechas && (
            <PanelDiagnostico nombreColaborador={unico.nombre} brechas={unico.brechas} filtros={filtros} onFiltros={setFiltros} />
          )}

          {esMasiva && elegido === null && (
            <>
              <KpiDiagnostico
                items={[
                  { label: text.kpi.colaboradores, valor: String(dnf.resumen.colaboradores) },
                  { label: text.kpi.conBrecha, valor: String(dnf.resumen.cantidad_brechas) },
                  { label: text.kpi.prioridadMasAlta, valor: dnf.resumen.prioridad_mas_alta ? dnfText.prioridad[dnf.resumen.prioridad_mas_alta] : dnfText.sinPrioridad },
                ]}
              />
              <ResumenPorColaborador
                colaboradores={dnf.colaboradores}
                onElegir={(id) => {
                  setFiltros(SIN_FILTROS)
                  setElegido(id)
                }}
              />
            </>
          )}

          {esMasiva && elegido !== null && (
            <div className="flex flex-col gap-4">
              <div>
                <Button variant="secondary" onClick={() => setElegido(null)}>
                  {text.paso2.volverResumen}
                </Button>
              </div>
              {detalle.isPending ? (
                <div role="status" aria-label={text.paso1.titulo}>
                  <Skeleton className="h-64" />
                </div>
              ) : detalle.isError || !brechasDelElegido ? (
                <div className="rounded-xl border border-border bg-surface">
                  <ErrorState
                    title={text.error.title}
                    description={text.error.description}
                    retryLabel={text.error.retry}
                    onRetry={() => void detalle.refetch()}
                  />
                </div>
              ) : (
                <PanelDiagnostico
                  nombreColaborador={seleccionado?.nombre ?? dnfText.noDisponible}
                  brechas={brechasDelElegido}
                  filtros={filtros}
                  onFiltros={setFiltros}
                />
              )}
            </div>
          )}
        </section>
      )}
    </div>
  )
}
