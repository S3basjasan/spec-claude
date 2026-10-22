import { useState } from 'react'
import { Link } from 'react-router-dom'
import { EmptyState } from '../../../../shared/ui/EmptyState'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { IconStairs } from '../../../../shared/ui/icons'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import type { InscripcionPropia, RutaDisponible } from '../api'
import { AvisoDeFuentes } from '../components/AvisoDeFuentes'
// import { AvisoGuia } from '../components/AvisoGuia'
import { DetalleDeNivel } from '../components/DetalleDeNivel'
import { EscaleraColaborador } from '../components/EscaleraColaborador'
import { EtiquetaTipo } from '../components/Etiquetas'
import { useMiRuta } from '../hooks/useRutas'
import { rutasText } from '../text'

const t = rutasText.miRuta

function RutaPropia({ inscripcion }: { inscripcion: InscripcionPropia }) {
  const { niveles, nivel_actual_id: actualId, nivel_objetivo_id: objetivoId, siguiente_nivel_id: siguienteId } = inscripcion
  const inicial = objetivoId ?? siguienteId ?? actualId ?? niveles[0]?.id ?? null
  const [elegido, setElegido] = useState<number | null>(inicial)
  const indice = niveles.findIndex((n) => n.id === elegido)
  const nivel = indice >= 0 ? niveles[indice] : null
  const anterior = indice > 0 ? niveles[indice - 1] : null

  const titulo = nivel === null ? '' : nivel.id === objetivoId ? t.detalleObjetivo(nivel.nombre) : nivel.id === siguienteId ? t.detalleSiguiente(nivel.nombre) : rutasText.detalle.titulo(nivel.orden, nivel.nombre)
  const subtitulo = nivel === null ? '' : rutasText.detalle.subtitulo(nivel.puesto.codigo, anterior === null ? rutasText.detalle.vsPartida : rutasText.detalle.vsNivel(anterior.orden, anterior.nombre))

  return (
    <section aria-labelledby={`ruta-${inscripcion.inscripcion_id}`} className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2.5">
          <h2 id={`ruta-${inscripcion.inscripcion_id}`} className="m-0 text-xl">
            {inscripcion.ruta.nombre}
          </h2>
          <EtiquetaTipo tipo={inscripcion.ruta.tipo} />
          <span className="text-sm text-muted">{inscripcion.ruta.codigo}</span>
        </div>
        {inscripcion.puede_ver_adecuacion && objetivoId !== null && (
          <Link to="/adecuacion" className="inline-flex min-h-11 items-center rounded-lg bg-accent px-5 text-[15px] font-semibold text-onaccent no-underline">
            {t.verAdecuacion}
          </Link>
        )}
      </div>

      {objetivoId === null && <p className="m-0 text-sm text-muted">{t.sinObjetivo}</p>}
      {actualId !== null && siguienteId === null && objetivoId === null && (
        <p className="m-0 text-sm font-semibold">
          {t.nivelMasAlto} <span className="font-normal text-muted">· {t.sinSiguiente}</span>
        </p>
      )}

      <div className="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] items-start gap-6">
        <div className="flex flex-col gap-2.5">
          <h3 className="m-0 text-base">{t.escalera}</h3>
          <EscaleraColaborador niveles={niveles} actualId={actualId} objetivoId={objetivoId} siguienteId={siguienteId} seleccionado={elegido} onSeleccionar={setElegido} />
        </div>
        <div>
          {nivel === null ? <p className="m-0 rounded-xl border border-dashed border-border p-6 text-muted">{t.elegirNivel}</p> : <DetalleDeNivel nivel={nivel} esPrimero={anterior === null} titulo={titulo} subtitulo={subtitulo} />}
        </div>
      </div>
    </section>
  )
}

function RutasDisponibles({ rutas }: { rutas: RutaDisponible[] }) {
  return (
    <section aria-labelledby="rutas-disponibles" className="flex flex-col gap-3">
      <div>
        <h2 id="rutas-disponibles" className="m-0 text-xl">
          {t.disponibles.title}
        </h2>
        <p className="mt-1 text-sm text-muted">{t.disponibles.descripcion}</p>
      </div>
      {rutas.length === 0 ? (
        <p className="m-0 rounded-xl border border-dashed border-border p-5 text-muted">{t.disponibles.vacio}</p>
      ) : (
        <ul className="m-0 grid list-none grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-4 p-0">
          {rutas.map((r) => (
            <li key={r.id} className="flex flex-col gap-2 rounded-xl border border-border bg-surface px-4 py-3.5">
              <div className="flex items-start justify-between gap-2">
                <h3 className="m-0 text-base leading-snug">{r.nombre}</h3>
                <EtiquetaTipo tipo={r.tipo} />
              </div>
              <div className="text-sm text-muted">
                {r.codigo} · {r.niveles_count} {rutasText.lista.tarjeta.nivelPalabra(r.niveles_count)}
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

/** «Mi ruta de carrera» (RutasColaborador.dc.html): la escalera de cada ruta propia, lo que se exige y las rutas disponibles. */
export function MiRutaPage() {
  const mi = useMiRuta()

  return (
    <div className="mx-auto flex max-w-282 flex-col gap-6">
      <div>
        {/* <p className="m-0 mb-1 text-[13px] font-semibold text-accent">{t.eyebrow}</p> */}
        <h1 className="m-0 text-[28px] font-bold leading-tight tracking-tight">{t.title}</h1>
        <p className="mt-1.5 text-base text-muted">{t.subtitle}</p>
      </div>

      {/* <AvisoGuia /> */}

      {mi.isPending ? (
        <div role="status" aria-label={rutasText.loading} className="flex flex-col gap-3">
          <Skeleton className="h-40" />
          <Skeleton className="h-24" />
        </div>
      ) : mi.isError ? (
        <ErrorState title={rutasText.error.title} description={rutasText.error.description} retryLabel={rutasText.error.retry} onRetry={() => void mi.refetch()} />
      ) : (
        <>
          <AvisoDeFuentes fuentes={mi.data.fuentes_no_disponibles} onReintentar={() => void mi.refetch()} />
          {mi.data.inscripciones.length === 0 ? (
            <EmptyState icon={<IconStairs size={24} />} title={t.vacio.title} description={t.vacio.description} />
          ) : (
            mi.data.inscripciones.map((i) => <RutaPropia key={i.inscripcion_id} inscripcion={i} />)
          )}
          <RutasDisponibles rutas={mi.data.rutas_disponibles} />
        </>
      )}
    </div>
  )
}
