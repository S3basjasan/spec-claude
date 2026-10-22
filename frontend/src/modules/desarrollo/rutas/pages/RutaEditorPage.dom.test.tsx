// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { evento, inscripcion, opcionesRutas, rutaDetalle } from '../../../../test/rutasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarRuta, cambiarEstadoRuta, listarHistorial, listarInscripciones, obtenerOpcionesRutas, obtenerPermisos, obtenerRuta, type ClavePermiso } from '../api'
import { RutaEditorPage } from './RutaEditorPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  actualizarRuta: vi.fn(),
  cambiarEstadoRuta: vi.fn(),
  listarHistorial: vi.fn(),
  listarInscripciones: vi.fn(),
  obtenerOpcionesRutas: vi.fn(),
  obtenerPermisos: vi.fn(),
  obtenerRuta: vi.fn(),
}))

const ADMIN: ClavePermiso[] = ['desarrollo.rutas.ver', 'desarrollo.rutas.gestionar', 'desarrollo.rutas.inscribir', 'desarrollo.rutas.inscripciones', 'desarrollo.rutas.historial']

function renderEditor(permisos: ClavePermiso[] = ADMIN, entrada = '/rutas/1') {
  vi.mocked(obtenerPermisos).mockResolvedValue(permisos)
  return renderRoutes(<Route path="/rutas/:id" element={<RutaEditorPage />} />, entrada)
}

beforeEach(() => {
  vi.mocked(obtenerRuta).mockReset().mockResolvedValue(rutaDetalle())
  vi.mocked(obtenerOpcionesRutas).mockReset().mockResolvedValue(opcionesRutas())
  vi.mocked(actualizarRuta).mockReset().mockResolvedValue(rutaDetalle({ version: 3 }))
  vi.mocked(cambiarEstadoRuta).mockReset().mockResolvedValue(rutaDetalle({ estado: 'Archivada', version: 3 }))
  vi.mocked(listarInscripciones).mockReset().mockResolvedValue({ data: [inscripcion()], meta: { page: 1, per_page: 10, total: 1, last_page: 1 } })
  vi.mocked(listarHistorial).mockReset().mockResolvedValue({ data: [evento()], meta: { page: 1, per_page: 10, total: 1, last_page: 1 } })
})

