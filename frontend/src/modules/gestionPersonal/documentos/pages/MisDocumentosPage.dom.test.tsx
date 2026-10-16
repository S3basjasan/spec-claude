// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { miDocumento, paginaDeDocumentos } from '../../../../test/documentosFixtures'
import { detalle } from '../../../../test/fichasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { abrirBlobEnPestana } from '../../../../shared/api/pdf'
import { FichaContenido } from '../../fichas/components/FichaContenido'
import { listarMisDocumentos, obtenerContenido } from '../api'
import { MisDocumentosPage } from './MisDocumentosPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarDocumentos: vi.fn(),
  listarMisDocumentos: vi.fn(),
  obtenerContenido: vi.fn(),
}))
vi.mock('../../../../shared/api/pdf', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../../../../shared/api/pdf')>()),
  descargarBlob: vi.fn(),
  abrirBlobEnPestana: vi.fn(),
}))

function renderPagina() {
  return renderRoutes(<Route path="/" element={<MisDocumentosPage />} />, '/')
}

beforeEach(() => {
  vi.mocked(listarMisDocumentos).mockReset().mockResolvedValue(paginaDeDocumentos([miDocumento({ titulo: 'Mi contrato' })]))
  vi.mocked(obtenerContenido).mockReset().mockResolvedValue({ blob: new Blob(['%PDF-1.4'], { type: 'application/pdf' }), nombreDeArchivo: 'Documento_1.pdf' })
  vi.mocked(abrirBlobEnPestana).mockReset()
})

describe('MisDocumentosPage', () => {
  it('lista lo propio en solo lectura, sin controles de administración ni vista de archivados', async () => {
    renderPagina()

    expect(await screen.findByText('Mi contrato')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Mis documentos', level: 1 })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /subir|archivar|archivados|visible|eliminar|restaurar|editar/i })).not.toBeInTheDocument()
    expect(screen.queryByText('Visibilidad')).not.toBeInTheDocument()
    expect(listarMisDocumentos).toHaveBeenCalled()
  })

  it('abre un documento propio con el contenido autorizado', async () => {
    renderPagina()
    await userEvent.setup().click(await screen.findByRole('button', { name: 'Abrir Mi contrato' }))

    await waitFor(() => expect(abrirBlobEnPestana).toHaveBeenCalledTimes(1))
    expect(vi.mocked(obtenerContenido).mock.calls[0]).toEqual([null, 1, false])
  })

  it('un 404 al abrir no muestra contenido y un 403 explica la falta de permiso', async () => {
    const user = userEvent.setup()
    renderPagina()
    vi.mocked(obtenerContenido).mockRejectedValueOnce(httpError(404))
    await user.click(await screen.findByRole('button', { name: 'Abrir Mi contrato' }))
    expect(await screen.findByText('El documento ya no está disponible.')).toBeInTheDocument()

    vi.mocked(obtenerContenido).mockRejectedValueOnce(httpError(403))
    await user.click(screen.getByRole('button', { name: 'Abrir Mi contrato' }))
    expect(await screen.findByText('No tienes permiso para abrir este documento.')).toBeInTheDocument()
    expect(abrirBlobEnPestana).not.toHaveBeenCalled()
  })

  it('sin permiso documental explica que no tiene permiso', async () => {
    vi.mocked(listarMisDocumentos).mockRejectedValue(httpError(403, { code: 'SIN_PERMISO_DOCUMENTOS', message: 'x' }))
    renderPagina()

    expect(await screen.findByText('No tienes permiso para ver esta información')).toBeInTheDocument()
    expect(screen.queryByText('Mi contrato')).not.toBeInTheDocument()
  })

  it('sin ficha asociada lo informa', async () => {
    vi.mocked(listarMisDocumentos).mockRejectedValue(httpError(403, { code: 'CUENTA_SIN_FICHA', message: 'x' }))
    renderPagina()

    expect(await screen.findByText('Tu cuenta no tiene una ficha asociada')).toBeInTheDocument()
  })

  it('estados cargando y error con Reintentar', async () => {
    vi.mocked(listarMisDocumentos).mockReturnValueOnce(new Promise(() => {}))
    const { unmount } = renderPagina()
    expect(screen.getByRole('status', { name: 'Cargando documentos…' })).toBeInTheDocument()
    unmount()

    vi.mocked(listarMisDocumentos).mockRejectedValueOnce(httpError(404))
    renderPagina()
    expect(await screen.findByText('No se pudieron cargar los documentos')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByText('Mi contrato')).toBeInTheDocument()
  })

  it('la pestaña Documentos de Mi ficha usa la consulta propia y de la ficha del Administrador usa el id', async () => {
    const capacidades = { desarrollo: false, documentos: true, registrar_ingreso: false, exportar: false }
    const { unmount } = renderRoutes(
      <Route path="/" element={<FichaContenido ficha={detalle({ capacidades, acciones: { editar: false } })} esMiFicha onReintentar={vi.fn()} />} />,
      '/',
    )
    await userEvent.setup().click(screen.getByRole('tab', { name: 'Documentos' }))
    expect(await screen.findByText('Mi contrato')).toBeInTheDocument()
    expect(listarMisDocumentos).toHaveBeenCalled()
    unmount()
  })
})
