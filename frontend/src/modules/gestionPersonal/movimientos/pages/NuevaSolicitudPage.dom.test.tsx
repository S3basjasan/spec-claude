// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { aplicable } from '../../../../test/politicasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerPoliticaAplicable } from '../../politicas/api'
import { listarMisRespaldos, presentarMiNovedad } from '../api'
import { NuevaSolicitudPage } from './NuevaSolicitudPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  presentarMiNovedad: vi.fn(),
  listarMisRespaldos: vi.fn(),
}))
vi.mock('../../politicas/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../../politicas/api')>()),
  obtenerPoliticaAplicable: vi.fn(),
}))

function renderPage() {
  return renderRoutes(
    <>
      <Route path="/mis-solicitudes/nueva" element={<NuevaSolicitudPage />} />
      <Route path="/mis-solicitudes" element={<p>Listado de mis solicitudes</p>} />
    </>,
    '/mis-solicitudes/nueva',
  )
}

async function completar(user: ReturnType<typeof userEvent.setup>, motivo = 'Trámite personal') {
  await user.type(await screen.findByLabelText('Fecha de inicio'), '2026-11-02')
  await user.type(screen.getByLabelText('Fecha de fin'), '2026-11-03')
  if (motivo) await user.type(screen.getByLabelText('Motivo o justificación'), motivo)
}

beforeEach(() => {
  vi.mocked(obtenerPoliticaAplicable).mockReset().mockResolvedValue(aplicable())
  vi.mocked(listarMisRespaldos).mockReset().mockResolvedValue([{ id: 40, titulo: 'Certificado médico' }])
  vi.mocked(presentarMiNovedad).mockReset().mockResolvedValue({ id: 5 } as never)
})

