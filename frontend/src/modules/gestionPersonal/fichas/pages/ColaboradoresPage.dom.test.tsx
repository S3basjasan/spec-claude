// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { detalle, pagina, resumen } from '../../../../test/fichasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarColaboradores, obtenerColaborador, obtenerFiltros } from '../api'
import { ColaboradoresPage } from './ColaboradoresPage'
import { FichaDetallePage } from './FichaDetallePage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarColaboradores: vi.fn(),
  obtenerFiltros: vi.fn(),
  obtenerColaborador: vi.fn(),
}))

function renderList(entrada = '/colaboradores') {
  return renderRoutes(
    <>
      <Route path="/colaboradores" element={<ColaboradoresPage />} />
      <Route path="/colaboradores/:colaboradorId" element={<FichaDetallePage />} />
    </>,
    entrada,
  )
}

const items = [
  resumen({ id: 1, codigo: 'EMP-001', nombres: 'Ana', apellidos: 'Núñez Paredes' }),
  resumen({ id: 2, codigo: 'EMP-002', nombres: 'Luis', apellidos: 'Núñez Andrade' }),
]

beforeEach(() => {
  vi.mocked(listarColaboradores).mockReset().mockResolvedValue(pagina(items))
  vi.mocked(obtenerFiltros)
    .mockReset()
    .mockResolvedValue({ estados: [{ valor: 'Todos' }, { valor: 'SinVinculoVigente' }], unidades: [], unidades_disponibles: true })
  vi.mocked(obtenerColaborador).mockReset().mockResolvedValue(detalle())
})

describe('ColaboradoresPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(listarColaboradores).mockReturnValue(new Promise(() => {}))
    renderList()

    expect(screen.getByRole('status', { name: 'Cargando fichas…' })).toBeInTheDocument()
  })

  it('lista código, nombre, puesto y unidad «No registrado», estado y la acción Ver ficha', async () => {
    renderList()

    expect(await screen.findByText('EMP-001')).toBeInTheDocument()
    expect(screen.getByText('Ana Núñez Paredes')).toBeInTheDocument()
    expect(screen.getAllByText('No registrado')).toHaveLength(4)
    expect(screen.getAllByText('Sin vínculo vigente')[0]).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver ficha de Ana Núñez Paredes' })).toHaveAttribute('href', '/colaboradores/1')
    expect(screen.getByRole('link', { name: 'Nueva ficha' })).toHaveAttribute('href', '/colaboradores/nuevo')
    expect(screen.queryByRole('button', { name: /eliminar|desactivar/i })).not.toBeInTheDocument()
  })

  it('indica «Información no disponible» cuando la fuente laboral falla, sin ocultar las fichas', async () => {
    vi.mocked(listarColaboradores).mockResolvedValue(pagina([resumen({ laboral_disponible: false })]))
    renderList()

    expect(await screen.findByText('Ana Núñez Paredes')).toBeInTheDocument()
    expect(screen.getAllByText('Información no disponible')).toHaveLength(3)
  })

  it('muestra el estado vacío con Nueva ficha cuando no hay fichas ni filtros', async () => {
    vi.mocked(listarColaboradores).mockResolvedValue(pagina([]))
    renderList()

    expect(await screen.findByText('Aún no hay fichas de colaboradores')).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Nueva ficha' }).length).toBeGreaterThan(0)
  })

  it('muestra «Ningún colaborador coincide con la búsqueda» cuando hay filtros sin resultados', async () => {
    vi.mocked(listarColaboradores).mockResolvedValue(pagina([]))
    renderList('/colaboradores?q=zzz')

    expect(await screen.findByText('Ningún colaborador coincide con la búsqueda')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Limpiar filtros' })).toBeInTheDocument()
  })

  it('muestra el error con Reintentar y vuelve a consultar', async () => {
    const user = userEvent.setup()
    vi.mocked(listarColaboradores).mockRejectedValueOnce(new Error('fallo')).mockResolvedValue(pagina(items))
    renderList()

    expect(await screen.findByText('No se pudieron cargar las fichas')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('EMP-001')).toBeInTheDocument()
  })

  it('aplica la búsqueda tras una pausa y la conserva en la dirección', async () => {
    const user = userEvent.setup()
    renderList()
    await screen.findByText('EMP-001')

    await user.type(screen.getByLabelText('Buscar colaborador'), 'nunez')

    await waitFor(() => expect(vi.mocked(listarColaboradores).mock.calls.at(-1)?.[0].q).toBe('nunez'))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('q=nunez')
  })

  it('el filtro de estado ofrece Todos y los estados presentes, y se escribe en la URL', async () => {
    const user = userEvent.setup()
    renderList()
    await screen.findByText('EMP-001')

    const filtro = await screen.findByLabelText('Filtrar por estado')
    await waitFor(() => expect(filtro.querySelectorAll('option')).toHaveLength(2))
    await user.selectOptions(filtro, 'SinVinculoVigente')

    await waitFor(() => expect(vi.mocked(listarColaboradores).mock.calls.at(-1)?.[0].estado).toBe('SinVinculoVigente'))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('estado=SinVinculoVigente')
  })

  it('abre el detalle desde Ver ficha', async () => {
    const user = userEvent.setup()
    renderList()

    await user.click(await screen.findByRole('link', { name: 'Ver ficha de Ana Núñez Paredes' }))

    expect(await screen.findByRole('heading', { name: 'Ana Núñez Paredes', level: 1 })).toBeInTheDocument()
  })
})
