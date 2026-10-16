// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { filaHistorial, opciones, paginaHistorial } from '../../../../test/dnfFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarDnf, obtenerOpciones } from '../api'
import { DnfHistorialPage } from './DnfHistorialPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarDnf: vi.fn(),
  obtenerOpciones: vi.fn(),
}))

function renderPage(entrada = '/dnf/historial') {
  return renderRoutes(<Route path="/dnf/historial" element={<DnfHistorialPage />} />, entrada)
}

const filas = [
  filaHistorial(),
  filaHistorial({ id: 6, codigo: 'DNF-0006', alcance: 'Departamento', colaborador: null, departamento: { id: 5, nombre: 'Tecnología' }, cantidad_brechas: 9, prioridad_mas_alta: 'Critica', tiene_plan: true }),
]

beforeEach(() => {
  vi.mocked(listarDnf).mockReset().mockResolvedValue(paginaHistorial(filas))
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opciones())
})

describe('DnfHistorialPage', () => {
  it('muestra código, fecha, alcance, colaborador o departamento, brechas, prioridad y plan', async () => {
    renderPage()

    const tabla = await screen.findByRole('table', { name: 'Historial de diagnósticos' }, { timeout: 5000 })
    const individual = within(tabla).getAllByRole('row')[1]
    expect(within(individual).getByText('DNF-0007')).toBeInTheDocument()
    expect(within(individual).getByText('Individual')).toBeInTheDocument()
    expect(within(individual).getByText('Diego Paredes')).toBeInTheDocument()
    expect(within(individual).getByText('Alta')).toBeInTheDocument()
    expect(within(individual).getByText('Sin plan')).toBeInTheDocument()
    const masiva = within(tabla).getAllByRole('row')[2]
    expect(within(masiva).getByText('Tecnología')).toBeInTheDocument()
    expect(within(masiva).getByText('Crítica')).toBeInTheDocument()
    expect(within(masiva).getByText('Con plan')).toBeInTheDocument()
  })

  it('cada fila abre el detalle y no hay acciones de editar ni eliminar', async () => {
    renderPage()

    const enlace = await screen.findByRole('link', { name: 'Ver detalle de DNF-0007' })
    expect(enlace).toHaveAttribute('href', '/dnf/7')
    expect(screen.queryByRole('button', { name: /Editar|Eliminar/ })).not.toBeInTheDocument()
  })

  it('lee los filtros de la URL y los envía al servidor', async () => {
    renderPage('/dnf/historial?desde=2026-10-01&hasta=2026-10-31&colaborador=10&departamento=5&prioridad=Alta&page=2')

    await screen.findByRole('table', { name: 'Historial de diagnósticos' })

    expect(listarDnf).toHaveBeenCalledWith({
      fecha_desde: '2026-10-01', fecha_hasta: '2026-10-31', colaborador_id: 10, departamento_id: 5, prioridad: 'Alta', page: 2, per_page: 15,
    })
    expect(screen.getByLabelText('Prioridad más alta')).toHaveValue('Alta')
    expect(screen.getByLabelText('Desde')).toHaveValue('2026-10-01')
  })

  it('al cambiar un filtro actualiza la URL y vuelve a la primera página', async () => {
    renderPage('/dnf/historial?page=3')
    await screen.findByRole('table', { name: 'Historial de diagnósticos' })

    await userEvent.selectOptions(screen.getByLabelText('Prioridad más alta'), 'Critica')

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('/dnf/historial?prioridad=Critica'))
    await waitFor(() => expect(listarDnf).toHaveBeenLastCalledWith(expect.objectContaining({ prioridad: 'Critica', page: 1 })))
  })

  it('un rango de fechas invertido muestra el error y no consulta con ese rango', async () => {
    renderPage('/dnf/historial?desde=2026-10-10&hasta=2026-10-01')

    expect(await screen.findByText('La fecha inicial no puede ser posterior a la final.')).toBeInTheDocument()
    await waitFor(() => expect(listarDnf).toHaveBeenCalled())
    expect(listarDnf).not.toHaveBeenCalledWith(expect.objectContaining({ fecha_desde: '2026-10-10' }))
  })

  it('«Limpiar filtros» borra todos los filtros de la URL', async () => {
    renderPage('/dnf/historial?prioridad=Alta&colaborador=10')
    await screen.findByRole('table', { name: 'Historial de diagnósticos' })

    await userEvent.click(screen.getByRole('button', { name: 'Limpiar filtros' }))

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/dnf\/historial$/))
  })

  it('pagina: el botón Siguiente pide la página 2', async () => {
    vi.mocked(listarDnf).mockResolvedValue(paginaHistorial(filas, { total: 30, last_page: 2 }))
    renderPage()
    await screen.findByRole('table', { name: 'Historial de diagnósticos' })

    await userEvent.click(screen.getByRole('button', { name: 'Siguiente' }))

    await waitFor(() => expect(listarDnf).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2 })))
  })

  it('sin diagnósticos muestra el estado vacío; con filtros, que ninguno coincide', async () => {
    vi.mocked(listarDnf).mockResolvedValue(paginaHistorial([]))
    renderPage()
    expect(await screen.findByText('Aún no hay diagnósticos')).toBeInTheDocument()
  })

  it('con filtros y sin resultados dice que ninguno coincide', async () => {
    vi.mocked(listarDnf).mockResolvedValue(paginaHistorial([]))
    renderPage('/dnf/historial?prioridad=Baja')

    expect(await screen.findByText('Ningún diagnóstico coincide con los filtros.')).toBeInTheDocument()
  })

  it('muestra cargando y, si falla, el error con reintento', async () => {
    vi.mocked(listarDnf).mockReturnValueOnce(new Promise(() => {}))
    const primera = renderPage()
    expect(screen.getByRole('status', { name: 'Historial de diagnósticos' })).toBeInTheDocument()
    primera.unmount()

    vi.mocked(listarDnf).mockReset().mockRejectedValueOnce(httpError(500)).mockResolvedValue(paginaHistorial(filas))
    renderPage()
    expect(await screen.findByText('No se pudo cargar la información')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('table', { name: 'Historial de diagnósticos' })).toBeInTheDocument()
  })
})
