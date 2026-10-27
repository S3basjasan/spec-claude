// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { miDesvinculacion } from '../../../../test/offboardingFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerMiDesvinculacion } from '../api'
import { MiDesvinculacionPage } from './MiDesvinculacionPage'

vi.mock('../api', async (importOriginal) => ({ ...(await importOriginal<typeof import('../api')>()), obtenerMiDesvinculacion: vi.fn() }))

function renderPage() {
  return renderRoutes(<Route path="/mi-desvinculacion" element={<MiDesvinculacionPage />} />, '/mi-desvinculacion')
}

beforeEach(() => {
  vi.mocked(obtenerMiDesvinculacion).mockReset().mockResolvedValue(miDesvinculacion())
})

describe('MiDesvinculacionPage', () => {
  it('muestra motivo, fecha efectiva y actividades, con la entrevista como una tarea voluntaria (HU-06, CA-10)', async () => {
    renderPage()

    const resumen = await screen.findByRole('definition', { name: '' }).catch(() => null)
    expect(resumen).toBeNull()
    expect(await screen.findByText('Renuncia voluntaria')).toBeInTheDocument()
    expect(screen.getByText('2026-11-10')).toBeInTheDocument()
    expect(screen.getByText('Devolver equipos')).toBeInTheDocument()
    expect(screen.getByText(/La entrevista de salida es voluntaria/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Responder la entrevista' })).toHaveAttribute('href', '/mi-desvinculacion/entrevista')
  })

  it('no expone datos administrativos: ni respaldos, ni política, ni incidencias', async () => {
    renderPage()

    await screen.findByText('Renuncia voluntaria')

    expect(screen.queryByText(/Respaldo/)).not.toBeInTheDocument()
    expect(screen.queryByText(/Política/)).not.toBeInTheDocument()
    expect(screen.queryByText(/Incidencia/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/Cierre/)).not.toBeInTheDocument()
  })

  it('con la entrevista en curso invita a continuar y con una entrevista cerrada no ofrece responder', async () => {
    vi.mocked(obtenerMiDesvinculacion).mockResolvedValue(miDesvinculacion({ entrevista: { estado: 'EnCurso', version: 2, responder_disponible: true, cuestionario_version: 1 } }))
    const { unmount } = renderPage()
    expect(await screen.findByRole('link', { name: 'Continuar la entrevista' })).toBeInTheDocument()
    unmount()

    vi.mocked(obtenerMiDesvinculacion).mockResolvedValue(miDesvinculacion({ entrevista: { estado: 'Completada', version: 3, responder_disponible: false, cuestionario_version: 1 } }))
    renderPage()
    expect(await screen.findByText('La entrevista ya no admite cambios.')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Responder la entrevista' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver mis respuestas' })).toBeInTheDocument()
  })

  it('una salida efectiva se muestra como tal', async () => {
    vi.mocked(obtenerMiDesvinculacion).mockResolvedValue(miDesvinculacion({ estado: 'Efectiva' }))
    renderPage()

    expect(await screen.findByText('Efectiva')).toBeInTheDocument()
  })

  it('sin proceso de salida lo explica, y un error de carga se distingue y permite reintentar', async () => {
    vi.mocked(obtenerMiDesvinculacion).mockRejectedValue(httpError(404, { code: 'EXPEDIENTE_NO_ENCONTRADO' }))
    const { unmount } = renderPage()
    expect(await screen.findByText('No tienes un proceso de salida')).toBeInTheDocument()
    unmount()

    vi.mocked(obtenerMiDesvinculacion).mockRejectedValue(httpError(500))
    renderPage()
    expect(await screen.findByText('No se pudo cargar tu desvinculación')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
  })

  it('una cuenta sin ficha asociada lo dice', async () => {
    vi.mocked(obtenerMiDesvinculacion).mockRejectedValue(httpError(404, { code: 'SIN_FICHA_ASOCIADA' }))
    renderPage()

    expect(await screen.findByText('Tu cuenta no está asociada a una ficha de colaborador.')).toBeInTheDocument()
  })
})
