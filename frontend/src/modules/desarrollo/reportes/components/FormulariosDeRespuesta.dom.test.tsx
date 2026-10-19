// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { responderEncuesta, responderSeguimiento, type EncuestaPendiente, type SeguimientoPendiente } from '../api'
import { FormularioEncuesta } from './FormularioEncuesta'
import { FormularioSeguimiento } from './FormularioSeguimiento'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  responderEncuesta: vi.fn(),
  responderSeguimiento: vi.fn(),
}))

const encuesta: EncuestaPendiente = { actividad_id: 5, codigo: 'ACT-0005', nombre: 'Gestión del tiempo', fecha_fin: '2026-10-10', vence: '2026-11-09' }
const seguimiento: SeguimientoPendiente = { actividad_id: 4, codigo: 'ACT-0004', nombre: 'Liderazgo situacional', fecha_fin: '2026-09-24', hito: 30, vence: '2026-10-24' }
const alCerrar = vi.fn()
const alEnviar = vi.fn()

beforeEach(() => {
  alCerrar.mockReset()
  alEnviar.mockReset()
  vi.mocked(responderEncuesta).mockReset().mockResolvedValue({ actividad_id: 5, calificacion: 4, comentario: null, fecha: '2026-10-17' })
  vi.mocked(responderSeguimiento).mockReset().mockResolvedValue(undefined)
})

describe('FormularioEncuesta', () => {
  const renderForm = () => renderRoutes(<Route path="/" element={<FormularioEncuesta pendiente={encuesta} onCerrar={alCerrar} onEnviada={alEnviar} />} />, '/')

  it('exige una calificación antes de pedir confirmación', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.click(screen.getByRole('button', { name: 'Enviar' }))

    expect(screen.getByText('Elige una calificación de 1 a 5.')).toBeInTheDocument()
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(responderEncuesta).not.toHaveBeenCalled()
  })

  it('pide confirmación (la respuesta no se puede modificar) y luego envía una sola vez', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.click(screen.getByRole('radio', { name: '4 de 5' }))
    await user.type(screen.getByRole('textbox', { name: /Comentario/ }), 'Muy útil')
    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    expect(await screen.findByText('Una vez enviada no podrás modificarla.')).toBeInTheDocument()
    expect(responderEncuesta).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Sí, enviar' }))

    await vi.waitFor(() => expect(responderEncuesta).toHaveBeenCalledTimes(1))
    expect(vi.mocked(responderEncuesta).mock.calls[0][0]).toEqual({ actividad_id: 5, calificacion: 4, comentario: 'Muy útil' })
    await vi.waitFor(() => expect(alEnviar).toHaveBeenCalledTimes(1))
  })

  it('el comentario vacío no se envía', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.click(screen.getByRole('radio', { name: '5 de 5' }))
    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    await user.click(await screen.findByRole('button', { name: 'Sí, enviar' }))

    await vi.waitFor(() => expect(responderEncuesta).toHaveBeenCalled())
    expect(vi.mocked(responderEncuesta).mock.calls[0][0]).toEqual({ actividad_id: 5, calificacion: 5, comentario: undefined })
  })

  it('volver desde la confirmación conserva lo escrito', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.click(screen.getByRole('radio', { name: '3 de 5' }))
    await user.type(screen.getByRole('textbox', { name: /Comentario/ }), 'Texto')
    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    await user.click(await screen.findByRole('button', { name: 'Volver' }))

    expect(await screen.findByRole('radio', { name: '3 de 5' })).toBeChecked()
    expect(screen.getByRole('textbox', { name: /Comentario/ })).toHaveValue('Texto')
    expect(responderEncuesta).not.toHaveBeenCalled()
  })

  it('traduce el error ya_respondida del servidor a un mensaje claro', async () => {
    vi.mocked(responderEncuesta).mockRejectedValue(httpError(422, { message: 'x', code: 'ya_respondida' }))
    const user = userEvent.setup()
    renderForm()

    await user.click(screen.getByRole('radio', { name: '4 de 5' }))
    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    await user.click(await screen.findByRole('button', { name: 'Sí, enviar' }))

    expect(await screen.findByText('Ya respondiste esto: las respuestas no se pueden modificar.')).toBeInTheDocument()
    expect(alEnviar).not.toHaveBeenCalled()
  })

  it('muestra el error por ventana cerrada', async () => {
    vi.mocked(responderEncuesta).mockRejectedValue(httpError(422, { message: 'x', code: 'ventana_cerrada' }))
    const user = userEvent.setup()
    renderForm()

    await user.click(screen.getByRole('radio', { name: '4 de 5' }))
    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    await user.click(await screen.findByRole('button', { name: 'Sí, enviar' }))

    expect(await screen.findByText('El plazo para responder ya terminó.')).toBeInTheDocument()
  })

  it('cancelar cierra sin enviar', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(alCerrar).toHaveBeenCalledTimes(1)
    expect(responderEncuesta).not.toHaveBeenCalled()
  })
})

describe('FormularioSeguimiento', () => {
  const renderForm = () => renderRoutes(<Route path="/" element={<FormularioSeguimiento pendiente={seguimiento} onCerrar={alCerrar} onEnviado={alEnviar} />} />, '/')

  it('exige las dos calificaciones', async () => {
    const user = userEvent.setup()
    renderForm()

    await user.click(screen.getByRole('button', { name: 'Enviar' }))

    expect(screen.getByText('Elige una calificación de aplicación de 1 a 5.')).toBeInTheDocument()
    expect(screen.getByText('Elige una calificación de apoyo de 1 a 5.')).toBeInTheDocument()
    expect(responderSeguimiento).not.toHaveBeenCalled()
  })

  it('el título indica el hito y el envío lleva actividad, hito y las dos calificaciones', async () => {
    const user = userEvent.setup()
    renderForm()
    expect(screen.getByRole('dialog', { name: 'Seguimiento a 30 días: Liderazgo situacional' })).toBeInTheDocument()

    const [aplicacion, apoyo] = screen.getAllByRole('group')
    await user.click(within(aplicacion).getByRole('radio', { name: '5 de 5' }))
    await user.click(within(apoyo).getByRole('radio', { name: '2 de 5' }))
    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    await user.click(await screen.findByRole('button', { name: 'Sí, enviar' }))

    await vi.waitFor(() => expect(responderSeguimiento).toHaveBeenCalledTimes(1))
    expect(vi.mocked(responderSeguimiento).mock.calls[0][0]).toEqual({ actividad_id: 4, hito: 30, aplicacion: 5, apoyo: 2, comentario: undefined })
    await vi.waitFor(() => expect(alEnviar).toHaveBeenCalled())
  })

  it('muestra el error cuando el hito aún no vence', async () => {
    vi.mocked(responderSeguimiento).mockRejectedValue(httpError(422, { message: 'x', code: 'hito_no_vencido' }))
    const user = userEvent.setup()
    renderForm()

    const [aplicacion, apoyo] = screen.getAllByRole('group')
    await user.click(within(aplicacion).getByRole('radio', { name: '4 de 5' }))
    await user.click(within(apoyo).getByRole('radio', { name: '4 de 5' }))
    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    await user.click(await screen.findByRole('button', { name: 'Sí, enviar' }))

    expect(await screen.findByText('Este seguimiento todavía no está disponible.')).toBeInTheDocument()
  })
})
