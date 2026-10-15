// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { ARBOL_DE_EJEMPLO, listado, unidad } from '../../../../test/unidadesFixtures'
import { useCurrentUser } from '../../../auth/login'
import { listarUnidades } from '../api'
import { UnidadesPage } from './UnidadesPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarUnidades: vi.fn(),
  obtenerUnidad: vi.fn(),
  obtenerOpciones: vi.fn(),
  cambiarEstadoUnidad: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderPage(entrada = '/unidades') {
  return renderRoutes(<Route path="/unidades" element={<UnidadesPage />} />, entrada)
}

const activas = ARBOL_DE_EJEMPLO.filter((u) => u.estado === 'Activa')

beforeEach(() => {
  vi.mocked(listarUnidades).mockReset().mockResolvedValue(listado(activas))
  comoRol('user')
})

describe('UnidadesPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(listarUnidades).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando unidades…' })).toBeInTheDocument()
  })

  it('muestra las columnas del árbol, con "—" como padre de la raíz y el estado como badge', async () => {
    renderPage()

    const tabla = await screen.findByRole('table', { name: 'Unidades organizacionales' })
    for (const columna of ['Código', 'Nombre', 'Tipo de unidad', 'Unidad padre', 'Estado']) {
      expect(within(tabla).getByRole('columnheader', { name: columna })).toBeInTheDocument()
    }
    const filaRaiz = within(tabla).getByRole('row', { name: /UO-001/ })
    expect(within(filaRaiz).getByRole('cell', { name: '—' })).toBeInTheDocument()
    expect(within(filaRaiz).getByText('Activa')).toHaveClass('bg-soft')
    expect(within(tabla).getAllByText('Activa')).toHaveLength(8)
  })

  it('lista las unidades en el orden jerárquico que entrega el servidor', async () => {
    renderPage()

    const filas = await screen.findAllByRole('rowheader')
    expect(filas.map((fila) => fila.textContent?.replace(/^Nivel \d+: /, ''))).toEqual([
      'Gerencia general',
      'Talento humano',
      'Reclutamiento',
      'Tecnología',
      'Inteligencia de negocio',
      'Desarrollo',
      'Finanzas',
      'Ventas',
    ])
  })

  it('muestra la cantidad encontrada y ningún paginador', async () => {
    renderPage()

    expect(await screen.findByText('8 unidades encontradas')).toBeInTheDocument()
    expect(screen.queryByText(/Mostrando/)).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Siguiente|Anterior/ })).not.toBeInTheDocument()
  })

  it('usa el singular cuando solo hay una unidad', async () => {
    vi.mocked(listarUnidades).mockResolvedValue(listado([activas[0]]))
    renderPage()

    expect(await screen.findByText('1 unidad encontrada')).toBeInTheDocument()
  })

  it('al Usuario no le ofrece el filtro de estado, Nueva unidad ni acciones, y no envía estado', async () => {
    renderPage()
    await screen.findByRole('table', { name: 'Unidades organizacionales' })

    expect(screen.queryByLabelText('Filtrar por estado')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Nueva unidad' })).not.toBeInTheDocument()
    expect(screen.queryByRole('columnheader', { name: 'Acciones' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Editar|Desactivar|Reactivar/ })).not.toBeInTheDocument()
    expect(screen.queryByText(/solo se puede desactivar/)).not.toBeInTheDocument()
    expect(vi.mocked(listarUnidades).mock.calls[0][0].estado).toBeUndefined()
  })

  it('al Administrador le ofrece Nueva unidad, el filtro con Activas por defecto y la columna de acciones', async () => {
    comoRol('admin')
    renderPage()
    await screen.findByRole('table', { name: 'Unidades organizacionales' })

    expect(screen.getByRole('button', { name: 'Nueva unidad' })).toBeInTheDocument()
    expect(screen.getByLabelText('Filtrar por estado')).toHaveValue('Activa')
    expect(screen.getByRole('columnheader', { name: 'Acciones' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Editar Finanzas' })).toBeInTheDocument()
    expect(screen.getByText(/Una unidad solo se puede desactivar si no tiene unidades dependientes activas ni posiciones asignadas\./)).toBeInTheDocument()
    expect(vi.mocked(listarUnidades).mock.calls[0][0].estado).toBe('Activa')
  })

  it('no existe ningún botón de eliminar', async () => {
    comoRol('admin')
    renderPage()
    await screen.findByRole('table', { name: 'Unidades organizacionales' })

    expect(screen.queryByRole('button', { name: /eliminar/i })).not.toBeInTheDocument()
  })

  it('muestra el estado Inactiva como badge neutro', async () => {
    vi.mocked(listarUnidades).mockResolvedValue(listado([ARBOL_DE_EJEMPLO[8]]))
    comoRol('admin')
    renderPage('/unidades?estado=Inactiva')

    expect(await screen.findByText('Operaciones')).toBeInTheDocument()
    expect(screen.getByText('Inactiva', { selector: 'span' })).toHaveClass('bg-border')
  })

  it('guarda búsqueda y estado en la URL y no escribe Activa', async () => {
    comoRol('admin')
    renderPage()
    const user = userEvent.setup()
    await screen.findByRole('table', { name: 'Unidades organizacionales' })

    await user.selectOptions(screen.getByLabelText('Filtrar por estado'), 'Todas')
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('/unidades?estado=Todas'))
    await user.selectOptions(screen.getByLabelText('Filtrar por estado'), 'Activa')
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/unidades$/))

    await user.type(screen.getByLabelText('Buscar unidad'), 'area')
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('q=area'))
  })

  it('pide al servidor la búsqueda escrita con el estado elegido', async () => {
    comoRol('admin')
    renderPage('/unidades?q=tec&estado=Todas')

    await screen.findByRole('table', { name: 'Unidades organizacionales' })

    expect(listarUnidades).toHaveBeenCalledWith({ q: 'tec', estado: 'Todas' })
    expect(screen.getByLabelText('Buscar unidad')).toHaveValue('tec')
    expect(screen.getByLabelText('Filtrar por estado')).toHaveValue('Todas')
  })

  it('la búsqueda tiene la ayuda "Buscar por código, nombre o tipo"', async () => {
    renderPage()

    expect(await screen.findByPlaceholderText('Buscar por código, nombre o tipo')).toBeInTheDocument()
  })

  it('con una búsqueda sin coincidencias muestra el mensaje y permite limpiarla', async () => {
    vi.mocked(listarUnidades).mockResolvedValue(listado([]))
    renderPage('/unidades?q=zzz')

    expect(await screen.findByText('Ninguna unidad coincide con la búsqueda.')).toBeInTheDocument()
    expect(screen.getByText('0 unidades encontradas')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/unidades$/))
  })

  it('al Usuario sin unidades le muestra el vacío sencillo', async () => {
    vi.mocked(listarUnidades).mockResolvedValue(listado([]))
    renderPage()

    expect(await screen.findByText('Aún no hay unidades organizacionales')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Nueva unidad' })).not.toBeInTheDocument()
  })

  it('al Administrador sin unidades activas le ofrece crear la primera', async () => {
    vi.mocked(listarUnidades).mockResolvedValue(listado([]))
    comoRol('admin')
    renderPage()

    expect(await screen.findByText('No hay unidades activas')).toBeInTheDocument()
    // Uno en el encabezado y otro como acción del estado vacío.
    expect(screen.getAllByRole('button', { name: 'Nueva unidad' })).toHaveLength(2)
  })

  it('al Administrador con otro estado elegido y sin resultados le sugiere cambiar el filtro', async () => {
    vi.mocked(listarUnidades).mockResolvedValue(listado([]))
    comoRol('admin')
    renderPage('/unidades?estado=Inactiva')

    expect(await screen.findByText('No hay unidades con ese estado')).toBeInTheDocument()
  })

  it('muestra el error con Reintentar', async () => {
    vi.mocked(listarUnidades).mockRejectedValueOnce(httpError(500)).mockResolvedValue(listado(activas))
    renderPage()

    expect(await screen.findByText('No se pudieron cargar las unidades')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('table', { name: 'Unidades organizacionales' })).toBeInTheDocument()
  })

  it('una unidad con sangría de varios niveles conserva su nivel al aparecer sola en una búsqueda', async () => {
    vi.mocked(listarUnidades).mockResolvedValue(listado([unidad({ id: 9, codigo: 'UO-009', nombre: 'Reclutamiento', nivel: 2 })]))
    renderPage('/unidades?q=reclut')

    const nombre = await screen.findByText('Reclutamiento')
    expect(nombre).toHaveStyle({ paddingLeft: '48px' })
  })
})
