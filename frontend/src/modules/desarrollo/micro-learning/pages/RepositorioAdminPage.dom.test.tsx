// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { fila, meta, micro, opciones } from '../../../../test/microFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { cambiarEstadoMicro, eliminarMicro, listarHistorialMicro, listarMicros, obtenerMicro, obtenerOpciones } from '../api'
import { RepositorioAdminPage } from './RepositorioAdminPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarMicros: vi.fn(),
  obtenerOpciones: vi.fn(),
  obtenerMicro: vi.fn(),
  cambiarEstadoMicro: vi.fn(),
  eliminarMicro: vi.fn(),
  listarHistorialMicro: vi.fn(),
}))

function renderPage(entrada = '/micro-learning') {
  return renderRoutes(<Route path="/micro-learning" element={<RepositorioAdminPage />} />, entrada)
}

const items = [
  fila({ id: 1, titulo: 'Publicado uno', estado: 'Publicado', completaciones: 4 }),
  fila({ id: 2, titulo: 'Borrador uno', estado: 'Borrador', tipo: 'PDF' }),
  fila({ id: 3, titulo: 'Con progreso', estado: 'Publicado', puede_eliminarse: false }),
]

beforeEach(() => {
  vi.mocked(listarMicros).mockReset().mockResolvedValue({ data: items, meta: meta({ total: 3, to: 3 }) })
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opciones())
  vi.mocked(obtenerMicro).mockReset().mockResolvedValue(micro())
  vi.mocked(cambiarEstadoMicro).mockReset().mockResolvedValue(micro())
  vi.mocked(eliminarMicro).mockReset().mockResolvedValue()
  vi.mocked(listarHistorialMicro).mockReset().mockResolvedValue({ data: [], meta: meta({ total: 0 }) })
})

