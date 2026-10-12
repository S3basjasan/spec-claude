// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { competencia, detalle, pagina } from '../../../../test/competenciasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { cambiarEstadoCompetencia, listarCompetencias } from '../api'
import { CompetenciasPage } from './CompetenciasPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarCompetencias: vi.fn(),
  obtenerCompetencia: vi.fn(),
  cambiarEstadoCompetencia: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

const activas = [competencia({ id: 1, nombre: 'Liderazgo' }), competencia({ id: 2, codigo: 'COMP-0002', nombre: 'Adaptabilidad' })]
const inactivas = [competencia({ id: 3, codigo: 'COMP-0003', nombre: 'Retirada', estado: 'Inactivo' })]

function renderComoAdmin(entrada = '/competencias') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role: 'admin' } } as ReturnType<typeof useCurrentUser>)
  renderRoutes(<Route path="/competencias" element={<CompetenciasPage />} />, entrada)
}

async function pulsarDesactivarLiderazgo() {
  const user = userEvent.setup()
  await screen.findByText('Liderazgo')
  await user.click(screen.getByRole('button', { name: 'Desactivar Liderazgo' }))
  return user
}

beforeEach(() => {
  vi.mocked(listarCompetencias).mockReset().mockResolvedValue(pagina(activas))
  vi.mocked(cambiarEstadoCompetencia).mockReset().mockResolvedValue(detalle({ estado: 'Inactivo' }))
})

describe('CompetenciasPage (activar y desactivar)', () => {
  it('desactivar pide confirmación nombrando la competencia e informando la consecuencia', async () => {
    renderComoAdmin()
    await pulsarDesactivarLiderazgo()

    const dialogo = screen.getByRole('alertdialog')
    expect(dialogo).toHaveAccessibleName('¿Desactivar la competencia «Liderazgo»?')
    expect(dialogo).toHaveAccessibleDescription(/no podrá asignarse en nuevos registros/)
    expect(cambiarEstadoCompetencia).not.toHaveBeenCalled()
  })

  it('cancelar no cambia nada', async () => {
    renderComoAdmin()
    const user = await pulsarDesactivarLiderazgo()

    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(cambiarEstadoCompetencia).not.toHaveBeenCalled()
  })

  it('confirmar desactiva, cierra el diálogo, avisa y refresca el listado', async () => {
    renderComoAdmin()
    const user = await pulsarDesactivarLiderazgo()

    await user.click(screen.getByRole('button', { name: 'Desactivar competencia' }))

    expect(await screen.findByText('Competencia desactivada.')).toBeInTheDocument()
    expect(cambiarEstadoCompetencia).toHaveBeenCalledWith(1, 'Inactivo')
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    await waitFor(() => expect(listarCompetencias).toHaveBeenCalledTimes(2))
  })

  it('activar actúa de inmediato, sin diálogo de confirmación', async () => {
    vi.mocked(listarCompetencias).mockResolvedValue(pagina(inactivas))
    vi.mocked(cambiarEstadoCompetencia).mockResolvedValue(detalle({ id: 3, estado: 'Activo' }))
    renderComoAdmin('/competencias?estado=Inactivo')
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Activar Retirada' }))

    expect(await screen.findByText('Competencia activada.')).toBeInTheDocument()
    expect(cambiarEstadoCompetencia).toHaveBeenCalledWith(3, 'Activo')
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('si ya estaba en ese estado (422) cierra el diálogo, avisa y refresca', async () => {
    vi.mocked(cambiarEstadoCompetencia).mockRejectedValue(httpError(422, { errors: { estado: ['ya'] } }))
    renderComoAdmin()
    const user = await pulsarDesactivarLiderazgo()

    await user.click(screen.getByRole('button', { name: 'Desactivar competencia' }))

    expect(await screen.findByText('La competencia ya estaba en ese estado.')).toBeInTheDocument()
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    await waitFor(() => expect(listarCompetencias).toHaveBeenCalledTimes(2))
  })

  it('si ya no existe (404) lo informa', async () => {
    vi.mocked(cambiarEstadoCompetencia).mockRejectedValue(httpError(404))
    renderComoAdmin()
    const user = await pulsarDesactivarLiderazgo()

    await user.click(screen.getByRole('button', { name: 'Desactivar competencia' }))

    expect(await screen.findByText('La competencia ya no existe.')).toBeInTheDocument()
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('ante un error inesperado mantiene el diálogo con el motivo', async () => {
    vi.mocked(cambiarEstadoCompetencia).mockRejectedValue(httpError(500))
    renderComoAdmin()
    const user = await pulsarDesactivarLiderazgo()

    await user.click(screen.getByRole('button', { name: 'Desactivar competencia' }))

    expect(await screen.findByText('No se pudo desactivar la competencia. Inténtalo de nuevo.')).toBeInTheDocument()
    expect(screen.getByRole('alertdialog')).toBeInTheDocument()
  })

  it('bloquea los botones del diálogo mientras guarda y no envía dos veces', async () => {
    vi.mocked(cambiarEstadoCompetencia).mockReturnValue(new Promise(() => {}))
    renderComoAdmin()
    const user = await pulsarDesactivarLiderazgo()

    await user.click(screen.getByRole('button', { name: 'Desactivar competencia' }))

    await waitFor(() => expect(screen.getByRole('button', { name: 'Desactivar competencia' })).toBeDisabled())
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeDisabled()
    expect(cambiarEstadoCompetencia).toHaveBeenCalledOnce()
  })

  it('un error al activar muestra un aviso sin diálogo', async () => {
    vi.mocked(listarCompetencias).mockResolvedValue(pagina(inactivas))
    vi.mocked(cambiarEstadoCompetencia).mockRejectedValue(httpError(500))
    renderComoAdmin('/competencias?estado=Inactivo')

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Activar Retirada' }))

    expect(await screen.findByText('No se pudo cambiar el estado de la competencia. Inténtalo de nuevo.')).toBeInTheDocument()
  })
})
