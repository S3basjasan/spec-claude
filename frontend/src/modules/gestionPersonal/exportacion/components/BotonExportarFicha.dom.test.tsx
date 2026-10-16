// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { descargarBlob, RespuestaNoEsPdfError } from '../../../../shared/api/pdf'
import { exportarFicha } from '../api'
import { MiExportacionPage } from '../pages/MiExportacionPage'
import { BotonExportarFicha } from './BotonExportarFicha'

vi.mock('../api', () => ({ exportarFicha: vi.fn() }))
vi.mock('../../../../shared/api/pdf', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../../../../shared/api/pdf')>()),
  descargarBlob: vi.fn(),
}))

const pdf = { blob: new Blob(['%PDF-1.4'], { type: 'application/pdf' }), nombreDeArchivo: 'Ficha_EMP-001.pdf' }

function renderBoton(colaboradorId: number | null = 7, onSinPermiso?: () => void) {
  return renderRoutes(<Route path="/" element={<BotonExportarFicha colaboradorId={colaboradorId} codigo="EMP-001" onSinPermiso={onSinPermiso} />} />, '/')
}

beforeEach(() => {
  vi.mocked(exportarFicha).mockReset().mockResolvedValue(pdf)
  vi.mocked(descargarBlob).mockReset()
})

describe('BotonExportarFicha', () => {
  it('descarga el PDF con el nombre del servidor y aclara que no se agregó a Documentos', async () => {
    renderBoton()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Exportar ficha' }))

    await waitFor(() => expect(descargarBlob).toHaveBeenCalledWith(pdf.blob, 'Ficha_EMP-001.pdf'))
    expect(exportarFicha).toHaveBeenCalledWith(7)
    expect(await screen.findByText(/no se agregó a Documentos/i)).toBeInTheDocument()
  })

  it('muestra el progreso y evita solicitudes repetidas mientras la petición está en curso', async () => {
    let resolver: (valor: typeof pdf) => void = () => undefined
    vi.mocked(exportarFicha).mockReturnValue(new Promise((resolve) => (resolver = resolve)))
    const user = userEvent.setup()
    renderBoton()

    await user.click(screen.getByRole('button', { name: 'Exportar ficha' }))
    const ocupado = await screen.findByRole('button', { name: 'Generando PDF…' })
    expect(ocupado).toBeDisabled()
    expect(screen.getByText(/generando el pdf de la ficha/i)).toBeInTheDocument()
    await user.click(ocupado)
    expect(exportarFicha).toHaveBeenCalledTimes(1)

    resolver(pdf)
    await waitFor(() => expect(descargarBlob).toHaveBeenCalledTimes(1))
  })

  it('un 503 muestra el error con Reintentar y no descarga nada; el reintento funciona', async () => {
    vi.mocked(exportarFicha).mockRejectedValueOnce(httpError(503))
    const user = userEvent.setup()
    renderBoton()

    await user.click(screen.getByRole('button', { name: 'Exportar ficha' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(/no está disponible/i)
    expect(descargarBlob).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Reintentar' }))
    await waitFor(() => expect(descargarBlob).toHaveBeenCalledTimes(1))
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('una respuesta que no es PDF no se descarga', async () => {
    vi.mocked(exportarFicha).mockRejectedValueOnce(new RespuestaNoEsPdfError())
    renderBoton()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Exportar ficha' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(/no devolvió un PDF válido/i)
    expect(descargarBlob).not.toHaveBeenCalled()
  })

  it('un 403 avisa de la revocación, no ofrece reintentar y notifica al contenedor', async () => {
    vi.mocked(exportarFicha).mockRejectedValueOnce(httpError(403))
    const onSinPermiso = vi.fn()
    renderBoton(7, onSinPermiso)
    await userEvent.setup().click(screen.getByRole('button', { name: 'Exportar ficha' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(/ya no tienes permiso/i)
    expect(screen.queryByRole('button', { name: 'Reintentar' })).not.toBeInTheDocument()
    expect(onSinPermiso).toHaveBeenCalledTimes(1)
  })
})

describe('MiExportacionPage', () => {
  it('exporta la propia sin identificadores y, tras un 403, reemplaza la acción por el estado sin permiso', async () => {
    vi.mocked(exportarFicha).mockRejectedValueOnce(httpError(403))
    renderRoutes(<Route path="/" element={<MiExportacionPage />} />, '/')

    expect(screen.getByRole('heading', { name: 'Exportar mi ficha', level: 1 })).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Exportar ficha' }))

    expect(exportarFicha).toHaveBeenCalledWith(null)
    expect(await screen.findByText('No tienes permiso para exportar tu ficha')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Exportar ficha' })).not.toBeInTheDocument()
  })
})
