// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { borrador, detalle, detalleSinConfigurar, version } from '../../../../test/politicasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { crearBorrador, obtenerPolitica, retirarVersion } from '../api'
import { PoliticaDetallePage } from './PoliticaDetallePage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPolitica: vi.fn(),
  crearBorrador: vi.fn(),
  retirarVersion: vi.fn(),
  compararVersion: vi.fn().mockResolvedValue({ contra: null, diferencias: [] }),
}))

function renderDetalle(tipo = 'permisos') {
  return renderRoutes(
    <>
      <Route path="/politicas-personal/:tipo" element={<PoliticaDetallePage />} />
      <Route path="/politicas-personal/:tipo/borrador" element={<p>Editor de borrador</p>} />
      <Route path="/politicas-personal" element={<p>Lista de políticas</p>} />
    </>,
    `/politicas-personal/${tipo}`,
  )
}

const programada = version({ id: 11, numero: 2, estado: 'Programada', inicio_vigencia_local: '2026-11-05T08:00' })

beforeEach(() => {
  vi.mocked(obtenerPolitica).mockReset().mockResolvedValue(detalle())
  vi.mocked(crearBorrador).mockReset().mockResolvedValue(borrador())
  vi.mocked(retirarVersion).mockReset().mockResolvedValue({ ...programada, estado: 'Retirada', retiro: { por: 1, en: '2026-10-20T10:00:00Z', motivo: 'Motivo' } })
})

describe('PoliticaDetallePage', () => {
  it('muestra las condiciones vigentes, las reglas fijas y el historial de versiones', async () => {
    renderDetalle()

    expect(await screen.findByRole('heading', { name: 'Permisos', level: 1 })).toBeInTheDocument()
    const vigente = screen.getByRole('region', { name: 'Condiciones vigentes' })
    expect(within(vigente).getByText(/Versión 1/)).toBeInTheDocument()
    expect(within(vigente).getByText('2 días calendario')).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Reglas fijas' })).toBeInTheDocument()
    expect(screen.getByRole('list', { name: /versiones publicadas/i })).toBeInTheDocument()
  })

  it('«Política sin configurar» se distingue de una política que no admite solicitudes', async () => {
    vi.mocked(obtenerPolitica).mockResolvedValue(detalleSinConfigurar())
    const { unmount } = renderDetalle()
    expect(await screen.findByText('Política sin configurar', { selector: 'p' })).toBeInTheDocument()
    expect(screen.getByText(/no pueden presentar solicitudes/i)).toBeInTheDocument()
    unmount()

    const base = detalle()
    vi.mocked(obtenerPolitica).mockResolvedValue({ ...base, resumen: { ...base.resumen, situacion: 'NoAdmiteSolicitudes', disponible: false } })
    renderDetalle()
    expect(await screen.findByText(/la versión vigente no permite nuevas solicitudes/i)).toBeInTheDocument()
    expect(screen.queryByText('Política sin configurar', { selector: 'p' })).not.toBeInTheDocument()
  })

  it('muestra la versión programada y avisa que hasta entonces sigue la vigente', async () => {
    vi.mocked(obtenerPolitica).mockResolvedValue(detalle('permisos', { programada, versiones: [programada, version()] }))
    renderDetalle()

    const region = await screen.findByRole('region', { name: 'Versión programada' })
    expect(within(region).getByText(/comenzará el 05\/11\/2026 08:00/i)).toBeInTheDocument()
    expect(within(region).getByText(/sigue aplicando la vigente/i)).toBeInTheDocument()
  })

  it('crear borrador abre el editor', async () => {
    const user = userEvent.setup()
    renderDetalle()

    await user.click(await screen.findByRole('button', { name: 'Crear borrador' }))

    await waitFor(() => expect(crearBorrador).toHaveBeenCalledWith('permisos', undefined))
    expect(await screen.findByText('Editor de borrador')).toBeInTheDocument()
  })

  it('con un borrador existente ofrece continuarlo en lugar de crear otro', async () => {
    vi.mocked(obtenerPolitica).mockResolvedValue(detalle('permisos', { borrador: borrador({ descripcion: 'En preparación' }) }))
    renderDetalle()

    expect(await screen.findByRole('link', { name: 'Continuar borrador' })).toHaveAttribute('href', '/politicas-personal/permisos/borrador')
    expect(screen.queryByRole('button', { name: 'Crear borrador' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Crear borrador a partir de esta versión' })).toBeDisabled()
  })

  it('crear a partir de una versión envía la versión base', async () => {
    const user = userEvent.setup()
    renderDetalle()

    await user.click(await screen.findByRole('button', { name: 'Crear borrador a partir de esta versión' }))

    await waitFor(() => expect(crearBorrador).toHaveBeenCalledWith('permisos', 10))
  })

  it('un 409 por borrador existente lo explica sin salir de la página', async () => {
    const user = userEvent.setup()
    vi.mocked(crearBorrador).mockRejectedValueOnce(httpError(409, { code: 'BORRADOR_EXISTENTE', errors: { borrador_id: 20 } }))
    renderDetalle()

    await user.click(await screen.findByRole('button', { name: 'Crear borrador' }))

    expect(await screen.findByText(/ya existe un borrador para este tipo/i)).toBeInTheDocument()
  })

  it('retirar una versión programada pide justificación y confirma que la anterior continúa', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerPolitica).mockResolvedValue(detalle('permisos', { programada, versiones: [programada, version()] }))
    renderDetalle()

    await user.click(await screen.findByRole('button', { name: 'Retirar versión' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Retirar versión programada' })
    await user.type(within(dialogo).getByLabelText('Justificación del retiro'), 'Motivo del retiro')
    await user.click(within(dialogo).getByRole('button', { name: 'Retirar versión' }))

    await waitFor(() => expect(retirarVersion).toHaveBeenCalledWith('permisos', 11, 'Motivo del retiro', expect.any(String)))
    expect(await screen.findByText(/versión 2 retirada\. la versión anterior continúa aplicándose/i)).toBeInTheDocument()
  })

  it('un tipo desconocido o un 404 muestran «la política no existe»', async () => {
    vi.mocked(obtenerPolitica).mockRejectedValue(httpError(404))
    renderDetalle()

    expect(await screen.findByText('La política no existe')).toBeInTheDocument()
  })

  it('un error muestra el estado de error con Reintentar', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerPolitica).mockRejectedValue(httpError(500))
    renderDetalle()

    expect(await screen.findByText('No se pudo cargar la política', {}, { timeout: 4000 })).toBeInTheDocument()
    vi.mocked(obtenerPolitica).mockResolvedValue(detalle())
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('heading', { name: 'Permisos', level: 1 })).toBeInTheDocument()
  })
})
