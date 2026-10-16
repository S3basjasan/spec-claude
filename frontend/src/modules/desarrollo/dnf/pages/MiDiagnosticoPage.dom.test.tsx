// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { miDiagnostico as fixture } from '../../../../test/dnfFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { miDiagnostico } from '../api'
import { MiDiagnosticoPage } from './MiDiagnosticoPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  miDiagnostico: vi.fn(),
}))

function renderPage() {
  return renderRoutes(<Route path="/dnf" element={<MiDiagnosticoPage />} />, '/dnf')
}

beforeEach(() => {
  vi.mocked(miDiagnostico).mockReset().mockResolvedValue(fixture())
})

describe('MiDiagnosticoPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(miDiagnostico).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando tu diagnóstico' })).toBeInTheDocument()
  })

  it('muestra el puesto, los indicadores, las brechas prioritarias y las demás secciones', async () => {
    renderPage()

    expect(await screen.findByRole('region', { name: 'Mis brechas prioritarias' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Mis brechas formativas' })).toBeInTheDocument()
    expect(screen.getByText(/Analista de datos \(PU-014\)/)).toBeInTheDocument()
    expect(screen.getByText('DNF-0007')).toBeInTheDocument()
    expect(screen.getByText('Cómo leer las barras')).toBeInTheDocument()
    expect(screen.getByText('Competencias que ya cumples (1)')).toBeInTheDocument()
  })

  it('«Ver cursos sugeridos» está disponible solo para la competencia con oferta', async () => {
    renderPage()

    const enlace = await screen.findByRole('link', { name: 'Ver cursos sugeridos para Análisis de datos' })
    expect(enlace).toHaveAttribute('href', '/formacion?competencia=1')
    expect(screen.getByRole('button', { name: 'Ver cursos sugeridos' })).toBeDisabled()
  })

  it('sin DNF muestra un mensaje informativo en lugar de tarjetas', async () => {
    vi.mocked(miDiagnostico).mockResolvedValue(null)
    renderPage()

    expect(await screen.findByText('Aún no tienes un diagnóstico')).toBeInTheDocument()
    expect(screen.queryByRole('article')).not.toBeInTheDocument()
  })

  it('sin brechas muestra un mensaje informativo y las competencias que cumple', async () => {
    vi.mocked(miDiagnostico).mockResolvedValue(fixture({ prioritarias: [], brechas: [], resumen: { con_brecha: 0, cumplen: 1, sin_evaluacion: 0 } }))
    renderPage()

    expect(await screen.findByText('No tienes brechas detectadas')).toBeInTheDocument()
    expect(screen.queryByRole('article')).not.toBeInTheDocument()
    expect(screen.getByText('Competencias que ya cumples (1)')).toBeInTheDocument()
  })

  it('si falla muestra el error y permite reintentar', async () => {
    vi.mocked(miDiagnostico).mockRejectedValueOnce(httpError(500)).mockResolvedValue(fixture())
    renderPage()

    expect(await screen.findByText('No se pudo cargar tu diagnóstico')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('region', { name: 'Mis brechas prioritarias' })).toBeInTheDocument()
  })

  it('no ofrece controles del prototipo ni de administrador', async () => {
    renderPage()
    await screen.findByRole('region', { name: 'Mis brechas prioritarias' })

    expect(screen.queryByRole('button', { name: /Ver como administrador|Generar/ })).not.toBeInTheDocument()
  })
})
