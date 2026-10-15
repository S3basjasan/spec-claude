// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { area, cobertura, detalleAdmin, fila, meta } from '../../../../test/catalogoFormativoFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarAreas, listarCatalogo, listarHistorial, obtenerCobertura, obtenerDetalle } from '../api'
import { CatalogoAdminPage } from './CatalogoAdminPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarCatalogo: vi.fn(),
  obtenerCobertura: vi.fn(),
  listarAreas: vi.fn(),
  obtenerDetalle: vi.fn(),
  listarHistorial: vi.fn(),
}))

function renderPage(entrada = '/formacion') {
  return renderRoutes(<Route path="/formacion" element={<CatalogoAdminPage />} />, entrada)
}

const items = [
  fila({ competencia_id: 1, nombre: 'Comunicación efectiva' }),
  fila({ competencia_id: 2, nombre: 'Liderazgo', codigo: 'COMP-0004', cursos_count: 0, microcontenidos_count: 0, cubierta: false }),
]

beforeEach(() => {
  vi.mocked(listarCatalogo).mockReset().mockResolvedValue({ data: items, meta: meta({ total: 2, to: 2 }) })
  vi.mocked(obtenerCobertura).mockReset().mockResolvedValue(cobertura())
  vi.mocked(listarAreas).mockReset().mockResolvedValue([area({ id: 1, nombre: 'Habilidades interpersonales' })])
  vi.mocked(obtenerDetalle).mockReset().mockResolvedValue(detalleAdmin())
  vi.mocked(listarHistorial).mockReset().mockResolvedValue({ data: [], meta: meta({ total: 0 }) })
})

describe('CatalogoAdminPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(listarCatalogo).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando competencias…' })).toBeInTheDocument()
  })

  it('muestra la matriz, el aviso de solo lectura y el indicador de cobertura', async () => {
    renderPage()

    expect(await screen.findByRole('table', { name: 'Matriz formativa de competencias' })).toBeInTheDocument()
    expect(screen.getByText('El diccionario de competencias es de solo lectura.')).toBeInTheDocument()
    expect(await screen.findByText('40%')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Ver como/ })).not.toBeInTheDocument()
  })

  it('por defecto pide solo las activas, sin escribir el estado en la URL', async () => {
    renderPage()
    await screen.findByRole('table')

    expect(listarCatalogo).toHaveBeenCalledWith(expect.objectContaining({ estado: 'Activo', page: 1 }))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('/formacion')
  })

  it('el filtro de estado y los demás filtros se guardan en la URL y se envían al servidor', async () => {
    renderPage()
    const user = userEvent.setup()
    await screen.findByRole('table')

    await user.selectOptions(screen.getByLabelText('Filtrar por estado de la competencia'), 'Todos')
    await user.selectOptions(screen.getByLabelText('Filtrar por tipo de competencia'), 'Cardinal')
    await user.selectOptions(screen.getByLabelText('Filtrar por área formativa'), '1')
    await user.selectOptions(screen.getByLabelText('Filtrar por cursos asociados'), 'sin')

    await waitFor(() =>
      expect(listarCatalogo).toHaveBeenLastCalledWith(
        expect.objectContaining({ estado: 'Todos', tipo: 'Cardinal', area_formativa_id: 1, cobertura: 'sin' }),
      ),
    )
    const url = screen.getByTestId('ubicacion').textContent ?? ''
    expect(url).toContain('estado=Todos')
    expect(url).toContain('tipo=Cardinal')
    expect(url).toContain('area=1')
    expect(url).toContain('cobertura=sin')
  })

  it('la búsqueda por nombre se aplica a la URL tras una pausa', async () => {
    renderPage()
    const user = userEvent.setup()
    await screen.findByRole('table')

    await user.type(screen.getByRole('searchbox', { name: 'Buscar competencia' }), 'lider')

    await waitFor(() => expect(listarCatalogo).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'lider' })))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('q=lider')
  })

  it('"Ver sin cobertura" aplica el filtro de competencias sin ningún recurso', async () => {
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Ver sin cobertura' }))

    await waitFor(() => expect(listarCatalogo).toHaveBeenLastCalledWith(expect.objectContaining({ cobertura: 'sin_oferta' })))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('cobertura=sin_oferta')
  })

  it('"Ver detalle" abre el panel lateral con la competencia en la URL', async () => {
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Ver detalle de Liderazgo' }))

    expect(await screen.findByRole('dialog', { name: 'Comunicación efectiva' })).toBeInTheDocument()
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('competencia=2')
    expect(obtenerDetalle).toHaveBeenCalledWith(2)
  })

  it('una URL con ?competencia abre el panel directamente y cerrarlo conserva los filtros', async () => {
    renderPage('/formacion?tipo=Cardinal&competencia=1')
    const user = userEvent.setup()

    const dialogo = await screen.findByRole('dialog')
    await user.click(within(dialogo).getByRole('button', { name: 'Cerrar panel' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('tipo=Cardinal')
    expect(screen.getByTestId('ubicacion')).not.toHaveTextContent('competencia=')
  })

  it('sin coincidencias muestra el mensaje de búsqueda y permite limpiar los filtros', async () => {
    vi.mocked(listarCatalogo).mockResolvedValue({ data: [], meta: meta({ total: 0, from: null, to: null }) })
    renderPage('/formacion?q=zzz')
    const user = userEvent.setup()

    expect(await screen.findByText('Ninguna competencia coincide con la búsqueda.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Limpiar filtros' }))

    await waitFor(() => expect(screen.getByTestId('ubicacion')).not.toHaveTextContent('q=zzz'))
  })

  it('muestra el error con Reintentar', async () => {
    vi.mocked(listarCatalogo).mockRejectedValueOnce(httpError(500))
    renderPage()
    const user = userEvent.setup()

    expect(await screen.findByText('No se pudo cargar el catálogo')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('table')).toBeInTheDocument()
  })

  it('"Gestionar áreas" abre el diálogo de áreas', async () => {
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Gestionar áreas' }))

    expect(await screen.findByRole('dialog', { name: 'Gestionar áreas formativas' })).toBeInTheDocument()
  })
})
