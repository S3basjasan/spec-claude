// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { opcionesRutas, rutaResumen } from '../../../../test/rutasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { listarRutas, obtenerOpcionesRutas, obtenerPermisos, type Pagina, type RutaResumen } from '../api'
import { RutasPage } from './RutasPage'

vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))
vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarRutas: vi.fn(),
  obtenerOpcionesRutas: vi.fn(),
  obtenerPermisos: vi.fn(),
}))

const rutas = [
  rutaResumen({ id: 1, codigo: 'CAR-0001', nombre: 'Ruta de datos', tipo: 'Especialista', estado: 'Vigente', inscritos_count: 4 }),
  rutaResumen({ id: 2, codigo: 'CAR-0002', nombre: 'Ruta gerencial', tipo: 'Gerencial', estado: 'Borrador', inscritos_count: 0 }),
  rutaResumen({ id: 3, codigo: 'CAR-0003', nombre: 'Ruta archivada', tipo: 'Especialista', estado: 'Archivada', inscritos_count: 1 }),
]

function pagina(items: RutaResumen[]): Pagina<RutaResumen> {
  return { data: items, meta: { page: 1, per_page: 100, total: items.length, last_page: 1 } }
}

function renderLista(entrada = '/rutas', permisos: string[] = ['desarrollo.rutas.ver', 'desarrollo.rutas.gestionar']) {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Marta', email: 'm@hrms.test', role: 'admin' }, isPending: false } as ReturnType<typeof useCurrentUser>)
  vi.mocked(obtenerPermisos).mockResolvedValue(permisos as Awaited<ReturnType<typeof obtenerPermisos>>)
  return renderRoutes(<Route path="/rutas" element={<RutasPage />} />, entrada)
}

beforeEach(() => {
  vi.mocked(listarRutas).mockReset().mockImplementation(async (filtros) => {
    let r = rutas
    if (filtros.q) r = r.filter((x) => x.nombre.toLowerCase().includes(filtros.q!.toLowerCase()))
    if (filtros.tipo) r = r.filter((x) => x.tipo === filtros.tipo)
    if (filtros.estado) r = r.filter((x) => x.estado === filtros.estado)
    return pagina(r)
  })
  vi.mocked(obtenerOpcionesRutas).mockReset().mockResolvedValue(opcionesRutas())
})

describe('Lista de rutas (Administrador)', () => {
  it('muestra el título, el aviso «guía, no promesa» y los indicadores', async () => {
    renderLista()

    expect(await screen.findByRole('heading', { level: 1, name: 'Rutas de carrera' })).toBeInTheDocument()
    expect(screen.getByRole('note')).toHaveTextContent('Esta ruta es una guía de desarrollo; no implica la promesa de un puesto.')
    expect(await screen.findByText('Rutas vigentes')).toBeInTheDocument()
    expect(screen.getByText('Participantes activos').parentElement).toHaveTextContent('4')
  })

  it('agrupa las tarjetas por tipo con su contador', async () => {
    renderLista()

    const especialistas = await screen.findByRole('region', { name: 'Especialista' })
    expect(within(especialistas).getAllByRole('article')).toHaveLength(2)
    expect(especialistas).toHaveTextContent('2 rutas')
    expect(within(screen.getByRole('region', { name: 'Gerencial' })).getAllByRole('article')).toHaveLength(1)
    expect(screen.queryByRole('region', { name: 'Interáreas' })).not.toBeInTheDocument()
  })

  it('los filtros viven en la URL y reducen las tarjetas', async () => {
    renderLista('/rutas?tipo=Gerencial')

    expect(await screen.findByRole('heading', { name: 'Ruta gerencial' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Ruta de datos' })).not.toBeInTheDocument()
  })

  it('la búsqueda por nombre filtra y «Limpiar filtros» los restablece', async () => {
    renderLista()
    await screen.findByRole('heading', { name: 'Ruta gerencial' })

    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar' }), 'gerencial')
    expect(await screen.findByRole('heading', { name: 'Ruta gerencial' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Ruta archivada' })).not.toBeInTheDocument()
    expect(screen.getByText('Mostrando 1 de 3 rutas')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    expect(await screen.findByRole('heading', { name: 'Ruta archivada' })).toBeInTheDocument()
  })

  it('sin coincidencias muestra «Ninguna ruta coincide con los filtros.»', async () => {
    renderLista('/rutas?q=zzz')

    expect(await screen.findByText('Ninguna ruta coincide con los filtros.')).toBeInTheDocument()
  })

  it('«Nueva ruta» solo se ofrece con el permiso de gestión', async () => {
    renderLista('/rutas', ['desarrollo.rutas.ver'])
    await screen.findByRole('heading', { name: 'Ruta de datos' })

    expect(screen.queryByRole('button', { name: 'Nueva ruta' })).not.toBeInTheDocument()
  })

  it('«Nueva ruta» abre el formulario con la nota de que se crea en Borrador', async () => {
    renderLista()

    await userEvent.click(await screen.findByRole('button', { name: 'Nueva ruta' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Nueva ruta de carrera' })
    expect(dialogo).toHaveTextContent('La ruta se crea en estado Borrador')
  })

  it('con error muestra «Reintentar» y vuelve a pedir la lista', async () => {
    vi.mocked(listarRutas).mockRejectedValueOnce(new Error('caída'))
    renderLista()

    const reintentar = await screen.findByRole('button', { name: 'Reintentar' })
    vi.mocked(listarRutas).mockResolvedValue(pagina(rutas))
    await userEvent.click(reintentar)
    expect(await screen.findByRole('heading', { name: 'Ruta de datos' })).toBeInTheDocument()
  })

  it('sin ninguna ruta muestra el estado vacío', async () => {
    vi.mocked(listarRutas).mockResolvedValue(pagina([]))
    renderLista()

    expect(await screen.findByText('Todavía no hay rutas de carrera')).toBeInTheDocument()
  })
})
