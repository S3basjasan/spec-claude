// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalleDeMovimiento, detalleDeNovedad } from '../../../../test/movimientosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { ejecutarAccion, obtenerActuacion, type DetalleActuacion } from '../api'
import { ActuacionDetallePage } from './ActuacionDetallePage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerActuacion: vi.fn(),
  ejecutarAccion: vi.fn(),
}))
vi.mock('../../politicas', () => ({ CondicionesAplicables: () => <p>Condiciones conservadas</p> }))

function renderPage(id = 7) {
  return renderRoutes(<Route path="/movimientos-personal/:id" element={<ActuacionDetallePage />} />, `/movimientos-personal/${id}`)
}

function mostrar(detalle: DetalleActuacion) {
  vi.mocked(obtenerActuacion).mockReset().mockResolvedValue(detalle)
}

beforeEach(() => {
  vi.mocked(ejecutarAccion).mockReset().mockResolvedValue(detalleDeNovedad({ estado: 'Aprobada', version: 2 }))
  mostrar(detalleDeNovedad())
})

describe('ActuacionDetallePage', () => {
  it('muestra la solicitud, sus comprobaciones y la versión de política conservada (FR-038)', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Permiso' })).toBeInTheDocument()
    expect(screen.getByText('Ana Núñez (EMP-003)')).toBeInTheDocument()
    const comprobaciones = screen.getByRole('list', { name: 'Comprobaciones' })
    expect(within(comprobaciones).getAllByText('Cumple')).toHaveLength(2)
    expect(screen.getByText('Condiciones conservadas')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Aprobar' })).toBeInTheDocument()
  })

  it('sin evaluación verificable no muestra el control de aprobar y explica por qué (US4.4, FR-039)', async () => {
    mostrar(detalleDeMovimiento())
    renderPage(12)

    expect(await screen.findByText('Evaluación no disponible: no se puede aprobar hasta que exista evidencia verificable.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aprobar' })).not.toBeInTheDocument()
    expect(screen.getByText('No se puede aprobar: la evaluación frente al puesto destino no es verificable.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Rechazar' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Pasar a revisión' })).toBeInTheDocument()
    expect(screen.getByText('No verificable')).toBeInTheDocument()
    expect(screen.queryByText(/%/)).not.toBeInTheDocument()
  })

  it('una evaluación válida es evidencia, no aprobación: se ve y habilita decidir (US4.3)', async () => {
    mostrar(
      detalleDeMovimiento({
        puede_aprobar: true,
        motivo_no_aprobable: null,
        acciones: { revisar: true, rechazar: true, aprobar: true, retirar: false, revisar_ausencia: false, aplicar: false },
        comprobaciones: [{ clave: 'evaluacion', estado: 'Cumple', texto: 'Existe una evaluación válida frente al puesto destino; es evidencia para decidir, no una aprobación.' }],
        movimiento: {
          ...detalleDeMovimiento().movimiento!,
          evaluacion: { estado: 'Valida', motivo: null, consultada_en: null, contexto: { requisitos: ['Formación afín'], resultado: 'Apto con brechas', evaluada_en: '2026-10-01T10:00:00Z' } },
        },
      }),
    )
    renderPage(12)

    expect(await screen.findByText(/Evaluación válida: es evidencia para decidir/)).toBeInTheDocument()
    expect(screen.getByText('Formación afín')).toBeInTheDocument()
    expect(screen.getByText('Apto con brechas')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Aprobar' })).toBeInTheDocument()
    expect(screen.getByText(/Aprobar no equivale a aplicar/)).toBeInTheDocument()
  })

  it('aprobar exige la justificación y envía versión y clave de operación (US2.2)', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Aprobar' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Aprobar solicitud' })
    await user.click(within(dialogo).getByRole('button', { name: 'Aprobar' }))
    expect(await within(dialogo).findByText('Escribe la justificación.')).toBeInTheDocument()
    expect(ejecutarAccion).not.toHaveBeenCalled()

    await user.type(within(dialogo).getByLabelText('Justificación'), 'Cumple con lo solicitado')
    await user.click(within(dialogo).getByRole('button', { name: 'Aprobar' }))

    await waitFor(() => expect(ejecutarAccion).toHaveBeenCalledTimes(1))
    const [id, accion, cuerpo, clave] = vi.mocked(ejecutarAccion).mock.calls[0]
    expect([id, accion]).toEqual([7, 'aprobacion'])
    expect(cuerpo).toMatchObject({ version: 1, justificacion: 'Cumple con lo solicitado', reconoce_cruce: false })
    expect(clave).toMatch(/^[0-9a-f-]{36}$/)
    expect(await screen.findByText('Solicitud aprobada.')).toBeInTheDocument()
  })

  it('un cruce de períodos se señala y debe reconocerse para aprobar; rechazar no lo exige (US2.2b)', async () => {
    const user = userEvent.setup()
    mostrar(detalleDeNovedad({ cruces: [{ id: 5, tipo: 'Vacaciones', estado: 'Aprobada', inicio: '2026-10-25', fin: '2026-10-30' }] }))
    renderPage()

    expect(await screen.findByText(/Vacaciones \(Aprobada\) del 2026-10-25 al 2026-10-30/)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Aprobar' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Aprobar solicitud' })
    await user.type(within(dialogo).getByLabelText('Justificación'), 'Aprobada')
    await user.click(within(dialogo).getByRole('button', { name: 'Aprobar' }))
    expect(await within(dialogo).findByText('Debes reconocer el cruce para aprobar.')).toBeInTheDocument()
    expect(ejecutarAccion).not.toHaveBeenCalled()

    await user.click(within(dialogo).getByRole('checkbox'))
    await user.click(within(dialogo).getByRole('button', { name: 'Aprobar' }))
    await waitFor(() => expect(vi.mocked(ejecutarAccion).mock.calls[0]?.[2]).toMatchObject({ reconoce_cruce: true }))
  })

  it('un conflicto de edición explica, conserva lo escrito y no reenvía por su cuenta (FR-040)', async () => {
    const user = userEvent.setup()
    vi.mocked(ejecutarAccion).mockRejectedValue(httpError(409, { code: 'SOLICITUD_MODIFICADA', message: 'La solicitud cambió.' }))
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Rechazar' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Rechazar solicitud' })
    await user.type(within(dialogo).getByLabelText('Justificación'), 'Sin cobertura')
    await user.click(within(dialogo).getByRole('button', { name: 'Rechazar' }))

    expect((await screen.findAllByText(/Otra persona la modificó o ya se resolvió/)).length).toBeGreaterThan(0)
    expect(within(dialogo).getByLabelText('Justificación')).toHaveValue('Sin cobertura')
    expect(ejecutarAccion).toHaveBeenCalledTimes(1)
  })

  it('un movimiento programado no se presenta como efectuado y no ofrece aplicar antes de su fecha (US5.1)', async () => {
    mostrar(
      detalleDeMovimiento({
        estado: 'Programada',
        puede_aprobar: false,
        motivo_no_aprobable: 'ESTADO_NO_RESOLUBLE',
        comprobaciones: [],
        acciones: { revisar: false, rechazar: false, aprobar: false, retirar: false, revisar_ausencia: false, aplicar: false },
      }),
    )
    renderPage(12)

    expect(await screen.findByText('Pendiente de aplicar')).toBeInTheDocument()
    expect(screen.getByText('Todavía no se aplicó: la posición actual no cambió.')).toBeInTheDocument()
    expect(screen.queryByText(/Aplicada el/)).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aplicar ahora' })).not.toBeInTheDocument()
    expect(screen.getByText('No hay acciones disponibles.')).toBeInTheDocument()
  })

  it('una incidencia indica la condición que impidió el cambio y ofrece reintentar, no forzar (US5.3, US5.4)', async () => {
    const user = userEvent.setup()
    const base = detalleDeMovimiento()
    mostrar(
      detalleDeMovimiento({
        estado: 'IncidenciaDeAplicacion',
        comprobaciones: [],
        acciones: { revisar: false, rechazar: false, aprobar: false, retirar: false, revisar_ausencia: false, aplicar: true },
        movimiento: { ...base.movimiento!, aplicacion: { estado: 'Incidencia', aplicada_en: null, por_sistema: false, intentos: 1, incidencia: { codigo: 'DESTINO_OCUPADO', detalle: null } } },
      }),
    )
    vi.mocked(ejecutarAccion).mockRejectedValue(httpError(409, { code: 'INCIDENCIA_DE_APLICACION', incidencia: { codigo: 'DESTINO_OCUPADO', detalle: 'ocupada' } }))
    renderPage(12)

    expect(await screen.findByText('La posición destino está ocupada en el período del movimiento.')).toBeInTheDocument()
    expect(screen.getByText('No hubo cambios en la asignación. Puedes reintentar cuando la condición se cumpla.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /forzar/i })).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Reintentar aplicación' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Aplicar el movimiento' })
    await user.click(within(dialogo).getByRole('button', { name: 'Aplicar' }))

    expect(await within(dialogo).findByText(/sigue sin cumplirse/)).toBeInTheDocument()
    expect(vi.mocked(ejecutarAccion).mock.calls[0]?.[1]).toBe('aplicacion')
  })

  it('una ausencia informada se muestra como hecho ocurrido y se revisa o justifica (US3.2)', async () => {
    const user = userEvent.setup()
    mostrar(
      detalleDeNovedad({
        id: 30,
        tipo: 'Ausencia',
        estado: 'Informada',
        periodo: { inicio: '2026-10-19', fin: '2026-10-19', ocurrida: true },
        comprobaciones: [],
        puede_aprobar: false,
        acciones: { revisar: false, rechazar: false, aprobar: false, retirar: false, revisar_ausencia: true, aplicar: false },
      }),
    )
    renderPage(30)

    expect(await screen.findByText('Hecho ocurrido (no es una autorización)')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aprobar' })).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Revisar o justificar' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Revisar o justificar la ausencia' })
    await user.selectOptions(within(dialogo).getByLabelText('Resultado'), 'Justificada')
    await user.type(within(dialogo).getByLabelText('Observación'), 'Certificado médico')
    await user.click(within(dialogo).getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(ejecutarAccion).toHaveBeenCalledTimes(1))
    expect(vi.mocked(ejecutarAccion).mock.calls[0]?.[1]).toBe('revision-de-ausencia')
    expect(vi.mocked(ejecutarAccion).mock.calls[0]?.[2]).toMatchObject({ resultado: 'Justificada', observacion: 'Certificado médico' })
  })

  it('distingue una solicitud inexistente de un error de carga', async () => {
    vi.mocked(obtenerActuacion).mockReset().mockRejectedValue(httpError(404, { code: 'SOLICITUD_NO_ENCONTRADA' }))
    const noExiste = renderPage(99)
    expect(await screen.findByText('Solicitud no encontrada')).toBeInTheDocument()
    noExiste.unmount()

    vi.mocked(obtenerActuacion).mockReset().mockRejectedValue(httpError(500))
    renderPage(99)
    expect(await screen.findByText('No se pudo cargar la solicitud')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
  })
})
