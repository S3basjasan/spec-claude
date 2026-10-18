import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconList, IconSitemap } from '../../../../shared/ui/icons'
import { Modal } from '../../../../shared/ui/Modal'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { coincidencias as calcularCoincidencias, construirArbol, primeraCoincidencia } from '../arbol'
import { AreaDelArbol } from '../components/AreaDelArbol'
import { BarraDeHerramientas } from '../components/BarraDeHerramientas'
import { Leyenda } from '../components/Leyenda'
import { OrganigramaNodo, type ContextoDelArbol } from '../components/OrganigramaNodo'
import { PanelDeDetalle } from '../components/PanelDeDetalle'
import { Resumen } from '../components/Resumen'
import { useEsPantallaEstrecha } from '../hooks/useEsPantallaEstrecha'
import { useEstadoDelArbol } from '../hooks/useEstadoDelArbol'
import { useListadoParams } from '../hooks/useListadoParams'
import { useOrganigrama } from '../hooks/useOrganigrama'
import { organigramaText } from '../text'

const RETARDO_BUSQUEDA_MS = 300
const text = organigramaText

/** Organigrama: la misma pantalla para el Administrador y el Usuario; es de solo lectura. */
export function OrganigramaPage() {
  const consulta = useOrganigrama()

  return (
    <div className="mx-auto flex max-w-350 flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-[28px] font-bold leading-tight tracking-tight">{text.title}</h1>
          <p className="mt-1.5 text-base text-muted">{text.subtitle}</p>
        </div>
        <Link
          to="/posiciones"
          className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-4 text-[15px] font-medium hover:bg-soft"
        >
          <IconList size={18} />
          {text.listLink}
        </Link>
      </div>

      {consulta.isPending ? (
        <div role="status" aria-label={text.loadingLabel} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
          {[0, 1, 2, 3].map((fila) => (
            <Skeleton key={fila} className="h-10" />
          ))}
        </div>
      ) : consulta.isError ? (
        <div className="rounded-xl border border-border bg-surface">
          <ErrorState
            title={text.errorTitle}
            description={text.errorDescription}
            retryLabel={text.retry}
            onRetry={() => void consulta.refetch()}
          />
        </div>
      ) : consulta.data.posiciones.length === 0 ? (
        <div className="rounded-xl border border-border bg-surface">
          <EmptyState icon={<IconSitemap size={24} />} title={text.emptyTitle} description={text.emptyDescription} />
        </div>
      ) : (
        <OrganigramaConDatos organigrama={consulta.data} />
      )}
    </div>
  )
}

function OrganigramaConDatos({ organigrama }: { organigrama: NonNullable<ReturnType<typeof useOrganigrama>['data']> }) {
  const { q, unidad, update } = useListadoParams()
  // Lo que la persona escribe y aún no se aplicó a la URL; null = mostrar lo que dice la URL.
  const [borrador, setBorrador] = useState<string | null>(null)
  const estrecha = useEsPantallaEstrecha()

  // La búsqueda se escribe sin demora y se aplica a la URL tras una breve pausa.
  useEffect(() => {
    if (borrador === null) return
    const espera = setTimeout(() => {
      update({ q: borrador.trim() })
      setBorrador(null)
    }, RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [borrador, update])

  const arbol = useMemo(() => construirArbol(organigrama.posiciones), [organigrama.posiciones])
  const coincidencias = useMemo(
    () => calcularCoincidencias(organigrama.posiciones, organigrama.unidades, q, unidad),
    [organigrama.posiciones, organigrama.unidades, q, unidad],
  )
  const estado = useEstadoDelArbol(arbol, coincidencias)

  const vigentes = organigrama.posiciones.length
  const ocupadas = organigrama.posiciones.filter((p) => p.estado === 'Ocupada').length

  const contexto: ContextoDelArbol = {
    seleccionada: estado.seleccionada,
    coincidencias,
    descendientes: arbol.descendientes,
    estaAbierta: estado.estaAbierta,
    esContraible: estado.esContraible,
    onAlternar: estado.alternar,
    onSeleccionar: (id) => estado.seleccionar(id),
  }

  // El árbol se lleva hasta la primera coincidencia de la búsqueda (por código) cuando esta cambia.
  const primera = q.trim() !== '' && coincidencias !== null ? primeraCoincidencia(arbol.porId, coincidencias) : null
  const llevarABusqueda = primera ? { id: primera.id, clave: `${q}|${unidad ?? ''}` } : null
  const llevarASeleccion = estado.irA ? { id: estado.irA.id, clave: String(estado.irA.version) } : null

  const seleccionada = estado.seleccionada === null ? null : (arbol.porId.get(estado.seleccionada) ?? null)
  const panel = seleccionada && (
    <PanelDeDetalle
      posicion={seleccionada}
      arbol={arbol}
      onSeleccionar={(id) => estado.seleccionar(id, true)}
      onCerrar={estado.cerrarDetalle}
      conCierre={!estrecha}
    />
  )

  return (
    <>
      <BarraDeHerramientas
        busqueda={borrador ?? q}
        unidad={unidad}
        unidades={organigrama.unidades}
        onBuscar={setBorrador}
        onUnidad={(valor) => update({ unidad: valor })}
        onExpandirTodo={estado.expandirTodo}
        onContraerTodo={estado.contraerTodo}
        onLimpiar={() => {
          setBorrador(null)
          update({ q: '', unidad: null })
        }}
      />

      <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
        <Resumen
          vigentes={vigentes}
          ocupadas={ocupadas}
          vacantes={vigentes - ocupadas}
          coinciden={coincidencias === null ? null : coincidencias.size}
        />
        <Leyenda />
      </div>

      <div className="flex flex-wrap items-start gap-4">
        <div className="min-w-0 flex-[999_1_560px]">
          <AreaDelArbol
            zoom={estado.zoom}
            onAlejar={estado.alejar}
            onAcercar={estado.acercar}
            onRestablecer={estado.restablecerZoom}
            llevarABusqueda={llevarABusqueda}
            llevarASeleccion={llevarASeleccion}
          >
            {arbol.raiz && <OrganigramaNodo nodo={arbol.raiz} contexto={contexto} />}
          </AreaDelArbol>
        </div>
        {!estrecha && panel && <div className="w-full flex-[1_1_320px] lg:max-w-85">{panel}</div>}
      </div>

      {estrecha && seleccionada && (
        <Modal open title={text.detail.regionLabel} onClose={estado.cerrarDetalle} closeLabel={text.detail.close} size="lg">
          {panel}
        </Modal>
      )}
    </>
  )
}
