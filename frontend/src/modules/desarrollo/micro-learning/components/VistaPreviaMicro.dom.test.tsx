// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { micro, quiz } from '../../../../test/microFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import * as api from '../api'
import { VistaPreviaMicro } from './VistaPreviaMicro'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerMicro: vi.fn(),
  guardarAvance: vi.fn(),
  completarMicro: vi.fn(),
  iniciarIntento: vi.fn(),
  responderPregunta: vi.fn(),
}))

function renderPrevia(props: { id?: number; micro?: api.Micro }) {
  return renderRoutes(<Route path="/" element={<VistaPreviaMicro {...props} onCerrar={() => {}} />} />, '/')
}

function ningunaPeticionDeProgreso() {
  expect(api.guardarAvance).not.toHaveBeenCalled()
  expect(api.completarMicro).not.toHaveBeenCalled()
  expect(api.iniciarIntento).not.toHaveBeenCalled()
  expect(api.responderPregunta).not.toHaveBeenCalled()
}

beforeEach(() => {
  vi.mocked(api.obtenerMicro).mockReset()
  vi.mocked(api.guardarAvance).mockReset()
  vi.mocked(api.completarMicro).mockReset()
  vi.mocked(api.iniciarIntento).mockReset()
  vi.mocked(api.responderPregunta).mockReset()
})

describe('VistaPreviaMicro', () => {
  it('muestra un video tal como lo ve el colaborador y no registra nada', async () => {
    vi.mocked(api.obtenerMicro).mockResolvedValue(micro({ id: 3, titulo: 'Mi video', tipo: 'Video' }))
    renderPrevia({ id: 3 })
    const user = userEvent.setup()

    expect(await screen.findByRole('heading', { name: 'Mi video' })).toBeInTheDocument()
    expect(screen.getByText('Reproductor de video (marcador de posición)')).toBeInTheDocument()
    expect(screen.getByText('Apto para conocimientos')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reproducir' }))
    await user.click(screen.getByRole('button', { name: 'Pausar' }))

    ningunaPeticionDeProgreso()
    expect(screen.queryByRole('button', { name: 'Marcar como completado' })).not.toBeInTheDocument()
  })

  it('muestra un PDF con el acceso a su URL', async () => {
    renderPrevia({ micro: micro({ tipo: 'PDF', url: 'https://contenido.test/a.pdf', titulo: 'Guía' }) })

    expect(await screen.findByText('Visor de documento PDF (marcador de posición)')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Abrir contenido de Guía/ })).toHaveAttribute('href', 'https://contenido.test/a.pdf')
  })

  it('resuelve un quiz en el cliente con retroalimentación inmediata, sin intentos ni progreso', async () => {
    renderPrevia({ micro: quiz() })
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Nunca/ }))
    expect(await screen.findByText('Incorrecto')).toBeInTheDocument()
    expect(screen.getByText('La opción correcta era: Lo antes posible')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Siguiente pregunta' }))
    await user.click(screen.getByRole('button', { name: /Opción B2/ }))
    await user.click(await screen.findByRole('button', { name: 'Ver resultado' }))

    expect(screen.getByTestId('puntaje')).toHaveTextContent('1 de 2')
    await user.click(screen.getByRole('button', { name: 'Reintentar quiz' }))
    expect(screen.getByText('Pregunta 1 de 2')).toBeInTheDocument()
    ningunaPeticionDeProgreso()
  })

  it('un borrador de quiz sin preguntas completas avisa que no hay qué mostrar', async () => {
    renderPrevia({ micro: quiz({ preguntas: [] }) })

    expect(await screen.findByText('Aún no hay preguntas completas para mostrar.')).toBeInTheDocument()
  })

  it('al fallar la lectura ofrece reintentar', async () => {
    vi.mocked(api.obtenerMicro).mockRejectedValueOnce(new Error('x')).mockResolvedValue(micro({ titulo: 'Recuperado' }))
    renderPrevia({ id: 1 })
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('heading', { name: 'Recuperado' })).toBeInTheDocument()
  })
})
