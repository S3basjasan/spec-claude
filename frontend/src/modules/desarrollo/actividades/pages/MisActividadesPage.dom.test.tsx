// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { actividadColaborador, misActividades } from '../../../../test/actividadesFixtures'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerMisActividades } from '../api'
import { MisActividadesPage } from './MisActividadesPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerMisActividades: vi.fn(),
}))

function renderPage() {
  return renderRoutes(<Route path="/actividades" element={<MisActividadesPage />} />, '/actividades')
}

beforeEach(() => {
  vi.mocked(obtenerMisActividades).mockReset().mockResolvedValue(misActividades())
})

describe('MisActividadesPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(obtenerMisActividades).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando actividades' })).toBeInTheDocument()
  })

  it('muestra el resumen, «Próximas actividades» e «Historial»', async () => {
    renderPage()

    expect(screen.getByRole('heading', { name: 'Mis actividades formativas' })).toBeInTheDocument()
    const resumen = await screen.findByRole('list', { name: 'Resumen de mis actividades' })
    expect(resumen).toHaveTextContent('Actividades inscritas')
    expect(resumen).toHaveTextContent('100 %')
    expect(resumen).toHaveTextContent('4,0 / 5')
    const proximas = screen.getByRole('region', { name: 'Próximas actividades' })
    const historial = screen.getByRole('region', { name: 'Historial' })
    expect(within(proximas).getByRole('heading', { name: 'Próxima actividad' })).toBeInTheDocument()
    expect(within(historial).getByRole('heading', { name: 'Actividad pasada' })).toBeInTheDocument()
  })

  it('cada tarjeta muestra tipo, fecha, lugar, formador, estado, asistencia y calificación', async () => {
    renderPage()

    const tarjeta = (await screen.findByRole('heading', { name: 'Actividad pasada' })).closest('article') as HTMLElement
    expect(tarjeta).toHaveTextContent('Presencial')
    expect(tarjeta).toHaveTextContent('10–11 feb 2026')
    expect(tarjeta).toHaveTextContent('Aula 2, edificio B')
    expect(tarjeta).toHaveTextContent('Dra. Paula Ortiz')
    expect(tarjeta).toHaveTextContent('Finalizada')
    expect(tarjeta).toHaveTextContent('Presente')
    expect(tarjeta).toHaveTextContent('4 / 5')
  })

  it('una asistencia Pendiente se muestra tal cual y sin calificación', async () => {
    vi.mocked(obtenerMisActividades).mockResolvedValue(misActividades({ historial: [actividadColaborador({ nombre: 'Sin registrar', estado: 'Finalizada', asistencia: 'Pendiente', calificacion: null })], proximas: [] }))
    renderPage()

    const tarjeta = (await screen.findByRole('heading', { name: 'Sin registrar' })).closest('article') as HTMLElement
    expect(tarjeta).toHaveTextContent('Pendiente')
    expect(tarjeta).toHaveTextContent('Sin calificación')
  })

  it('nunca muestra las observaciones del formador aunque la respuesta las incluyera', async () => {
    const conObservaciones = { ...actividadColaborador({ nombre: 'Con notas' }), observaciones: 'Confidencial del formador' }
    vi.mocked(obtenerMisActividades).mockResolvedValue(misActividades({ proximas: [conObservaciones], historial: [] }))
    renderPage()

    await screen.findByRole('heading', { name: 'Con notas' })
    expect(screen.queryByText(/Confidencial/)).not.toBeInTheDocument()
    expect(screen.queryByText(/Observaciones/i)).not.toBeInTheDocument()
  })

  it('una sección sin elementos muestra su mensaje', async () => {
    vi.mocked(obtenerMisActividades).mockResolvedValue(misActividades({ proximas: [], historial: [actividadColaborador({ id: 5, nombre: 'Pasada', estado: 'Finalizada' })] }))
    renderPage()

    expect(await screen.findByText('No tienes actividades próximas.')).toBeInTheDocument()
  })

  it('el enlace de una actividad virtual se abre en otra pestaña', async () => {
    vi.mocked(obtenerMisActividades).mockResolvedValue(misActividades({ proximas: [actividadColaborador({ nombre: 'Virtual', tipo: 'Virtual', lugar_enlace: 'https://meet.ejemplo.edu.ec/sala' })], historial: [] }))
    renderPage()

    const enlace = await screen.findByRole('link', { name: 'Abrir enlace' })
    expect(enlace).toHaveAttribute('href', 'https://meet.ejemplo.edu.ec/sala')
    expect(enlace).toHaveAttribute('target', '_blank')
  })

  it('sin actividades muestra un mensaje informativo', async () => {
    vi.mocked(obtenerMisActividades).mockResolvedValue(
      misActividades({ proximas: [], historial: [], resumen: { inscritas: 0, finalizadas: 0, asistencias_registradas: 0, presentes: 0, asistencia_porcentaje: null, calificadas: 0, calificacion_promedio: null } }),
    )
    renderPage()

    expect(await screen.findByText('Aún no tienes actividades')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Próximas actividades' })).not.toBeInTheDocument()
  })

  it('el resumen muestra «—» cuando no hay datos para calcularlo', async () => {
    vi.mocked(obtenerMisActividades).mockResolvedValue(
      misActividades({ resumen: { inscritas: 1, finalizadas: 0, asistencias_registradas: 0, presentes: 0, asistencia_porcentaje: null, calificadas: 0, calificacion_promedio: null } }),
    )
    renderPage()

    const resumen = await screen.findByRole('list', { name: 'Resumen de mis actividades' })
    expect(within(resumen).getAllByText('—')).toHaveLength(2)
  })

  it('si falla la carga ofrece reintentar', async () => {
    vi.mocked(obtenerMisActividades).mockRejectedValueOnce(httpError(500)).mockResolvedValue(misActividades())
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('heading', { name: 'Próxima actividad' })).toBeInTheDocument()
  })
})
