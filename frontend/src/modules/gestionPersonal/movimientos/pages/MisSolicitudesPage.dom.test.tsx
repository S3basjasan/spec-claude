// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { miNovedad } from '../../../../test/movimientosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarMisSolicitudes, retirarMiNovedad } from '../api'
import { MisSolicitudesPage } from './MisSolicitudesPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarMisSolicitudes: vi.fn(),
  retirarMiNovedad: vi.fn(),
}))

function renderPage() {
  return renderRoutes(<Route path="/mis-solicitudes" element={<MisSolicitudesPage />} />, '/mis-solicitudes')
}

beforeEach(() => {
  vi.mocked(listarMisSolicitudes).mockReset().mockResolvedValue([miNovedad()])
  vi.mocked(retirarMiNovedad).mockReset().mockResolvedValue(miNovedad({ estado: 'Retirada', retirable: false }))
})

describe('MisSolicitudesPage', () => {
  it('muestra el estado, el período, las condiciones conservadas y el resultado comunicable (US2.3)', async () => {
    vi.mocked(listarMisSolicitudes).mockResolvedValue([
      miNovedad({ id: 1, estado: 'Rechazada', retirable: false, resultado: { sentido: 'Rechazada', justificacion: 'Sin cobertura en esas fechas', resuelta_en: '2026-10-22T10:00:00Z' } }),
      miNovedad({ id: 2, tipo: 'Vacaciones', estado: 'Aprobada', retirable: false, resultado: { sentido: 'Aprobada', justificacion: null, resuelta_en: null } }),
    ])
    renderPage()

    const tabla = await screen.findByRole('table', { name: 'Mis solicitudes' })
    expect(within(tabla).getByText('Rechazada', { selector: 'span.rounded-full' })).toBeInTheDocument()
    expect(within(tabla).getByText(/Razón: Sin cobertura en esas fechas/)).toBeInTheDocument()
    expect(within(tabla).getAllByText('Se conserva la versión 2 de la política con la que presentaste.')).toHaveLength(2)
    expect(within(tabla).getAllByText(/Justificación obligatoria · Anticipación de 2 días calendario/)).toHaveLength(2)
    expect(within(tabla).queryByRole('button', { name: 'Retirar' })).not.toBeInTheDocument()
  })

  it('de un movimiento comunicado solo muestra su resultado y su fecha efectiva (FR-037)', async () => {
    vi.mocked(listarMisSolicitudes).mockResolvedValue([{ id: 9, tipo: 'Ascenso', categoria: 'Movimiento', estado: 'Programada', fecha_efectiva: '2026-12-01' }])
    renderPage()

    const tabla = await screen.findByRole('table', { name: 'Mis solicitudes' })
    expect(within(tabla).getByText('Ascenso')).toBeInTheDocument()
    expect(within(tabla).getByText('Programada', { selector: 'span.rounded-full' })).toBeInTheDocument()
    expect(within(tabla).getByText('2026-12-01')).toBeInTheDocument()
    expect(within(tabla).getByText('Movimiento comunicado: solo se muestran su resultado y su fecha efectiva.')).toBeInTheDocument()
    expect(within(tabla).queryByText(/evaluación|incidencia|propuesta/i)).not.toBeInTheDocument()
  })

  it('distingue vacío, error de carga y cuenta sin ficha asociada', async () => {
    vi.mocked(listarMisSolicitudes).mockResolvedValue([])
    const vacio = renderPage()
    expect(await screen.findByText('Sin solicitudes')).toBeInTheDocument()
    vacio.unmount()

    vi.mocked(listarMisSolicitudes).mockRejectedValue(httpError(500))
    const error = renderPage()
    expect(await screen.findByText('No se pudieron cargar tus solicitudes')).toBeInTheDocument()
    expect(screen.queryByText('Sin solicitudes')).not.toBeInTheDocument()
    error.unmount()

    vi.mocked(listarMisSolicitudes).mockRejectedValue(httpError(404, { code: 'SIN_FICHA_ASOCIADA' }))
    renderPage()
    expect(await screen.findByText('Sin ficha asociada')).toBeInTheDocument()
    expect(screen.queryByText('No se pudieron cargar tus solicitudes')).not.toBeInTheDocument()
  })

  it('retira una solicitud presentada tras confirmar, enviando versión y clave (US2.9)', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Retirar' }))
    const dialogo = await screen.findByRole('alertdialog', { name: 'Retirar solicitud' })
    await user.click(within(dialogo).getByRole('button', { name: 'Retirar' }))

    await waitFor(() => expect(retirarMiNovedad).toHaveBeenCalledTimes(1))
    const [id, version, clave] = vi.mocked(retirarMiNovedad).mock.calls[0]
    expect([id, version]).toEqual([21, 1])
    expect(clave).toMatch(/^[0-9a-f-]{36}$/)
    expect(await screen.findByText('Solicitud retirada.')).toBeInTheDocument()
  })

  it('si el retiro choca con una resolución lo explica y no reintenta (US2.10)', async () => {
    const user = userEvent.setup()
    vi.mocked(retirarMiNovedad).mockRejectedValue(httpError(409, { code: 'SOLICITUD_MODIFICADA' }))
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Retirar' }))
    const dialogo = await screen.findByRole('alertdialog', { name: 'Retirar solicitud' })
    await user.click(within(dialogo).getByRole('button', { name: 'Retirar' }))

    expect(await within(dialogo).findByText(/Otra persona la modificó o ya se resolvió/)).toBeInTheDocument()
    expect(retirarMiNovedad).toHaveBeenCalledTimes(1)
  })
})
