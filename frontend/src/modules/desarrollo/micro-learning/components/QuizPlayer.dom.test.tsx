// @vitest-environment jsdom
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { quiz, resultado, sinCorrectas } from '../../../../test/microFixtures'
import type { RespuestaDada, ResultadoRespuesta } from '../api'
import { QuizPlayer } from './QuizPlayer'

const preguntas = sinCorrectas(quiz()).preguntas ?? []

function renderQuiz(opciones: { onResponder?: (p: number, o: number) => Promise<ResultadoRespuesta>; respondidas?: RespuestaDada[]; onVolver?: () => void } = {}) {
  const onResponder = opciones.onResponder ?? vi.fn().mockResolvedValueOnce(resultado({ es_correcta: true, opcion_correcta_id: 101 })).mockResolvedValueOnce(resultado({ es_correcta: false, opcion_correcta_id: 112 }))
  const onReintentar = vi.fn().mockResolvedValue(undefined)
  render(<QuizPlayer preguntas={preguntas} respondidas={opciones.respondidas} onResponder={onResponder} onReintentar={onReintentar} onVolver={opciones.onVolver} />)
  return { onResponder, onReintentar }
}

describe('QuizPlayer', () => {
  it('muestra una pregunta a la vez con el indicador «Pregunta N de M»', () => {
    renderQuiz()

    expect(screen.getByText('Pregunta 1 de 2')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Pregunta uno' })).toBeInTheDocument()
    expect(screen.queryByText('Pregunta dos')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Siguiente pregunta/ })).toBeDisabled()
  })

  it('al elegir una opción muestra al instante si fue correcta', async () => {
    const { onResponder } = renderQuiz()
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: /Lo antes posible/ }))

    expect(onResponder).toHaveBeenCalledWith(10, 101)
    expect(await screen.findByText('Correcto')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Siguiente pregunta/ })).toBeEnabled()
    expect(screen.getByRole('button', { name: /Nunca/ })).toBeDisabled()
  })

  it('si fue incorrecta indica cuál era la opción correcta', async () => {
    const onResponder = vi.fn().mockResolvedValue(resultado({ es_correcta: false, opcion_correcta_id: 101 }))
    renderQuiz({ onResponder })

    await userEvent.setup().click(screen.getByRole('button', { name: /Nunca/ }))

    expect(await screen.findByText('Incorrecto')).toBeInTheDocument()
    expect(screen.getByText('La opción correcta era: Lo antes posible')).toBeInTheDocument()
  })

  it('al terminar muestra el puntaje «N de M» y permite repetir sin aprobado ni reprobado', async () => {
    const volver = vi.fn()
    const { onReintentar } = renderQuiz({ onVolver: volver })
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: /Lo antes posible/ }))
    await user.click(await screen.findByRole('button', { name: 'Siguiente pregunta' }))
    expect(screen.getByText('Pregunta 2 de 2')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: /Opción A2/ }))
    await user.click(await screen.findByRole('button', { name: 'Ver resultado' }))

    expect(screen.getByTestId('puntaje')).toHaveTextContent('1 de 2')
    expect(screen.getByText('1 respuestas correctas de 2')).toBeInTheDocument()
    expect(screen.queryByText(/aprob/i)).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Reintentar quiz' }))
    expect(onReintentar).toHaveBeenCalledOnce()
    await user.click(screen.getByRole('button', { name: 'Volver a Mi aprendizaje' }))
    expect(volver).toHaveBeenCalledOnce()
  })

  it('retoma un intento a medias desde la siguiente pregunta', () => {
    renderQuiz({ respondidas: [{ pregunta_id: 10, opcion_id: 101, es_correcta: true }] })

    expect(screen.getByText('Pregunta 2 de 2')).toBeInTheDocument()
    expect(screen.getByText('Llevas 1 de 2 preguntas respondidas.')).toBeInTheDocument()
  })

  it('si el servidor rechaza la respuesta por intento descartado pide reiniciar', async () => {
    const onResponder = vi.fn().mockRejectedValue(httpError(404, { message: 'x', code: 'no_encontrado' }))
    renderQuiz({ onResponder })

    await userEvent.setup().click(screen.getByRole('button', { name: /Lo antes posible/ }))

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('El quiz cambió o tu intento ya no está disponible'))
    expect(screen.getByRole('button', { name: /Lo antes posible/ })).toBeEnabled()
  })

  it('un fallo de red permite volver a intentar la misma respuesta', async () => {
    const onResponder = vi.fn().mockRejectedValueOnce(httpError(500)).mockResolvedValueOnce(resultado())
    renderQuiz({ onResponder })
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: /Lo antes posible/ }))
    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo registrar tu respuesta')

    await user.click(screen.getByRole('button', { name: /Lo antes posible/ }))
    expect(await screen.findByText('Correcto')).toBeInTheDocument()
  })
})
