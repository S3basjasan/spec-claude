// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { networkError } from '../../../../test/apiErrors'
import { paginaHistorial, renglonHistorial } from '../../../../test/planFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarHistorial } from '../api'
import { HistorialPlan } from './HistorialPlan'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarHistorial: vi.fn(),
}))

function renderHistorial() {
  return renderRoutes(<Route path="/" element={<HistorialPlan planId={1} />} />, '/')
}

beforeEach(() => {
  vi.mocked(listarHistorial).mockReset().mockResolvedValue(paginaHistorial([]))
})

describe('HistorialPlan', () => {
  it('muestra cada tipo de renglón con usuario, fecha y detalle', async () => {
    vi.mocked(listarHistorial).mockResolvedValue(
      paginaHistorial([
        renglonHistorial({ id: 4, tipo: 'Cancelacion', accion_id: 7, accion_nombre: 'Análisis con SQL', valor_anterior: null, valor_nuevo: 'Cancelada', motivo: 'Sin cupo' }),
        renglonHistorial({ id: 3, tipo: 'Participante', accion_id: 7, accion_nombre: 'Análisis con SQL', valor_anterior: null, valor_nuevo: 'Ana Pérez' }),
        renglonHistorial({ id: 2, tipo: 'Participante', accion_id: 7, accion_nombre: 'Análisis con SQL', valor_anterior: 'Luis Mora', valor_nuevo: null }),
        renglonHistorial({ id: 5, tipo: 'Aprobador', valor_anterior: 'Marta Ríos', valor_nuevo: 'Esteban Rivas' }),
        renglonHistorial({ id: 1, tipo: 'Estado', valor_anterior: 'Revision', valor_nuevo: 'Borrador', motivo: 'Falta detalle' }),
      ]),
    )
    renderHistorial()

    const lista = await screen.findByRole('list')
    const renglones = within(lista).getAllByRole('listitem')
    expect(renglones).toHaveLength(5)
    expect(within(renglones[0]).getByText('Cancelación')).toBeInTheDocument()
    expect(within(renglones[0]).getByText('Motivo: Sin cupo')).toBeInTheDocument()
    expect(within(renglones[0]).getByText('Acción «Análisis con SQL»')).toBeInTheDocument()
    expect(within(renglones[1]).getByText('Participante agregado: Ana Pérez')).toBeInTheDocument()
    expect(within(renglones[2]).getByText('Participante quitado: Luis Mora')).toBeInTheDocument()
    expect(within(renglones[3]).getByText('Marta Ríos → Esteban Rivas')).toBeInTheDocument()
    expect(within(renglones[4]).getByText('Revisión → Borrador')).toBeInTheDocument()
    expect(within(renglones[4]).getByText('Motivo: Falta detalle')).toBeInTheDocument()
    expect(within(renglones[4]).getByText(/Marta Ríos · 16 oct 2026/)).toBeInTheDocument()
  })

  it('muestra el estado vacío', async () => {
    renderHistorial()

    expect(await screen.findByText('Aún no hay cambios registrados.')).toBeInTheDocument()
  })

  it('muestra un error con reintento', async () => {
    vi.mocked(listarHistorial).mockRejectedValueOnce(networkError())
    renderHistorial()

    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Aún no hay cambios registrados.')).toBeInTheDocument()
  })

  it('pagina el historial', async () => {
    vi.mocked(listarHistorial).mockResolvedValue({ data: [renglonHistorial()], meta: { page: 1, per_page: 10, total: 25, last_page: 3 } })
    renderHistorial()

    await userEvent.click(await screen.findByRole('button', { name: /Siguiente/ }))

    expect(listarHistorial).toHaveBeenLastCalledWith(1, 2, 10)
  })
})
