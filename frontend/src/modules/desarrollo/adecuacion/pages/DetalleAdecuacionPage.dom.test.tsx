// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import { AxiosError } from 'axios'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { adecuacion } from '../../../../test/adecuacionFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerAdecuacion } from '../api'
import { DetalleAdecuacionPage } from './DetalleAdecuacionPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerAdecuacion: vi.fn(),
}))

function renderDetalle() {
  return renderRoutes(<Route path="/adecuacion/evaluaciones/:id" element={<DetalleAdecuacionPage />} />, '/adecuacion/evaluaciones/1')
}

beforeEach(() => {
  vi.mocked(obtenerAdecuacion).mockReset().mockResolvedValue(adecuacion({ observaciones: 'Revisar en seis meses' }))
})

describe('Detalle de una evaluación', () => {
  it('muestra la auditoría, los tres paneles en solo lectura y las observaciones', async () => {
    renderDetalle()

    expect(await screen.findByRole('heading', { level: 1, name: 'Evaluación ADE-0001' })).toBeInTheDocument()
    expect(screen.getByText('Generada por Marta Ríos el 2026-10-21')).toBeInTheDocument()
    expect(screen.getByRole('note')).toHaveTextContent('Una evaluación registrada no se edita ni se elimina.')
    const titulos = screen.getAllByRole('heading', { level: 2 }).map((h) => h.textContent)
    expect(titulos.slice(0, 3)).toEqual(['Experiencia', 'Conocimientos', 'Competencias'])
    expect(screen.getByText('Revisar en seis meses')).toBeInTheDocument()
    expect(screen.getByText('Es la evaluación vigente de este colaborador para este puesto.')).toBeInTheDocument()
    expect(screen.getByText('Umbral de preparación aplicado: 12 meses')).toBeInTheDocument()
  })

  it('no ofrece editar ni eliminar y habilita las acciones del plan hacia el asistente del PDI', async () => {
    renderDetalle()
    await screen.findByRole('heading', { level: 1 })

    expect(screen.queryByRole('button', { name: /editar|eliminar|borrar/i })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Crear plan de desarrollo' }).getAttribute('href')).toMatch(/^\/pdi\/nuevo\?colaborador=\d+&tipo=Carrera&puesto_objetivo=\d+$/)
    expect(screen.getByRole('link', { name: 'Incluir en Programa de Talentos' }).getAttribute('href')).toMatch(/&tipo=Talento&/)
  })

  it('avisa si hay una evaluación más reciente', async () => {
    vi.mocked(obtenerAdecuacion).mockResolvedValue(adecuacion({ es_vigente: false }))
    renderDetalle()

    expect(await screen.findByText('Hay una evaluación más reciente de este colaborador para este puesto.')).toBeInTheDocument()
  })

  it('muestra los tiempos de cierre guardados en las brechas', async () => {
    renderDetalle()

    const competencias = await screen.findByRole('region', { name: 'Competencias' })
    expect(within(competencias).getByText('Con brecha')).toBeInTheDocument()
  })

  it('si no existe lo explica y ofrece volver', async () => {
    vi.mocked(obtenerAdecuacion).mockRejectedValue(new AxiosError('x', '404', undefined, undefined, { status: 404, data: {}, statusText: '', headers: {}, config: {} as never }))
    renderDetalle()

    expect(await screen.findByText('La evaluación no existe')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Evaluaciones' })).toHaveAttribute('href', '/adecuacion?pestana=evaluaciones')
  })
})
