// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { aplicable } from '../../../../test/politicasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerPoliticaAplicable } from '../../politicas/api'
import { buscarColaboradores, informarAusencia, listarRespaldosDeAdministrador, presentarNovedadComoAdministrador } from '../api'
import { RegistrarNovedadPage } from './RegistrarNovedadPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  buscarColaboradores: vi.fn(),
  listarRespaldosDeAdministrador: vi.fn(),
  informarAusencia: vi.fn(),
  presentarNovedadComoAdministrador: vi.fn(),
}))
vi.mock('../../politicas/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../../politicas/api')>()),
  obtenerPoliticaAplicable: vi.fn(),
}))

function renderPage(entrada: string) {
  return renderRoutes(
    <>
      <Route path="/movimientos-personal/novedad" element={<RegistrarNovedadPage />} />
      <Route path="/movimientos-personal/:id" element={<p>Detalle registrado</p>} />
    </>,
    entrada,
  )
}

async function elegirPersona(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('combobox', { name: 'Colaborador' }))
  await user.click(await screen.findByRole('option', { name: /Ana Núñez/ }))
}

beforeEach(() => {
  vi.mocked(obtenerPoliticaAplicable).mockReset().mockResolvedValue(aplicable({ tipo: 'ausencias', parametros: { disponible: true, justificacion_obligatoria: false, documento_obligatorio: false } }))
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue([{ id: 3, codigo: 'EMP-003', nombre: 'Ana Núñez' }])
  vi.mocked(listarRespaldosDeAdministrador).mockReset().mockResolvedValue([])
  vi.mocked(informarAusencia).mockReset().mockResolvedValue({ id: 30 } as never)
  vi.mocked(presentarNovedadComoAdministrador).mockReset().mockResolvedValue({ id: 31 } as never)
})

describe('RegistrarNovedadPage', () => {
  it('la ausencia se presenta como un hecho ocurrido, sin tipo elegible ni anticipación (US3.3)', async () => {
    renderPage('/movimientos-personal/novedad?tipo=Ausencia')

    expect(await screen.findByRole('heading', { name: 'Informar ausencia ocurrida' })).toBeInTheDocument()
    expect(screen.getByText(/Estás informando un hecho ya ocurrido/)).toBeInTheDocument()
    expect(screen.queryByLabelText('Tipo de solicitud')).not.toBeInTheDocument()
    expect(screen.queryByText(/Anticipación mínima/)).not.toBeInTheDocument()
    expect(screen.getByLabelText('Primer día de ausencia')).toBeInTheDocument()
  })

  it('informa la ausencia a nombre de la persona elegida y abre el registro (US3.1)', async () => {
    const user = userEvent.setup()
    renderPage('/movimientos-personal/novedad?tipo=Ausencia')
    await elegirPersona(user)
    await user.type(screen.getByLabelText('Primer día de ausencia'), '2026-10-19')
    await user.type(screen.getByLabelText('Último día de ausencia'), '2026-10-19')
    await user.type(screen.getByLabelText('Motivo o respaldo disponible'), 'Inasistencia sin aviso')

    await user.click(screen.getByRole('button', { name: 'Informar ausencia' }))

    await waitFor(() => expect(informarAusencia).toHaveBeenCalledTimes(1))
    const [colaboradorId, datos, clave] = vi.mocked(informarAusencia).mock.calls[0]
    expect(colaboradorId).toBe(3)
    expect(datos).toMatchObject({ fecha_inicio: '2026-10-19', fecha_fin: '2026-10-19', motivo: 'Inasistencia sin aviso' })
    expect(clave).toMatch(/^[0-9a-f-]{36}$/)
    expect(await screen.findByText('Detalle registrado')).toBeInTheDocument()
  })

  it('no deja registrar sin elegir a la persona y no confunde el rol del Administrador con el del Colaborador', async () => {
    const user = userEvent.setup()
    renderPage('/movimientos-personal/novedad')
    await user.type(await screen.findByLabelText('Fecha de inicio'), '2026-11-02')
    await user.type(screen.getByLabelText('Fecha de fin'), '2026-11-03')

    expect(screen.getByRole('button', { name: 'Registrar solicitud' })).toBeDisabled()
    expect(screen.getByRole('heading', { name: 'Registrar novedad en nombre del colaborador' })).toBeInTheDocument()

    await elegirPersona(user)
    await user.type(screen.getByLabelText('Motivo o justificación'), 'Trámite')
    await user.click(screen.getByRole('button', { name: 'Registrar solicitud' }))

    await waitFor(() => expect(presentarNovedadComoAdministrador).toHaveBeenCalledTimes(1))
    expect(vi.mocked(presentarNovedadComoAdministrador).mock.calls[0][0]).toBe(3)
  })
})
