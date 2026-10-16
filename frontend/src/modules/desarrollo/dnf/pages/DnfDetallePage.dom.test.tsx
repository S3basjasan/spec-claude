// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { avisos, dnf, resultado } from '../../../../test/dnfFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerDnf } from '../api'
import { DnfDetallePage } from './DnfDetallePage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerDnf: vi.fn(),
}))

function renderPage(entrada = '/dnf/7') {
  return renderRoutes(<Route path="/dnf/:id" element={<DnfDetallePage />} />, entrada)
}

const sinBrechas = (r: ReturnType<typeof resultado>) => ({ ...r, brechas: undefined })

beforeEach(() => {
  vi.mocked(obtenerDnf).mockReset().mockResolvedValue({ data: dnf({ competencias_omitidas: 1 }), avisos: avisos({ competencias_omitidas: 1, sin_evaluacion: 1 }) })
})

describe('DnfDetallePage', () => {
  it('muestra el detalle de una individual en solo lectura con autor, fecha y avisos', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { name: 'DNF-0007 · Diego Paredes' }, { timeout: 5000 })).toBeInTheDocument()
    expect(screen.getByText(/Generada por Marta Ríos/)).toBeInTheDocument()
    expect(screen.getByText('Detalle de solo lectura')).toBeInTheDocument()
    expect(screen.getByText('Se omitió 1 competencia inactiva del perfil.')).toBeInTheDocument()
    expect(screen.getByRole('table', { name: 'Diagnóstico por competencia' })).toBeInTheDocument()
    expect(screen.getByRole('img', { name: /Gráfico del perfil requerido/ })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Editar|Eliminar|Detectar necesidades/ })).not.toBeInTheDocument()
    expect(obtenerDnf).toHaveBeenCalledWith(7, undefined)
  })

  it('tiene un enlace de regreso al historial', async () => {
    renderPage()
    await screen.findByRole('heading', { name: 'DNF-0007 · Diego Paredes' })

    expect(screen.getByRole('link', { name: 'Historial de DNF' })).toHaveAttribute('href', '/dnf/historial')
  })

  it('los filtros del diagnóstico viven en la URL', async () => {
    renderPage('/dnf/7?estado=Cumple')
    await screen.findByRole('heading', { name: 'DNF-0007 · Diego Paredes' })

    expect(screen.getByLabelText('Filtrar por estado')).toHaveValue('Cumple')
    await userEvent.selectOptions(screen.getByLabelText('Filtrar por prioridad'), 'Alta')

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('prioridad=Alta'))
  })

  it('en una masiva muestra el resumen y, con ?colaborador=, el diagnóstico de ese colaborador', async () => {
    const masiva = dnf({
      alcance: 'Departamento',
      departamento: { id: 5, nombre: 'Tecnología' },
      colaboradores: [sinBrechas(resultado({ colaborador_id: 10 })), sinBrechas(resultado({ colaborador_id: 11, nombre: 'Camila Andrade' }))],
    })
    vi.mocked(obtenerDnf).mockImplementation(async (_id, colaboradorId) => ({
      data: colaboradorId ? { ...masiva, colaboradores: [resultado({ colaborador_id: colaboradorId, nombre: 'Camila Andrade' })] } : masiva,
    }))
    renderPage()

    expect(await screen.findByRole('table', { name: 'Resumen por colaborador' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Ver diagnóstico de Camila Andrade' }))

    expect(await screen.findByRole('table', { name: 'Diagnóstico por competencia' })).toBeInTheDocument()
    expect(obtenerDnf).toHaveBeenLastCalledWith(7, 11)
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('colaborador=11')

    await userEvent.click(screen.getByRole('button', { name: 'Volver al resumen' }))
    expect(await screen.findByRole('table', { name: 'Resumen por colaborador' })).toBeInTheDocument()
  })

  it('si la DNF no existe muestra el aviso con enlace al historial', async () => {
    vi.mocked(obtenerDnf).mockRejectedValue(httpError(404, { message: 'La DNF no existe.' }))
    renderPage('/dnf/999')

    expect(await screen.findByRole('heading', { name: 'La DNF no existe' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al historial' })).toHaveAttribute('href', '/dnf/historial')
  })

  it('con un identificador inválido no consulta al servidor', async () => {
    renderPage('/dnf/abc')

    expect(await screen.findByRole('heading', { name: 'La DNF no existe' })).toBeInTheDocument()
    expect(obtenerDnf).not.toHaveBeenCalled()
  })

  it('si falla muestra el error con reintento', async () => {
    vi.mocked(obtenerDnf).mockRejectedValueOnce(httpError(500)).mockResolvedValue({ data: dnf() })
    renderPage()

    expect(await screen.findByText('No se pudo cargar la información')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('heading', { name: 'DNF-0007 · Diego Paredes' })).toBeInTheDocument()
  })
})
