// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { networkError } from '../../../../test/apiErrors'
import { filaPlan, paginaPlanes, puede } from '../../../../test/planFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarPlanes } from '../api'
import { PlanesPage } from './PlanesPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarPlanes: vi.fn(),
}))

function renderPage(entrada = '/plan-formacion') {
  return renderRoutes(<Route path="/plan-formacion" element={<PlanesPage />} />, entrada)
}

const filas = [
  filaPlan(),
  filaPlan({ id: 2, codigo: 'PLAN-0002', nombre: 'Liderazgo 2026', estado: 'Aprobado', cantidad_acciones: 5, costo_total: 9000, aprobador: null, puede: puede({ iniciar: true }) }),
]

beforeEach(() => {
  vi.mocked(listarPlanes).mockReset().mockResolvedValue(paginaPlanes(filas))
})

describe('PlanesPage', () => {
  it('muestra código, nombre, año, estado, acciones, costo total y aprobador', async () => {
    renderPage()

    const tabla = await screen.findByRole('table', { name: 'Planes de formación' })
    const primera = within(tabla).getAllByRole('row')[1]
    expect(within(primera).getByText('PLAN-0001')).toBeInTheDocument()
    expect(within(primera).getByText('Plan técnico 2026')).toBeInTheDocument()
    expect(within(primera).getByText('Borrador')).toBeInTheDocument()
    expect(within(primera).getByText('3')).toBeInTheDocument()
    expect(within(primera).getByText(/1.500,50/)).toBeInTheDocument()
    expect(within(primera).getByText('Marta Ríos')).toBeInTheDocument()
    const segunda = within(tabla).getAllByRole('row')[2]
    expect(within(segunda).getByText('Aprobado')).toBeInTheDocument()
    expect(within(segunda).getByText('Sin aprobador')).toBeInTheDocument()
  })

  it('muestra «Editar» solo cuando el servidor lo permite', async () => {
    renderPage()

    const tabla = await screen.findByRole('table', { name: 'Planes de formación' })
    expect(within(tabla).getAllByRole('link', { name: /Editar/ })).toHaveLength(1)
    expect(within(tabla).getByRole('link', { name: 'Editar: Plan técnico 2026' })).toHaveAttribute('href', '/plan-formacion/1/editar')
  })

  it('envía los filtros de la URL y los actualiza al cambiarlos', async () => {
    const { ubicacion } = { ubicacion: () => screen.getByTestId('ubicacion') }
    renderPage('/plan-formacion?anio=2026&estado=Aprobado&q=lider&page=2')

    await waitFor(() => expect(listarPlanes).toHaveBeenCalledWith({ anio: 2026, estado: 'Aprobado', q: 'lider', page: 2, per_page: 10 }))

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Estado' }), 'Cerrado')
    await waitFor(() => expect(ubicacion()).toHaveTextContent('estado=Cerrado'))
    expect(ubicacion()).not.toHaveTextContent('page=2')

    await userEvent.click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    await waitFor(() => expect(ubicacion()).toHaveTextContent(/^\/plan-formacion$/))
  })

  it('muestra el estado vacío, el de sin coincidencias y el de error con reintento', async () => {
    vi.mocked(listarPlanes).mockResolvedValue(paginaPlanes([]))
    const { unmount } = renderPage()
    expect(await screen.findByText('Aún no hay planes de formación')).toBeInTheDocument()
    unmount()

    renderPage('/plan-formacion?q=zzz')
    expect(await screen.findByText('Ningún plan coincide con los filtros.')).toBeInTheDocument()
  })

  it('muestra un error con «Reintentar» cuando falla la carga', async () => {
    vi.mocked(listarPlanes).mockRejectedValueOnce(networkError())
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('table', { name: 'Planes de formación' })).toBeInTheDocument()
    expect(listarPlanes).toHaveBeenCalledTimes(2)
  })

  it('enlaza a «Nuevo plan» y a las acciones formativas', async () => {
    renderPage()

    expect(await screen.findByRole('link', { name: 'Nuevo plan' })).toHaveAttribute('href', '/plan-formacion/nuevo')
    expect(screen.getByRole('link', { name: 'Ver acciones formativas' })).toHaveAttribute('href', '/plan-formacion/acciones')
  })
})
