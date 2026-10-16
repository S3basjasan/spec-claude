// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { categoria, paginaDeCategorias } from '../../../../test/categoriasFixtures'
import { documento, paginaDeDocumentos } from '../../../../test/documentosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { abrirBlobEnPestana, descargarBlob } from '../../../../shared/api/pdf'
import { listarCategorias } from '../../categorias/api'
import { archivarDocumento, cambiarVisibilidad, listarDocumentos, obtenerContenido } from '../api'
import { SeccionDocumentos } from './SeccionDocumentos'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarDocumentos: vi.fn(),
  listarMisDocumentos: vi.fn(),
  cargarDocumento: vi.fn(),
  cambiarVisibilidad: vi.fn(),
  archivarDocumento: vi.fn(),
  obtenerContenido: vi.fn(),
}))
vi.mock('../../categorias/api', async (importOriginal) => ({ ...(await importOriginal<typeof import('../../categorias/api')>()), listarCategorias: vi.fn() }))
vi.mock('../../../../shared/api/pdf', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../../../../shared/api/pdf')>()),
  descargarBlob: vi.fn(),
  abrirBlobEnPestana: vi.fn(),
}))

function renderSeccion(entrada = '/') {
  return renderRoutes(<Route path="/" element={<SeccionDocumentos colaboradorId={1} />} />, entrada)
}

const contratos = documento({ id: 1, titulo: 'Contrato firmado' })

beforeEach(() => {
  vi.mocked(listarDocumentos).mockReset().mockResolvedValue(paginaDeDocumentos([contratos]))
  vi.mocked(listarCategorias).mockReset().mockResolvedValue(paginaDeCategorias([categoria({ id: 4, nombre: 'Contratos' })]))
  vi.mocked(cambiarVisibilidad).mockReset().mockResolvedValue(documento({ visible_colaborador: true, version: 2 }))
  vi.mocked(archivarDocumento).mockReset().mockResolvedValue(documento({ estado: 'Archivado', version: 2 }))
  vi.mocked(obtenerContenido).mockReset().mockResolvedValue({ blob: new Blob(['%PDF-1.4'], { type: 'application/pdf' }), nombreDeArchivo: 'Documento_1.pdf' })
  vi.mocked(descargarBlob).mockReset()
  vi.mocked(abrirBlobEnPestana).mockReset()
})

