// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { intento, micro, progreso, quiz, resultado, sinCorrectas } from '../../../../test/microFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { completarMicro, descargarCertificado, guardarAvance, iniciarIntento, obtenerMicro, responderPregunta } from '../api'
import { ReproduccionPage } from './ReproduccionPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerMicro: vi.fn(),
  guardarAvance: vi.fn(),
  completarMicro: vi.fn(),
  iniciarIntento: vi.fn(),
  responderPregunta: vi.fn(),
  descargarCertificado: vi.fn(),
}))

function renderPage(id = 1) {
  return renderRoutes(<Route path="/micro-learning/:id" element={<ReproduccionPage />} />, `/micro-learning/${id}`)
}

const colaborador = (m: ReturnType<typeof micro>, p = progreso()) => ({ ...m, completaciones: undefined, puede_eliminarse: undefined, tiene_intentos: undefined, progreso: p })

beforeEach(() => {
  vi.mocked(obtenerMicro).mockReset().mockResolvedValue(colaborador(micro(), null as never))
  vi.mocked(guardarAvance).mockReset().mockResolvedValue()
  vi.mocked(completarMicro).mockReset().mockResolvedValue()
  vi.mocked(iniciarIntento).mockReset()
  vi.mocked(responderPregunta).mockReset()
  vi.mocked(descargarCertificado).mockReset().mockResolvedValue()
})

describe('ReproduccionPage', () => {
  it('un video muestra el reproductor, registra el inicio y ofrece «Marcar como completado»', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Cómo dar retroalimentación' })).toBeInTheDocument()
    expect(screen.getByText('Reproductor de video (marcador de posición)')).toBeInTheDocument()
    expect(screen.getByText('Apto para conocimientos')).toBeInTheDocument()
    await waitFor(() => expect(guardarAvance).toHaveBeenCalledWith(1, 0))
    expect(screen.getByRole('button', { name: 'Marcar como completado' })).toBeInTheDocument()
  })

  it('retoma el video desde la última posición guardada y no vuelve a registrar el inicio', async () => {
    vi.mocked(obtenerMicro).mockResolvedValue(colaborador(micro(), progreso({ posicion_segundos: 95 })))
    renderPage()

    expect(await screen.findByRole('button', { name: 'Reanudar desde 1:35' })).toBeInTheDocument()
    expect(screen.getByText('Retomas donde lo dejaste: 1:35')).toBeInTheDocument()
    expect(guardarAvance).not.toHaveBeenCalled()
  })

  it('marcar como completado llama al servidor y muestra el progreso registrado', async () => {
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Marcar como completado' }))

    await waitFor(() => expect(completarMicro).toHaveBeenCalledWith(1))
    expect(await screen.findByText('Progreso registrado.')).toBeInTheDocument()
  })

  it('si falla el completado lo informa y deja reintentar', async () => {
    vi.mocked(completarMicro).mockRejectedValue(httpError(500))
    renderPage()

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Marcar como completado' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo marcar como completado')
    expect(screen.getByRole('button', { name: 'Marcar como completado' })).toBeEnabled()
  })

  it('un contenido ya completado muestra su estado, no ofrece el botón y permite descargar el certificado', async () => {
    vi.mocked(obtenerMicro).mockResolvedValue(colaborador(micro({ tipo: 'PDF' }), progreso({ estado: 'Completado', fecha_completado: '2026-10-16T10:00:00Z' })))
    renderPage()
    const user = userEvent.setup()

    expect(await screen.findByText('Este contenido ya está completado.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Marcar como completado' })).not.toBeInTheDocument()
    expect(guardarAvance).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: 'Descargar certificado' }))
    await waitFor(() => expect(descargarCertificado).toHaveBeenCalledWith(1, 'MICRO-0001'))
  })

  it('un PDF o artículo da acceso a su URL', async () => {
    vi.mocked(obtenerMicro).mockResolvedValue(colaborador(micro({ tipo: 'Articulo', url: 'https://contenido.test/a', titulo: 'Mi artículo' }), null as never))
    renderPage()

    expect(await screen.findByRole('link', { name: /Abrir contenido de Mi artículo/ })).toHaveAttribute('href', 'https://contenido.test/a')
    await waitFor(() => expect(guardarAvance).toHaveBeenCalledWith(1, null))
  })

  it('un quiz se comienza, se responde con retroalimentación y muestra el puntaje final', async () => {
    vi.mocked(obtenerMicro).mockResolvedValue(colaborador(sinCorrectas(quiz()), null as never))
    vi.mocked(iniciarIntento).mockResolvedValue(intento())
    vi.mocked(responderPregunta)
      .mockResolvedValueOnce(resultado({ es_correcta: true, opcion_correcta_id: 101 }))
      .mockResolvedValueOnce(resultado({ es_correcta: true, opcion_correcta_id: 112, intento: { estado: 'Finalizado', respondidas: [], puntaje: 2, total_preguntas: 2 } }))
    renderPage(5)
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Comenzar quiz' }))
    expect(iniciarIntento).toHaveBeenCalledWith(5, false)
    expect(await screen.findByText('Pregunta 1 de 2')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: /Lo antes posible/ }))
    expect(responderPregunta).toHaveBeenCalledWith(77, 10, 101)
    await user.click(await screen.findByRole('button', { name: 'Siguiente pregunta' }))
    await user.click(screen.getByRole('button', { name: /Opción B2/ }))
    await user.click(await screen.findByRole('button', { name: 'Ver resultado' }))

    expect(screen.getByTestId('puntaje')).toHaveTextContent('2 de 2')
    expect(screen.getByRole('button', { name: 'Reintentar quiz' })).toBeInTheDocument()
  })

  it('reintentar el quiz inicia otro intento', async () => {
    vi.mocked(obtenerMicro).mockResolvedValue(colaborador(sinCorrectas(quiz()), progreso({ estado: 'Completado', ultimo_puntaje: { correctas: 1, total: 2 } })))
    vi.mocked(iniciarIntento).mockResolvedValueOnce(intento({ id: 78 }))
    renderPage(5)

    expect(await screen.findByText('Último puntaje: 1 de 2')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar quiz' }))

    expect(await screen.findByText('Pregunta 1 de 2')).toBeInTheDocument()
    expect(guardarAvance).not.toHaveBeenCalled()
  })

  it('no ofrece «Marcar como completado» en un quiz y si no inicia avisa', async () => {
    vi.mocked(obtenerMicro).mockResolvedValue(colaborador(sinCorrectas(quiz()), null as never))
    vi.mocked(iniciarIntento).mockRejectedValue(httpError(500))
    renderPage(5)

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Comenzar quiz' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo iniciar el quiz')
    expect(screen.queryByRole('button', { name: 'Marcar como completado' })).not.toBeInTheDocument()
  })

  it('un contenido inexistente o no accesible muestra que no está disponible', async () => {
    vi.mocked(obtenerMicro).mockRejectedValue(httpError(404, { message: 'x' }))
    renderPage(9)

    expect(await screen.findByText('Este contenido no está disponible.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver a Mi aprendizaje' })).toHaveAttribute('href', '/micro-learning')
  })

  it('un error de red ofrece reintentar', async () => {
    vi.mocked(obtenerMicro).mockRejectedValueOnce(httpError(500)).mockResolvedValue(colaborador(micro(), progreso()))
    renderPage()

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('heading', { name: 'Cómo dar retroalimentación' })).toBeInTheDocument()
  })
})