describe('NuevaSolicitudPage', () => {
  it('muestra las condiciones, la anticipación en días calendario y avisa que se fija una versión (US1.1)', async () => {
    renderPage()

    expect(await screen.findByRole('region', { name: 'Condiciones aplicables: Permisos' })).toBeInTheDocument()
    expect(screen.getByText('Anticipación mínima: 2 días calendario desde la presentación.')).toBeInTheDocument()
    expect(screen.getByText(/Al presentar se fija la versión de política vigente en ese momento/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Presentar solicitud' })).toBeEnabled()
  })

  it('«Política sin configurar» bloquea la presentación y no se confunde con la ausencia de requisitos (US1.4)', async () => {
    vi.mocked(obtenerPoliticaAplicable).mockResolvedValue(aplicable({ situacion: 'SinConfigurar', numero: null, parametros: null, inicio_vigencia: null }))
    renderPage()

    expect(await screen.findByText('Política sin configurar')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Presentar solicitud' })).toBeDisabled()
    expect(screen.getByText(/No se puede presentar hasta que exista una política vigente/)).toBeInTheDocument()
  })

  it('una política que no admite nuevas solicitudes y una falla al consultarla también bloquean, con mensajes distintos', async () => {
    vi.mocked(obtenerPoliticaAplicable).mockResolvedValue(aplicable({ situacion: 'NoAdmiteSolicitudes', parametros: { disponible: false } }))
    const sinSolicitudes = renderPage()
    expect(await screen.findByText('No admite nuevas solicitudes')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Presentar solicitud' })).toBeDisabled()
    sinSolicitudes.unmount()

    vi.mocked(obtenerPoliticaAplicable).mockRejectedValue(httpError(503, { code: 'POLITICA_NO_DISPONIBLE' }))
    renderPage()
    expect(await screen.findByText('No se pudieron consultar las condiciones', undefined, { timeout: 4000 })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Presentar solicitud' })).toBeDisabled()
  })

  it('valida fechas y la justificación exigida antes de enviar (US1.5)', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Presentar solicitud' }))
    expect((await screen.findAllByText('Ingresa una fecha válida (AAAA-MM-DD).')).length).toBe(2)
    expect(screen.getByText('Este dato es obligatorio.')).toBeInTheDocument()
    expect(presentarMiNovedad).not.toHaveBeenCalled()

    await user.type(screen.getByLabelText('Fecha de inicio'), '2026-11-05')
    await user.type(screen.getByLabelText('Fecha de fin'), '2026-11-01')
    await user.click(screen.getByRole('button', { name: 'Presentar solicitud' }))
    expect(await screen.findByText('La fecha de fin no puede ser anterior a la de inicio.')).toBeInTheDocument()
    expect(presentarMiNovedad).not.toHaveBeenCalled()
  })

  it('presenta con una clave de operación y vuelve a «Mis solicitudes» (US1.1)', async () => {
    const user = userEvent.setup()
    renderPage()
    await completar(user)

    await user.click(screen.getByRole('button', { name: 'Presentar solicitud' }))

    await waitFor(() => expect(presentarMiNovedad).toHaveBeenCalledTimes(1))
    const [datos, clave] = vi.mocked(presentarMiNovedad).mock.calls[0]
    expect(datos).toMatchObject({ tipo: 'Permiso', fecha_inicio: '2026-11-02', fecha_fin: '2026-11-03', motivo: 'Trámite personal', documento_ids: [] })
    expect(clave).toMatch(/^[0-9a-f-]{36}$/)
    expect(await screen.findByText('Listado de mis solicitudes')).toBeInTheDocument()
  })

  it('bloquea el envío mientras presenta y reutiliza la clave al reenviar lo mismo (US1.8)', async () => {
    const user = userEvent.setup()
    vi.mocked(presentarMiNovedad).mockRejectedValueOnce(httpError(500)).mockReturnValue(new Promise(() => {}))
    renderPage()
    await completar(user)

    await user.click(screen.getByRole('button', { name: 'Presentar solicitud' }))
    expect(await screen.findByText('Ocurrió un problema inesperado. Inténtalo de nuevo.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Presentar solicitud' }))

    await waitFor(() => expect(presentarMiNovedad).toHaveBeenCalledTimes(2))
    expect(vi.mocked(presentarMiNovedad).mock.calls[1][1]).toBe(vi.mocked(presentarMiNovedad).mock.calls[0][1])
    expect(await screen.findByRole('button', { name: /Presentar solicitud/ })).toBeDisabled()
  })

  it('un 422 por anticipación se explica y conserva lo escrito', async () => {
    const user = userEvent.setup()
    vi.mocked(presentarMiNovedad).mockRejectedValue(
      httpError(422, { code: 'ANTICIPACION_INSUFICIENTE', message: 'El inicio exige 2 días calendario.', errors: { fecha_inicio: ['El inicio exige 2 días calendario; la primera fecha posible es el 2026-11-04.'] } }),
    )
    renderPage()
    await completar(user)

    await user.click(screen.getByRole('button', { name: 'Presentar solicitud' }))

    expect(await screen.findByText('No se cumple la anticipación mínima.')).toBeInTheDocument()
    expect(screen.getByText(/la primera fecha posible es el 2026-11-04/)).toBeInTheDocument()
    expect(screen.getByLabelText('Fecha de inicio')).toHaveValue('2026-11-02')
    expect(screen.getByLabelText('Motivo o justificación')).toHaveValue('Trámite personal')
  })

  it('un conflicto explica, ofrece actualizar y no reenvía por su cuenta (FR-040)', async () => {
    const user = userEvent.setup()
    vi.mocked(presentarMiNovedad).mockRejectedValue(httpError(409, { code: 'SOLICITUD_MODIFICADA' }))
    renderPage()
    await completar(user)

    await user.click(screen.getByRole('button', { name: 'Presentar solicitud' }))

    expect(await screen.findByText('La solicitud cambió mientras la editabas')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Actualizar datos' })).toBeInTheDocument()
    expect(presentarMiNovedad).toHaveBeenCalledTimes(1)
  })

  it('con un documento exigido pide al menos un respaldo disponible y lo referencia (US1.5)', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerPoliticaAplicable).mockResolvedValue(aplicable({ parametros: { disponible: true, justificacion_obligatoria: false, documento_obligatorio: true, anticipacion_dias: 0 } }))
    renderPage()
    await completar(user, '')

    await user.click(screen.getByRole('button', { name: 'Presentar solicitud' }))
    expect(await screen.findByText('La política exige un documento de respaldo disponible.')).toBeInTheDocument()
    expect(presentarMiNovedad).not.toHaveBeenCalled()

    await user.click(await screen.findByRole('checkbox', { name: 'Certificado médico' }))
    await user.click(screen.getByRole('button', { name: 'Presentar solicitud' }))

    await waitFor(() => expect(presentarMiNovedad).toHaveBeenCalledTimes(1))
    expect(vi.mocked(presentarMiNovedad).mock.calls[0][0]).toMatchObject({ documento_ids: [40] })
  })

  it('una falla al consultar los respaldos no se presenta como «no hay documentos»', async () => {
    vi.mocked(listarMisRespaldos).mockRejectedValue(httpError(500))
    renderPage()

    const region = await screen.findByRole('group', { name: 'Respaldos' })
    expect(await within(region).findByText(/No se pudieron consultar los documentos/)).toBeInTheDocument()
    expect(within(region).queryByText(/No hay documentos disponibles/)).not.toBeInTheDocument()
  })
})