describe('RutaEditorPage', () => {
  it('muestra el encabezado, el aviso permanente, la auditoría y las tres pestañas', async () => {
    renderEditor()

    expect(await screen.findByRole('heading', { level: 1, name: 'Carrera de especialista en datos' })).toBeInTheDocument()
    expect(screen.getByRole('note')).toHaveTextContent('no implica la promesa de un puesto')
    expect(screen.getByText(/Creada por Administrador de prueba el 20 oct 2026/)).toBeInTheDocument()
    expect(screen.getAllByRole('tab').map((t) => t.textContent)).toEqual(['Escalera de niveles', 'Participantes (2)', 'Historial'])
    expect(screen.getByRole('list', { name: 'Escalera de niveles' })).toBeInTheDocument()
  })

  it('muestra el detalle del nivel elegido con la escala de grados', async () => {
    renderEditor(ADMIN, '/rutas/1?nivel=103')

    expect(await screen.findByRole('heading', { level: 2, name: 'Nivel 3: Analista senior' })).toBeInTheDocument()
    expect(screen.getByText(/Escala de grados: A \(más alto\) a D \(más bajo\)/)).toBeInTheDocument()
  })

  it('sin permisos de gestión, inscripciones ni historial solo ofrece la escalera de consulta', async () => {
    renderEditor(['desarrollo.rutas.ver'])
    await screen.findByRole('heading', { level: 1, name: 'Carrera de especialista en datos' })

    expect(screen.getAllByRole('tab')).toHaveLength(1)
    expect(screen.queryByRole('button', { name: /Editar ruta|Agregar nivel/ })).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Estado de la ruta')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Quitar/ })).not.toBeInTheDocument()
  })

  it('agregar un nivel guarda la ruta con la versión leída y el nuevo nivel al final', async () => {
    renderEditor()

    await screen.findByRole('option', { name: 'Jefe de datos (PU-004)' })
    await userEvent.selectOptions(screen.getByLabelText(/Agregar nivel/), 'Jefe de datos (PU-004)')
    await userEvent.click(screen.getByRole('button', { name: 'Agregar nivel arriba' }))

    await vi.waitFor(() => expect(actualizarRuta).toHaveBeenCalled())
    const [id, cuerpo] = vi.mocked(actualizarRuta).mock.calls[0]
    expect(id).toBe(1)
    expect(cuerpo.version).toBe(2)
    expect(cuerpo.niveles).toHaveLength(4)
    expect(cuerpo.niveles[3]).toEqual({ orden: 4, puesto_id: 4, nombre: 'Jefe de datos', experiencia_minima_anios: null })
    expect(cuerpo.niveles.slice(0, 3).map((n) => n.id)).toEqual([101, 102, 103])
  })

  it('subir un nivel intercambia su posición y renumera', async () => {
    renderEditor()

    await userEvent.click(await screen.findByRole('button', { name: 'Subir Asistente de datos al nivel superior' }))

    await vi.waitFor(() => expect(actualizarRuta).toHaveBeenCalled())
    expect(vi.mocked(actualizarRuta).mock.calls[0][1].niveles.map((n) => [n.id, n.orden])).toEqual([[102, 1], [101, 2], [103, 3]])
  })

  it('quitar un nivel pide confirmación antes de guardar', async () => {
    renderEditor()

    await userEvent.click(await screen.findByRole('button', { name: 'Quitar Analista senior de la ruta' }))
    expect(actualizarRuta).not.toHaveBeenCalled()
    await userEvent.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Quitar nivel' }))

    await vi.waitFor(() => expect(actualizarRuta).toHaveBeenCalled())
    expect(vi.mocked(actualizarRuta).mock.calls[0][1].niveles.map((n) => n.id)).toEqual([101, 102])
  })

  it('un nivel en uso no se puede quitar', async () => {
    vi.mocked(obtenerRuta).mockResolvedValue(rutaDetalle({ niveles: rutaDetalle().niveles.map((n) => (n.id === 103 ? { ...n, en_uso: true } : n)) }))
    renderEditor()

    expect(await screen.findByRole('button', { name: 'Quitar Analista senior de la ruta' })).toBeDisabled()
  })

  it('si otro administrador modificó la ruta (409) avisa del conflicto y ofrece «Recargar»', async () => {
    vi.mocked(actualizarRuta).mockRejectedValue(httpError(409, { message: 'Otro administrador modificó la ruta.', code: 'version_desactualizada' }))
    renderEditor()

    await userEvent.click(await screen.findByRole('button', { name: 'Subir Asistente de datos al nivel superior' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Otro administrador modificó la ruta mientras la editabas')
    await userEvent.click(screen.getByRole('button', { name: 'Recargar' }))
    await vi.waitFor(() => expect(obtenerRuta).toHaveBeenCalledTimes(2))
  })

  it('archivar con inscritos pide confirmación con la cantidad y envía la confirmación', async () => {
    renderEditor()

    await userEvent.selectOptions(await screen.findByLabelText('Estado de la ruta'), 'Archivada')
    const dialogo = await screen.findByRole('alertdialog', { name: 'Archivar ruta' })
    expect(dialogo).toHaveTextContent('La ruta tiene 2 inscripciones activas')
    expect(cambiarEstadoRuta).not.toHaveBeenCalled()

    await userEvent.click(within(dialogo).getByRole('button', { name: 'Archivar ruta' }))
    await vi.waitFor(() => expect(cambiarEstadoRuta).toHaveBeenCalledWith(1, 2, 'Archivada', true))
    expect(await screen.findByText('Estado de la ruta: Archivada.')).toBeInTheDocument()
  })

  it('archivar sin inscritos no pide confirmación', async () => {
    vi.mocked(obtenerRuta).mockResolvedValue(rutaDetalle({ inscritos_count: 0 }))
    renderEditor()

    await userEvent.selectOptions(await screen.findByLabelText('Estado de la ruta'), 'Archivada')

    await vi.waitFor(() => expect(cambiarEstadoRuta).toHaveBeenCalledWith(1, 2, 'Archivada', false))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('el selector de estado solo ofrece las transiciones válidas', async () => {
    vi.mocked(obtenerRuta).mockResolvedValue(rutaDetalle({ estado: 'Borrador' }))
    renderEditor()

    const select = await screen.findByLabelText('Estado de la ruta')
    expect(within(select).getAllByRole('option').map((o) => o.textContent)).toEqual(['Borrador', 'Vigente'])
  })

  it('si no se puede publicar muestra el motivo que devuelve el servidor', async () => {
    vi.mocked(obtenerRuta).mockResolvedValue(rutaDetalle({ estado: 'Borrador' }))
    vi.mocked(cambiarEstadoRuta).mockRejectedValue(httpError(422, { message: 'La ruta necesita al menos 2 niveles.', code: 'no_publicable' }))
    renderEditor()

    await userEvent.selectOptions(await screen.findByLabelText('Estado de la ruta'), 'Vigente')

    expect(await screen.findByRole('alert')).toHaveTextContent('La ruta necesita al menos 2 niveles.')
  })

  it('la pestaña Participantes lista las inscripciones y la cantera', async () => {
    renderEditor(ADMIN, '/rutas/1?pestana=participantes')

    const tabla = await screen.findByRole('table', { name: 'Participantes de la ruta' })
    expect(tabla).toHaveTextContent('Ana Núñez')
    expect(screen.getByRole('heading', { name: 'Cantera' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Inscribir colaborador' })).toBeInTheDocument()
    expect(listarInscripciones).toHaveBeenCalledWith(expect.objectContaining({ ruta_id: 1 }))
  })

  it('el filtro por estado de participantes vive en la URL y se envía al servidor', async () => {
    renderEditor(ADMIN, '/rutas/1?pestana=participantes&estado=Retirada')

    await screen.findByRole('table', { name: 'Participantes de la ruta' })

    expect(listarInscripciones).toHaveBeenCalledWith(expect.objectContaining({ estado: 'Retirada' }))
    expect(screen.getByLabelText('Estado')).toHaveValue('Retirada')
  })

  it('la pestaña Historial se muestra con su permiso y consulta el historial', async () => {
    renderEditor(ADMIN, '/rutas/1?pestana=historial')

    expect(await screen.findByRole('table', { name: 'Historial de cambios de la ruta' })).toBeInTheDocument()
    expect(listarHistorial).toHaveBeenCalledWith(1, 1)
  })

  it('sin permiso de historial, la pestaña Historial no existe aunque se pida en la URL', async () => {
    renderEditor(['desarrollo.rutas.ver', 'desarrollo.rutas.gestionar'], '/rutas/1?pestana=historial')
    await screen.findByRole('heading', { level: 1, name: 'Carrera de especialista en datos' })

    expect(screen.queryByRole('tab', { name: 'Historial' })).not.toBeInTheDocument()
    expect(listarHistorial).not.toHaveBeenCalled()
    expect(screen.getByRole('list', { name: 'Escalera de niveles' })).toBeInTheDocument()
  })

  it('con Estructura caída avisa y ofrece «Reintentar»', async () => {
    vi.mocked(obtenerRuta).mockResolvedValue(rutaDetalle({ fuentes_no_disponibles: ['estructura'] }))
    renderEditor()

    expect(await screen.findByText(/No se pudo consultar Estructura Empresarial/)).toBeInTheDocument()
  })

  it('con error de carga muestra «Reintentar»', async () => {
    vi.mocked(obtenerRuta).mockRejectedValue(new Error('caída'))
    renderEditor()

    expect(await screen.findByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
  })
})
