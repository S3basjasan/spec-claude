// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { detalle } from '../../../../test/fichasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerColaborador } from '../api'
import { FichaDetallePage } from './FichaDetallePage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerColaborador: vi.fn(),
}))

function renderDetalle() {
  return renderRoutes(<Route path="/colaboradores/:colaboradorId" element={<FichaDetallePage />} />, '/colaboradores/1')
}

const capacidades = { desarrollo: false, documentos: false, registrar_ingreso: true, exportar: false }

beforeEach(() => {
  vi.mocked(obtenerColaborador).mockReset().mockResolvedValue(detalle({ capacidades }))
})

describe('FichaDetallePage: ingresos', () => {
  it('ofrece Registrar ingreso cuando la capacidad está disponible', async () => {
    renderDetalle()

    expect(await screen.findByRole('link', { name: 'Registrar ingreso' })).toHaveAttribute('href', '/colaboradores/1/ingreso')
  })

  it('ofrece Registrar reingreso a una ficha Desvinculada', async () => {
    vi.mocked(obtenerColaborador).mockResolvedValue(
      detalle({ capacidades, laboral: { disponibilidad: 'disponible', estado: 'Desvinculado', puesto: null, unidad: null } }),
    )
    renderDetalle()

    expect(await screen.findByRole('link', { name: 'Registrar reingreso' })).toBeInTheDocument()
    expect(screen.getAllByText('Desvinculado').length).toBeGreaterThan(0)
  })

  it('no dibuja la acción cuando la capacidad no está disponible o para la propia ficha', async () => {
    vi.mocked(obtenerColaborador).mockResolvedValue(detalle({ capacidades: { ...capacidades, registrar_ingreso: false } }))
    renderDetalle()

    await screen.findByRole('heading', { name: 'Ana Núñez Paredes', level: 1 })
    expect(screen.queryByRole('link', { name: /Registrar (re)?ingreso/ })).not.toBeInTheDocument()
  })

  it('muestra la trayectoria con Ingreso y Reingreso y el hecho futuro programado', async () => {
    vi.mocked(obtenerColaborador).mockResolvedValue(
      detalle({
        capacidades,
        trayectoria: {
          disponibilidad: 'disponible',
          hechos: [
            { fecha_efectiva: '2999-01-01', tipo: 'Reingreso', programado: true, detalle: 'Contrato: Temporal' },
            { fecha_efectiva: '2020-01-10', tipo: 'Ingreso', programado: false, detalle: 'Contrato: Indefinido' },
          ],
        },
      }),
    )
    renderDetalle()

    expect(await screen.findByText('Reingreso')).toBeInTheDocument()
    expect(screen.getByText('Ingreso')).toBeInTheDocument()
    expect(screen.getByText('Programado')).toBeInTheDocument()
    expect(screen.getByText('Contrato: Temporal')).toBeInTheDocument()
  })
})
