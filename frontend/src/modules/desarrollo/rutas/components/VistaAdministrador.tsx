import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Button } from '../../../../shared/ui/Button'
import { IconPlus, IconStairs } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { TIPOS_RUTA, type RutaResumen } from '../api'
import { useFiltrosRutas } from '../hooks/useFiltrosRutas'
import { useOpcionesRutas, useRutas } from '../hooks/useRutas'
import { PERMISOS } from '../permisos'
import { rutasText } from '../text'
// import { AvisoGuia } from './AvisoGuia'
import { BarraDeFiltrosRutas } from './BarraDeFiltrosRutas'
import { EtiquetaTipo } from './Etiquetas'
import { FormularioRutaModal } from './FormularioRutaModal'
import { RequirePermiso } from './RequirePermiso'
import { TarjetaDeRuta } from './TarjetaDeRuta'

const t = rutasText.lista
const POR_PAGINA = 100

function Kpi({ etiqueta, valor, nota }: { etiqueta: string; valor: number; nota: string }) {
  return (
    <div className="rounded-xl border border-border bg-surface px-5 py-4">
      <div className="text-[13px] text-muted">{etiqueta}</div>
      <div className="mt-1 text-[28px] font-bold leading-tight tracking-tight tabular-nums">{valor}</div>
      <div className="mt-1 text-[13px] text-muted">{nota}</div>
    </div>
  )
}

/** Lista de rutas del Administrador (patrón Listado, RutasAdmin.dc.html): tarjetas agrupadas por tipo con filtros en la URL. */
export function VistaAdministrador() {
  const navigate = useNavigate()
  const filtros = useFiltrosRutas()
  const [creando, setCreando] = useState(false)
  const opciones = useOpcionesRutas()
  const todas = useRutas({ per_page: POR_PAGINA })
  const lista = useRutas({
    q: filtros.q || undefined,
    tipo: filtros.tipo || undefined,
    estado: filtros.estado || undefined,
    departamento_id: filtros.departamento === '' ? undefined : filtros.departamento,
    per_page: POR_PAGINA,
  })

  const rutas = lista.data?.data ?? []
  const total = todas.data?.meta.total ?? 0
  const vigentes = (todas.data?.data ?? []).filter((r) => r.estado === 'Vigente')
  const borradores = (todas.data?.data ?? []).filter((r) => r.estado === 'Borrador')

  const grupos = TIPOS_RUTA.map((tipo) => ({ tipo, rutas: rutas.filter((r: RutaResumen) => r.tipo === tipo) })).filter((g) => g.rutas.length > 0)

  return (
    <div className="mx-auto flex max-w-282 flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          {/* <p className="m-0 mb-1 text-[13px] font-semibold text-accent">{t.eyebrow}</p> */}
          <h1 className="m-0 text-[28px] font-bold leading-tight tracking-tight">{t.title}</h1>
          <p className="mt-1.5 text-base text-muted">{t.subtitle}</p>
        </div>
        <RequirePermiso clave={PERMISOS.gestionar}>
          <Button onClick={() => setCreando(true)}>
            <IconPlus size={18} />
            {t.nueva}
          </Button>
        </RequirePermiso>
      </div>

      {/* <AvisoGuia /> */}

      <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4">
        <Kpi etiqueta={t.kpis.vigentes} valor={vigentes.length} nota={t.kpis.vigentesNota(total)} />
        <Kpi etiqueta={t.kpis.participantes} valor={vigentes.reduce((suma, r) => suma + r.inscritos_count, 0)} nota={t.kpis.participantesNota} />
        <Kpi etiqueta={t.kpis.borradores} valor={borradores.length} nota={t.kpis.borradoresNota} />
      </div>

      <BarraDeFiltrosRutas filtros={filtros} opciones={opciones.data} />

      {lista.isPending ? (
        <div role="status" aria-label={rutasText.loading} className="grid grid-cols-[repeat(auto-fill,minmax(320px,1fr))] gap-4">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-56" />
          ))}
        </div>
      ) : lista.isError ? (
        <ErrorState title={rutasText.error.title} description={rutasText.error.description} retryLabel={rutasText.error.retry} onRetry={() => void lista.refetch()} />
      ) : total === 0 && !filtros.hayFiltros ? (
        <EmptyState icon={<IconStairs size={24} />} title={t.sinRutas.title} description={t.sinRutas.description} />
      ) : (
        <>
          <p role="status" aria-live="polite" className="m-0 text-sm text-muted">
            {t.resumen(rutas.length, total)}
          </p>
          {grupos.map((grupo) => (
            <section key={grupo.tipo} aria-label={rutasText.tipos[grupo.tipo]} className="flex flex-col gap-3">
              <div className="flex items-center gap-2.5">
                <EtiquetaTipo tipo={grupo.tipo} />
                <span className="text-sm text-muted">{t.cuenta(grupo.rutas.length)}</span>
              </div>
              <div className="grid grid-cols-[repeat(auto-fill,minmax(320px,1fr))] gap-4">
                {grupo.rutas.map((ruta) => (
                  <TarjetaDeRuta key={ruta.id} ruta={ruta} />
                ))}
              </div>
            </section>
          ))}
          {rutas.length === 0 && <p className="m-0 rounded-xl border border-dashed border-border p-6 text-muted">{t.vacio.title}</p>}
        </>
      )}

      {creando && (
        <FormularioRutaModal
          ruta={null}
          onCerrar={() => setCreando(false)}
          onGuardado={(ruta) => {
            setCreando(false)
            navigate(`/rutas/${ruta.id}`, { state: { aviso: rutasText.formulario.creada } })
          }}
        />
      )}
    </div>
  )
}
