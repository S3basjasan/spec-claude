import { useState } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { ConfirmDialog } from '../../../../shared/ui/ConfirmDialog'
import { IconPlus } from '../../../../shared/ui/icons'
import { SelectField } from '../../../../shared/ui/SelectField'
import type { Nivel, OpcionesRutas, RutaDetalle } from '../api'
import { useActualizarRuta } from '../hooks/useMutacionesRutas'
import { agregarNivel, cuerpoDeRuta, editarNivel, moverNivel, nivelesEnviables, quitarNivel } from '../niveles'
import { rutasText } from '../text'
import type { ErroresDe, CampoNivel } from '../validation'
import { DatosDelNivel } from './DatosDelNivel'
import { DetalleDeNivel } from './DetalleDeNivel'
import { EscaleraDeNiveles } from './EscaleraDeNiveles'

const t = rutasText.escalera

interface PestanaEscaleraProps {
  ruta: RutaDetalle
  opciones: OpcionesRutas | undefined
  /** Con el permiso de gestión se pueden agregar, reordenar, quitar y editar niveles. */
  puedeGestionar: boolean
  nivelSeleccionado: number | null
  onSeleccionar: (nivelId: number | null) => void
  onAviso: (mensaje: string) => void
  /** Otro administrador cambió la ruta (409): el editor la vuelve a leer. */
  onConflicto: () => void
}

/** Pestaña «Escalera de niveles» (RutasAdmin.dc.html): la escalera a la izquierda y el detalle del nivel elegido a la derecha. */
export function PestanaEscalera({ ruta, opciones, puedeGestionar, nivelSeleccionado, onSeleccionar, onAviso, onConflicto }: PestanaEscaleraProps) {
  const actualizar = useActualizarRuta(ruta.id)
  const [nuevoPuesto, setNuevoPuesto] = useState('')
  const [porQuitar, setPorQuitar] = useState<Nivel | null>(null)
  const [error, setError] = useState<string | undefined>()
  const [erroresNivel, setErroresNivel] = useState<ErroresDe<CampoNivel>>({})

  const niveles = ruta.niveles
  const seleccionado = niveles.find((n) => n.id === nivelSeleccionado) ?? niveles[0] ?? null
  const indiceSeleccionado = seleccionado === null ? -1 : niveles.findIndex((n) => n.id === seleccionado.id)
  const anterior = indiceSeleccionado > 0 ? niveles[indiceSeleccionado - 1] : null
  const usados = new Set(niveles.map((n) => n.puesto_id))
  const puestosLibres = (opciones?.puestos ?? []).filter((p) => !usados.has(p.id))

  const guardar = (nuevos: ReturnType<typeof nivelesEnviables>, mensaje: string, alExito?: () => void) => {
    setError(undefined)
    setErroresNivel({})
    actualizar.mutate(cuerpoDeRuta(ruta, nuevos), {
      onSuccess: () => {
        onAviso(mensaje)
        alExito?.()
      },
      onError: (e) => {
        const api = toApiError(e)
        if (api.status === 409) {
          onConflicto()
          return
        }
        setErroresNivel({ nombre: api.fieldErrors['niveles.0.nombre']?.[0] })
        setError(api.message ?? rutasText.formulario.errorGuardar)
      },
    })
  }

  const agregar = () => {
    const puesto = puestosLibres.find((p) => String(p.id) === nuevoPuesto)
    if (!puesto) {
      setError(t.elegirPuesto)
      return
    }
    guardar(agregarNivel(nivelesEnviables(ruta), puesto.id, puesto.nombre), t.nivelAgregado(niveles.length + 1, puesto.nombre), () => setNuevoPuesto(''))
  }

  const mover = (nivelId: number, direccion: 1 | -1) => {
    const indice = niveles.findIndex((n) => n.id === nivelId)
    guardar(moverNivel(nivelesEnviables(ruta), indice, direccion), t.ordenActualizado)
  }

  return (
    <div className="grid grid-cols-[repeat(auto-fit,minmax(340px,1fr))] items-start gap-6">
      <section aria-labelledby="escalera-titulo" className="flex flex-col gap-3">
        <h2 id="escalera-titulo" className="m-0 text-lg">
          {t.title}
        </h2>
        <p className="m-0 text-[13px] text-muted">{t.ayuda}</p>
        {error && porQuitar === null && <Alert>{error}</Alert>}
        {niveles.length === 0 && <p className="m-0 rounded-xl border border-dashed border-border p-4.5 text-sm text-muted">{t.vacio}</p>}
        <EscaleraDeNiveles
          niveles={niveles}
          seleccionado={seleccionado?.id ?? null}
          onSeleccionar={onSeleccionar}
          puedeGestionar={puedeGestionar}
          ocupado={actualizar.isPending}
          onMover={mover}
          onQuitar={setPorQuitar}
        />
        {puedeGestionar && (
          <div className="flex flex-col gap-2.5 rounded-xl border border-border bg-surface p-3.5">
            <SelectField
              id="agregar-puesto"
              label={t.agregar}
              value={nuevoPuesto}
              onChange={(e) => setNuevoPuesto(e.target.value)}
              options={[{ value: '', label: puestosLibres.length === 0 ? t.sinPuestos : t.elegirPuesto }, ...puestosLibres.map((p) => ({ value: String(p.id), label: `${p.nombre} (${p.codigo})` }))]}
            />
            <div>
              <Button variant="secondary" onClick={agregar} loading={actualizar.isPending} disabled={puestosLibres.length === 0}>
                <IconPlus size={16} />
                {t.agregarBoton}
              </Button>
            </div>
          </div>
        )}
      </section>

      <section aria-labelledby="detalle-nivel-titulo" className="flex flex-col gap-3.5">
        {seleccionado === null ? (
          <p className="m-0 rounded-xl border border-dashed border-border p-6 text-muted">{rutasText.detalle.seleccionar}</p>
        ) : (
          <>
            <DetalleDeNivel
              nivel={seleccionado}
              esPrimero={anterior === null}
              titulo={rutasText.detalle.titulo(seleccionado.orden, seleccionado.nombre)}
              subtitulo={rutasText.detalle.subtitulo(
                seleccionado.puesto.codigo,
                anterior === null ? rutasText.detalle.vsPartida : rutasText.detalle.vsNivel(anterior.orden, anterior.nombre),
              )}
            />
            {puedeGestionar && (
              <DatosDelNivel
                key={`${seleccionado.id}-${seleccionado.nombre}-${seleccionado.experiencia_minima_anios}`}
                nivel={seleccionado}
                pendiente={actualizar.isPending}
                errores={erroresNivel}
                onGuardar={(nombre, experiencia) => guardar(editarNivel(nivelesEnviables(ruta), seleccionado.id, nombre, experiencia), t.nivelEditado)}
              />
            )}
          </>
        )}
      </section>

      <ConfirmDialog
        open={porQuitar !== null}
        title={t.quitarTitulo}
        description={porQuitar ? t.quitarDescripcion(porQuitar.nombre) : ''}
        confirmLabel={t.quitarConfirmar}
        cancelLabel={rutasText.cancelar}
        busy={actualizar.isPending}
        error={error}
        onCancel={() => setPorQuitar(null)}
        onConfirm={() => {
          if (!porQuitar) return
          const quitado = porQuitar
          guardar(quitarNivel(nivelesEnviables(ruta), quitado.id), t.nivelQuitado(quitado.nombre), () => {
            setPorQuitar(null)
            if (nivelSeleccionado === quitado.id) onSeleccionar(null)
          })
        }}
      />
    </div>
  )
}