describe('RepositorioAdminPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(listarMicros).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando micro-learning' })).toBeInTheDocument()
  })

  it('muestra la cuadrícula, la nota de conocimientos y «+ Nuevo Contenido», sin controles de prototipo', async () => {
    renderPage()

    expect(await screen.findByRole('list', { name: 'Repositorio de micro-contenidos' })).toBeInTheDocument()
    expect(screen.getAllByRole('article')).toHaveLength(3)
    expect(screen.getByRole('button', { name: '+ Nuevo Contenido' })).toBeInTheDocument()
    expect(screen.getAllByText(/refuerzan conocimientos/).length).toBeGreaterThan(0)
    expect(screen.queryByRole('button', { name: /Ver como/ })).not.toBeInTheDocument()
  })

  it('por defecto pide Borrador y Publicado, sin escribir el estado en la URL', async () => {
    renderPage()
    await screen.findByRole('list')

    expect(listarMicros).toHaveBeenCalledWith(expect.objectContaining({ estado: 'Activos', page: 1 }))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('/micro-learning')
  })

  it('los filtros se guardan en la URL y se envían al servidor', async () => {
    renderPage()
    const user = userEvent.setup()
    await screen.findByRole('list')

    await user.selectOptions(screen.getByLabelText('Filtrar por estado'), 'Archivado')
    await user.selectOptions(screen.getByLabelText('Filtrar por tipo'), 'Quiz')
    await user.selectOptions(screen.getByLabelText('Filtrar por competencia'), '2')

    await waitFor(() => expect(listarMicros).toHaveBeenLastCalledWith(expect.objectContaining({ estado: 'Archivado', tipo: 'Quiz', competencia_id: 2 })))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('estado=Archivado')
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('tipo=Quiz')
  })

  it('busca por título tras una breve pausa', async () => {
    renderPage()
    const user = userEvent.setup()
    await screen.findByRole('list')

    await user.type(screen.getByLabelText('Buscar por título'), 'sql')

    await waitFor(() => expect(listarMicros).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'sql' })))
  })

  it('sin resultados con filtros ofrece limpiarlos; sin filtros muestra el vacío inicial', async () => {
    vi.mocked(listarMicros).mockResolvedValue({ data: [], meta: meta({ total: 0, from: null, to: null }) })
    renderPage('/micro-learning?tipo=Quiz')

    expect(await screen.findByText('Ningún contenido coincide con los filtros.')).toBeInTheDocument()
    await userEvent.setup().click(screen.getAllByRole('button', { name: 'Limpiar filtros' })[0])
    expect(await screen.findByText('Aún no hay micro-contenidos')).toBeInTheDocument()
  })

  it('muestra un error con reintentar', async () => {
    vi.mocked(listarMicros).mockRejectedValueOnce(httpError(500)).mockResolvedValue({ data: items, meta: meta({ total: 3 }) })
    renderPage()

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('list', { name: 'Repositorio de micro-contenidos' })).toBeInTheDocument()
  })

  it('publica un borrador y avisa; si faltan datos los informa', async () => {
    const user = userEvent.setup()
    renderPage()
    await screen.findByRole('list')

    await user.click(screen.getByRole('button', { name: 'Publicar Borrador uno' }))
    await waitFor(() => expect(cambiarEstadoMicro).toHaveBeenCalledWith(2, 'Publicado'))
    expect(await screen.findByText(/Borrador uno».*Contenido publicado/)).toBeInTheDocument()

    vi.mocked(cambiarEstadoMicro).mockRejectedValue(httpError(422, { message: 'Faltan datos', code: 'faltan_requisitos', faltantes: ['url', 'competencias'] }))
    await user.click(screen.getByRole('button', { name: 'Publicar Borrador uno' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Faltan datos para publicar: la URL, al menos una competencia activa')
  })

  it('archivar pide confirmación e informa que dejará de estar disponible', async () => {
    const user = userEvent.setup()
    renderPage()
    await screen.findByRole('list')

    await user.click(screen.getByRole('button', { name: 'Archivar Publicado uno' }))
    const dialogo = await screen.findByRole('alertdialog')
    expect(dialogo).toHaveTextContent('Dejará de estar disponible para los colaboradores')
    expect(cambiarEstadoMicro).not.toHaveBeenCalled()

    await user.click(within(dialogo).getByRole('button', { name: 'Archivar' }))
    await waitFor(() => expect(cambiarEstadoMicro).toHaveBeenCalledWith(1, 'Archivado'))
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
  })

  it('eliminar pide confirmación; con progreso el botón no se ofrece', async () => {
    const user = userEvent.setup()
    renderPage()
    await screen.findByRole('list')

    expect(screen.queryByRole('button', { name: 'Eliminar Con progreso' })).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Eliminar Borrador uno' }))
    const dialogo = await screen.findByRole('alertdialog')
    expect(dialogo).toHaveTextContent('Esta acción no se puede deshacer')
    await user.click(within(dialogo).getByRole('button', { name: 'Eliminar' }))

    await waitFor(() => expect(eliminarMicro).toHaveBeenCalledWith(2))
  })

  it('si el servidor rechaza eliminar por progreso lo explica dentro del diálogo', async () => {
    vi.mocked(eliminarMicro).mockRejectedValue(httpError(422, { message: 'x', code: 'con_progreso' }))
    const user = userEvent.setup()
    renderPage()
    await screen.findByRole('list')

    await user.click(screen.getByRole('button', { name: 'Eliminar Borrador uno' }))
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Eliminar' }))

    expect(await screen.findByText('Este contenido ya tiene progreso de colaboradores y solo puede archivarse.')).toBeInTheDocument()
  })

  it('abre el formulario con «+ Nuevo Contenido» y la vista previa de una tarjeta', async () => {
    const user = userEvent.setup()
    renderPage()
    await screen.findByRole('list')

    await user.click(screen.getByRole('button', { name: '+ Nuevo Contenido' }))
    expect(await screen.findByRole('dialog', { name: 'Nuevo contenido' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())

    await user.click(screen.getByRole('button', { name: 'Vista previa Publicado uno' }))
    expect(await screen.findByRole('dialog', { name: 'Vista previa · así lo verá el colaborador' })).toBeInTheDocument()
  })

  it('abre el historial de estados', async () => {
    vi.mocked(listarHistorialMicro).mockResolvedValue({
      data: [{ id: 1, estado_anterior: null, estado_nuevo: 'Borrador', usuario: 'Marta Ríos', fecha: '2026-10-16T10:00:00Z' }],
      meta: meta(),
    })
    const user = userEvent.setup()
    renderPage()
    await screen.findByRole('list')

    await user.click(screen.getByRole('button', { name: 'Historial Publicado uno' }))

    expect(await screen.findByText('Creado como Borrador')).toBeInTheDocument()
    expect(screen.getByText(/por Marta Ríos/)).toBeInTheDocument()
  })
})
