import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { Breadcrumb } from '../../../../shared/ui/Breadcrumb'
import { CondicionesAplicables, usePoliticaAplicable } from '../../politicas'
import type { TipoDeNovedad } from '../api'
import { problemaDeActuacion, type ProblemaDeActuacion } from '../errores'
import { useInformarAusencia, usePresentarMiNovedad, usePresentarNovedadAdministrador } from '../hooks/useAccionesDeActuacion'
import { useMisRespaldos, useRespaldosDeAdministrador } from '../hooks/useActuaciones'
import { useClaveDeOperacion } from '../hooks/useClaveDeOperacion'
import { politicaDe, situacionDeFormulario } from '../politica'
import { movimientosText } from '../text'
import { AvisoDeSituacionDePolitica } from './AvisoDeSituacionDePolitica'
import { FormularioDeNovedad } from './FormularioDeNovedad'
import { SelectorDeColaborador } from './SelectorDeColaborador'

const text = movimientosText.novelty

interface PantallaDeNovedadProps {
  /** `colaborador` presenta lo propio; `administrador` registra por una persona o informa una ausencia. */
  rol: 'colaborador' | 'administrador'
  modo: 'novedad' | 'ausencia'
}

/**
 * Pantalla común de presentación: condiciones de la política aplicable a la vista, formulario del tipo elegido,
 * advertencia de versión fija y manejo de errores por causa. Conserva lo escrito ante validaciones y conflictos y
 * nunca reenvía una solicitud obsoleta por su cuenta.
 */
export function PantallaDeNovedad({ rol, modo }: PantallaDeNovedadProps) {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const esAdministrador = rol === 'administrador'
  const paramColaborador = Number(params.get('colaborador_id'))

  const [tipo, setTipo] = useState<TipoDeNovedad>('Permiso')
  const [colaboradorId, setColaboradorId] = useState<number | null>(Number.isInteger(paramColaborador) && paramColaborador > 0 ? paramColaborador : null)
  const [errorDePersona, setErrorDePersona] = useState<string | undefined>()
  const [problema, setProblema] = useState<ProblemaDeActuacion | null>(null)
  const claveDe = useClaveDeOperacion()

  const tipoDeActuacion = modo === 'ausencia' ? 'Ausencia' : tipo
  const politica = usePoliticaAplicable(politicaDe(tipoDeActuacion))
  const { situacion, requisitos } = situacionDeFormulario(politica)

  const respaldosAdmin = useRespaldosDeAdministrador(esAdministrador ? colaboradorId : null)
  const respaldosPropios = useMisRespaldos()
  const respaldos = esAdministrador ? respaldosAdmin : respaldosPropios

  const presentarPropia = usePresentarMiNovedad()
  const presentarAdmin = usePresentarNovedadAdministrador()
  const informar = useInformarAusencia()
  const pendiente = presentarPropia.isPending || presentarAdmin.isPending || informar.isPending

  const titulo = modo === 'ausencia' ? movimientosText.absence.title : esAdministrador ? text.title : text.titleOwn
  const subtitulo = modo === 'ausencia' ? movimientosText.absence.subtitle : esAdministrador ? text.subtitle : text.subtitleOwn
  const raiz = esAdministrador ? { label: movimientosText.list.title, to: '/movimientos-personal' } : { label: movimientosText.mine.title, to: '/mis-solicitudes' }

  const alFallar = (error: unknown) => setProblema(problemaDeActuacion(error))

  return (
    <div className="flex flex-col gap-5">
      <Breadcrumb
        label="Ruta de navegación"
        items={[raiz, { label: modo === 'ausencia' ? movimientosText.absence.title : esAdministrador ? text.breadcrumb : text.breadcrumbOwn }]}
      />
      <header>
        <h1 className="text-2xl font-bold">{titulo}</h1>
        <p className="text-muted">{subtitulo}</p>
      </header>

      <CondicionesAplicables tipo={politicaDe(tipoDeActuacion)} />
      <AvisoDeSituacionDePolitica situacion={situacion} />

      <div className="rounded-xl border border-border bg-surface p-5">
        <FormularioDeNovedad
          key={modo}
          modo={modo}
          tipo={tipo}
          onTipoChange={(nuevo) => {
            setTipo(nuevo)
            setProblema(null)
          }}
          requisitos={requisitos}
          bloqueado={situacion !== 'vigente' || (esAdministrador && colaboradorId === null)}
          respaldos={{ items: respaldos.data, cargando: respaldos.isPending && (!esAdministrador || colaboradorId !== null), fallo: respaldos.isError }}
          pendiente={pendiente}
          error={problema?.mensaje}
          conflicto={problema?.conflicto}
          onActualizar={() => setProblema(null)}
          erroresDelServidor={problema?.campos}
          etiquetaEnviar={modo === 'ausencia' ? movimientosText.absence.submit : esAdministrador ? text.submitAdmin : text.submit}
          onSubmit={(valores) => {
            setProblema(null)
            if (esAdministrador && colaboradorId === null) {
              setErrorDePersona(movimientosText.validation.personRequired)
              return
            }
            const datos = {
              tipo: valores.tipo,
              fecha_inicio: valores.fecha_inicio,
              fecha_fin: valores.fecha_fin,
              motivo: valores.motivo,
              documento_ids: valores.documento_ids,
            }
            const clave = claveDe(JSON.stringify([rol, modo, colaboradorId, datos]))

            if (modo === 'ausencia') {
              informar.mutate(
                { colaboradorId: colaboradorId as number, datos: { fecha_inicio: datos.fecha_inicio, fecha_fin: datos.fecha_fin, motivo: datos.motivo, documento_ids: datos.documento_ids }, clave },
                { onSuccess: (r) => navigate(`/movimientos-personal/${r.id}`, { state: { aviso: movimientosText.absence.success } }), onError: alFallar },
              )
            } else if (esAdministrador) {
              presentarAdmin.mutate(
                { colaboradorId: colaboradorId as number, datos, clave },
                { onSuccess: (r) => navigate(`/movimientos-personal/${r.id}`, { state: { aviso: text.success } }), onError: alFallar },
              )
            } else {
              presentarPropia.mutate(
                { datos, clave },
                { onSuccess: () => navigate('/mis-solicitudes', { state: { aviso: text.successOwn } }), onError: alFallar },
              )
            }
          }}
        >
          {esAdministrador && (
            <SelectorDeColaborador
              id="novedad-colaborador"
              label={text.person}
              placeholder={text.personPlaceholder}
              valor={colaboradorId}
              onChange={(id) => {
                setColaboradorId(id)
                setErrorDePersona(undefined)
              }}
              error={errorDePersona}
            />
          )}
        </FormularioDeNovedad>
      </div>
    </div>
  )
}
