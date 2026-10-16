// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { networkError } from '../../../../test/apiErrors'
import { accionMiPlan, miPlan } from '../../../../test/planFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerMiPlan } from '../api'
import { MiPlanPage } from './MiPlanPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerMiPlan: vi.fn(),
}))

function renderPage() {
  return renderRoutes(<Route path="/plan-formacion" element={<MiPlanPage />} />, '/plan-formacion')
}

const acciones = [
  accionMiPlan({ id: 1, nombre: 'Fundamentos de SQL', estado_accion: 'Finalizada', completada: true, fecha_inicio: '2026-02-02', fecha_fin: '2026-02-27', tipo: 'ELearning' }),
  accionMiPlan({ id: 2, nombre: 'Visualización de datos', estado_accion: 'EnCurso' }),
  accionMiPlan({ id: 3, nombre: 'Presentaciones que convencen', estado_accion: 'Programada', fecha_inicio: '2026-10-20', fecha_fin: '2026-10-21', tipo: 'Presencial' }),
  accionMiPlan({ id: 4, nombre: 'Taller cancelado', estado_accion: 'Cancelada', motivo_cancelacion: 'Sin cupo', tipo: 'Mentoring' }),
]

beforeEach(() => {
  vi.mocked(obtenerMiPlan)
    .mockReset()
    .mockResolvedValue(miPlan({ porcentaje_completado: 33, acciones_asignadas: 3, acciones_completadas: 1, acciones, proximas: [acciones[2]] }))
})

describe('MiPlanPage', () => {
  it('muestra el progreso con una barra accesible', async () => {
    renderPage()

    const barra = await screen.findByRole('progressbar', { name: '% del plan completado' })
    expect(barra).toHaveAttribute('aria-valuenow', '33')
    expect(screen.getByText('1 de 3 acciones completadas')).toBeInTheDocument()
    expect(screen.getByText('33 %')).toBeInTheDocument()
    expect(screen.getByText('Las acciones formativas que tienes asignadas en el plan de 2026.')).toBeInTheDocument()
  })

  it('cada tarjeta trae nombre, competencia, fechas, formador, estado y «Ver detalle»', async () => {
    renderPage()

    const tarjeta = await screen.findByRole('article', { name: 'Fundamentos de SQL' })
    expect(within(tarjeta).getByText('Análisis de datos')).toBeInTheDocument()
    expect(within(tarjeta).getByText('2 – 27 feb 2026')).toBeInTheDocument()
    expect(within(tarjeta).getByText('Mg. Andrés Molina')).toBeInTheDocument()
    expect(within(tarjeta).getByText('Finalizada')).toBeInTheDocument()
    expect(within(tarjeta).getByRole('button', { name: 'Ver detalle: Fundamentos de SQL' })).toBeInTheDocument()
    const nombres = screen.getAllByRole('article').map((a) => a.getAttribute('aria-label'))
    expect(nombres).toEqual(['Fundamentos de SQL', 'Visualización de datos', 'Presentaciones que convencen', 'Taller cancelado'])
  })

  it('muestra la acción cancelada con su motivo', async () => {
    renderPage()

    const tarjeta = await screen.findByRole('article', { name: 'Taller cancelado' })
    expect(within(tarjeta).getByText('Cancelada', { selector: 'span' })).toBeInTheDocument()
    expect(within(tarjeta).getByText('Cancelada: Sin cupo')).toBeInTheDocument()
  })

  it('lista las próximas actividades', async () => {
    renderPage()

    const seccion = await screen.findByRole('region', { name: 'Próximas actividades' })
    expect(within(seccion).getByText('Presentaciones que convencen')).toBeInTheDocument()
    expect(within(seccion).getAllByRole('listitem')).toHaveLength(1)
  })

  it('«Ver detalle» abre el detalle sin lugar ni avance por acción', async () => {
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: 'Ver detalle: Fundamentos de SQL' }))

    const dialogo = await screen.findByRole('dialog', { name: 'Fundamentos de SQL' })
    expect(within(dialogo).getByText('Duración')).toBeInTheDocument()
    expect(within(dialogo).getByText('20 h')).toBeInTheDocument()
    expect(within(dialogo).queryByText(/Lugar|Tu avance/)).not.toBeInTheDocument()
    await userEvent.click(within(dialogo).getAllByRole('button', { name: 'Cerrar' })[0])
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('muestra un mensaje informativo si no tiene acciones asignadas', async () => {
    vi.mocked(obtenerMiPlan).mockResolvedValue(miPlan({ acciones: [], proximas: [], acciones_asignadas: 0 }))
    renderPage()

    expect(await screen.findByText('Aún no tienes acciones formativas asignadas')).toBeInTheDocument()
    expect(screen.queryByRole('progressbar')).not.toBeInTheDocument()
  })

  it('muestra un error con reintento', async () => {
    vi.mocked(obtenerMiPlan).mockRejectedValueOnce(networkError())
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('progressbar')).toBeInTheDocument()
    expect(obtenerMiPlan).toHaveBeenCalledTimes(2)
  })
})