describe('SeccionDocumentos del Administrador', () => {
  it('muestra el estado de carga', () => {
    vi.mocked(listarDocumentos).mockReturnValue(new Promise(() => {}))
    renderSeccion()

    expect(screen.getByRole('status', { name: 'Cargando documentos…' })).toBeInTheDocument()
  })

  it('lista los disponibles con título, categoría, tamaño y acciones, sin Eliminar, Restaurar ni Editar', async () => {
    renderSeccion()

    expect(await screen.findByText('Contrato firmado')).toBeInTheDocument()
    expect(screen.getByText('Contratos', { selector: 'span' })).toBeInTheDocument()
    expect(screen.getByText('482 KB')).toBeInTheDocument()
    expect(vi.mocked(listarDocumentos).mock.calls[0][1]).toMatchObject({ estado: 'Disponible' })
    expect(screen.getByRole('button', { name: 'Abrir Contrato firmado' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Descargar Contrato firmado' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Archivar Contrato firmado' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /eliminar|restaurar|editar/i })).not.toBeInTheDocument()
  })

  it('muestra el vacío con Subir documento, el error con Reintentar y el vacío con filtros', async () => {
    vi.mocked(listarDocumentos).mockResolvedValue(paginaDeDocumentos([]))
    const { unmount } = renderSeccion()
    expect(await screen.findByText('Aún no hay documentos')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Subir documento' }).length).toBeGreaterThanOrEqual(1)
    unmount()

    renderSeccion('/?q=zzz')
    expect(await screen.findByText('Ningún documento coincide con la búsqueda.')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    await waitFor(() => expect(vi.mocked(listarDocumentos).mock.calls.at(-1)?.[1]).toMatchObject({ q: '' }))
  })

  it('un fallo muestra error con Reintentar sin ocultar nada más', async () => {
    vi.mocked(listarDocumentos).mockRejectedValueOnce(httpError(403))
    renderSeccion()

    expect(await screen.findByText('No se pudieron cargar los documentos')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByText('Contrato firmado')).toBeInTheDocument()
  })

  it('identifica la categoría inactiva', async () => {
    vi.mocked(listarDocumentos).mockResolvedValue(paginaDeDocumentos([documento({ categoria: { id: 9, nombre: 'Antigua', estado: 'Inactiva' } })]))
    renderSeccion()

    expect(await screen.findByText('Antigua')).toBeInTheDocument()
    expect(screen.getByText('Inactiva')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Abrir Contrato firmado' })).toBeEnabled()
  })

  it('búsqueda, categoría y archivados se aplican a la consulta y a la URL', async () => {
    const user = userEvent.setup()
    renderSeccion()
    await screen.findByText('Contrato firmado')

    await user.type(screen.getByLabelText('Buscar documento'), 'cert')
    await waitFor(() => expect(vi.mocked(listarDocumentos).mock.calls.at(-1)?.[1]).toMatchObject({ q: 'cert' }))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('q=cert')

    await user.selectOptions(screen.getByLabelText('Categoría'), '4')
    await waitFor(() => expect(vi.mocked(listarDocumentos).mock.calls.at(-1)?.[1]).toMatchObject({ categoria_id: 4 }))

    await user.click(screen.getByRole('button', { name: 'Archivados' }))
    await waitFor(() => expect(vi.mocked(listarDocumentos).mock.calls.at(-1)?.[1]).toMatchObject({ estado: 'Archivado' }))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('estado=Archivado')
  })

  it('la vista de archivados no ofrece acciones de escritura y muestra quién y cuándo archivó', async () => {
    vi.mocked(listarDocumentos).mockResolvedValue(paginaDeDocumentos([documento({ estado: 'Archivado', archivado_por: 1, archivado_en: '2026-10-16T15:00:00Z' })]))
    renderSeccion('/?estado=Archivado')

    expect(await screen.findByText(/Archivado el/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /archivar|visible/i })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Descargar Contrato firmado' })).toBeInTheDocument()
  })

  it('abre en una pestaña y descarga el PDF autorizado', async () => {
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Abrir Contrato firmado' }))
    await waitFor(() => expect(abrirBlobEnPestana).toHaveBeenCalledTimes(1))
    expect(vi.mocked(obtenerContenido).mock.calls[0]).toEqual([1, 1, false])

    await user.click(screen.getByRole('button', { name: 'Descargar Contrato firmado' }))
    await waitFor(() => expect(descargarBlob).toHaveBeenCalledWith(expect.any(Blob), 'Documento_1.pdf'))
    expect(vi.mocked(obtenerContenido).mock.calls[1]).toEqual([1, 1, true])
  })

  it('un error al abrir no muestra contenido y explica el motivo', async () => {
    vi.mocked(obtenerContenido).mockRejectedValueOnce(httpError(404))
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Abrir Contrato firmado' }))

    expect(await screen.findByText('El documento ya no está disponible.')).toBeInTheDocument()
    expect(abrirBlobEnPestana).not.toHaveBeenCalled()
  })

  it('cambia la visibilidad solo cuando el servidor lo confirma', async () => {
    let resolver: (valor: ReturnType<typeof documento>) => void = () => {}
    vi.mocked(cambiarVisibilidad).mockReturnValue(new Promise((r) => (resolver = r)))
    const user = userEvent.setup()
    renderSeccion()
    const boton = await screen.findByRole('button', { name: 'Hacer visible Contrato firmado para el colaborador' })
    await user.click(boton)

    expect(cambiarVisibilidad).toHaveBeenCalledWith(1, 1, true, 1)
    expect(screen.getByRole('button', { name: 'Hacer visible Contrato firmado para el colaborador' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Hacer visible Contrato firmado para el colaborador' })).toHaveAttribute('aria-pressed', 'false')

    vi.mocked(listarDocumentos).mockResolvedValue(paginaDeDocumentos([documento({ visible_colaborador: true, version: 2 })]))
    resolver(documento({ visible_colaborador: true, version: 2 }))

    expect(await screen.findByText('Visibilidad actualizada.')).toBeInTheDocument()
    expect(await screen.findByRole('button', { name: 'Retirar la visibilidad de Contrato firmado para el colaborador' })).toHaveAttribute('aria-pressed', 'true')
  })

  it('un 409 al cambiar la visibilidad avisa y vuelve a consultar', async () => {
    vi.mocked(cambiarVisibilidad).mockRejectedValueOnce(httpError(409, { code: 'DOCUMENTO_MODIFICADO' }))
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Hacer visible Contrato firmado para el colaborador' }))

    expect(await screen.findByText(/La información del documento cambió/)).toBeInTheDocument()
    await waitFor(() => expect(vi.mocked(listarDocumentos).mock.calls.length).toBeGreaterThan(1))
  })

  it('archivar pide confirmación con el aviso de conservación y archiva con la versión vigente', async () => {
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Archivar Contrato firmado' }))

    const dialogo = screen.getByRole('alertdialog')
    expect(within(dialogo).getByText(/Se conservan el archivo y sus datos/)).toBeInTheDocument()
    await user.click(within(dialogo).getByRole('button', { name: 'Archivar documento' }))

    await waitFor(() => expect(archivarDocumento).toHaveBeenCalledWith(1, 1, 1))
    expect(await screen.findByText(/Documento archivado/)).toBeInTheDocument()
  })

  it('cancelar la confirmación no archiva y un 409 pide actualizar sin cambiar el estado', async () => {
    vi.mocked(archivarDocumento).mockRejectedValueOnce(httpError(409, { code: 'DOCUMENTO_MODIFICADO' }))
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Archivar Contrato firmado' }))
    await user.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Cancelar' }))
    expect(archivarDocumento).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Archivar Contrato firmado' }))
    await user.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Archivar documento' }))
    expect(await screen.findByText(/La información del documento cambió/)).toBeInTheDocument()
    expect(screen.getByRole('alertdialog')).toBeInTheDocument()
  })
})
