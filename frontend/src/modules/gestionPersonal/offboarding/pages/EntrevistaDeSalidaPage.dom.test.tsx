// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { miEntrevista } from '../../../../test/offboardingFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { guardarMiEntrevista, obtenerMiEntrevista, resolverMiEntrevista } from '../api'
import { EntrevistaDeSalidaPage } from './EntrevistaDeSalidaPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerMiEntrevista: vi.fn(),
  guardarMiEntrevista: vi.fn(),
  resolverMiEntrevista: vi.fn(),
}))

function renderPage() {
  return renderRoutes(
    <>
      <Route path="/mi-desvinculacion/entrevista" element={<EntrevistaDeSalidaPage />} />
      <Route path="/mi-desvinculacion" element={<p>Mi desvinculación</p>} />
    </>,
    '/mi-desvinculacion/entrevista',
  )
}

beforeEach(() => {
  vi.mocked(obtenerMiEntrevista).mockReset().mockResolvedValue(miEntrevista())
  vi.mocked(guardarMiEntrevista).mockReset().mockResolvedValue(miEntrevista({ estado: 'EnCurso', version: 2 }))
  vi.mocked(resolverMiEntrevista).mockReset().mockResolvedValue(miEntrevista({ estado: 'Completada', cerrada: true, version: 3 }))
})

describe('EntrevistaDeSalidaPage', () => {
  it('presenta el cuestionario como voluntario y con todas las preguntas opcionales (CA-11, CA-13)', async () => {
    renderPage()

    expect(await screen.findByText(/Tu participación es voluntaria y todas las preguntas son opcionales/)).toBeInTheDocument()
    expect(await screen.findByText('¿Qué tan satisfecho estuvo con las oportunidades de crecimiento y desarrollo profesional?')).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: /Compensación/ })).toBeInTheDocument()
    expect(screen.getByLabelText('¿Desea compartir alguna observación adicional?')).toBeInTheDocument()
    // Sin responder es la opción por defecto: nada se interpreta como un valor.
    expect(screen.getAllByRole('radio', { name: 'Sin responder' }).every((r) => (r as HTMLInputElement).checked)).toBe(true)
  })

  it('guardar avance envía lo respondido con la versión y la clave, sin presentar (CA-12)', async () => {
    const user = userEvent.setup()
    renderPage()

    const grupo = (await screen.findByText('¿Qué tan satisfecho estuvo con las oportunidades de crecimiento y desarrollo profesional?')).closest('fieldset') as HTMLElement
    await user.click(grupo.querySelector('input[type=radio]') as HTMLInputElement) // 1 · Muy insatisfecho
    await user.click(screen.getByRole('checkbox', { name: /Compensación/ }))
    await user.type(screen.getByLabelText('¿Desea compartir alguna observación adicional?'), 'Gracias por todo')
    await user.click(screen.getByRole('button', { name: 'Guardar avance' }))

    await waitFor(() => expect(guardarMiEntrevista).toHaveBeenCalledTimes(1))
    const [cuerpo, clave] = vi.mocked(guardarMiEntrevista).mock.calls[0]
    expect(cuerpo).toEqual({ version: 1, valoraciones: { 'DIM-DESARROLLO': 1 }, categorias: ['CAT-COMPENSACION'], abiertas: { 'ABI-06': 'Gracias por todo' } })
    expect(clave).toMatch(/^[0-9a-f-]{36}$/)
    expect(await screen.findByText('Avance guardado. Aún no presentaste la entrevista.')).toBeInTheDocument()
    expect(resolverMiEntrevista).not.toHaveBeenCalled()
  })

  it('puede dejar una valoración sin responder después de elegirla: no se envía como cero', async () => {
    const user = userEvent.setup()
    renderPage()

    const grupo = (await screen.findByText('¿Qué tan satisfecho estuvo, en términos generales, con su experiencia laboral?')).closest('fieldset') as HTMLElement
    const radios = grupo.querySelectorAll<HTMLInputElement>('input[type=radio]')
    await user.click(radios[2])
    await user.click(radios[radios.length - 1]) // Sin responder
    await user.click(screen.getByRole('button', { name: 'Guardar avance' }))

    await waitFor(() => expect(guardarMiEntrevista).toHaveBeenCalled())
    expect(vi.mocked(guardarMiEntrevista).mock.calls[0][0].valoraciones).toEqual({})
  })

  it('el texto demasiado largo se rechaza antes de enviar', async () => {
    const user = userEvent.setup()
    renderPage()

    const campo = await screen.findByLabelText('¿Desea compartir alguna observación adicional?')
    await user.click(campo)
    await user.paste('a'.repeat(2001))
    await user.click(screen.getByRole('button', { name: 'Guardar avance' }))

    expect(await screen.findByText('La respuesta no puede superar 2000 caracteres.')).toBeInTheDocument()
    expect(guardarMiEntrevista).not.toHaveBeenCalled()
  })

  it('presentar guarda lo escrito, confirma con la versión nueva y vuelve a «Mi desvinculación» (CA-15)', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.type(await screen.findByLabelText('¿Desea compartir alguna observación adicional?'), 'Hola')
    await user.click(screen.getByRole('button', { name: 'Presentar la entrevista' }))
    expect(await screen.findByText(/no podrás modificar tus respuestas/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Presentar' }))

    expect(await screen.findByText('Mi desvinculación')).toBeInTheDocument()
    expect(guardarMiEntrevista).toHaveBeenCalledTimes(1)
    expect(resolverMiEntrevista).toHaveBeenCalledWith('confirmacion', 2, expect.any(String))
  })

  it('no participar la deja «no realizada» sin consecuencias y lo dice (CA-11)', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'No deseo participar' }))
    expect(await screen.findByText(/Esto no afecta tu salida ni tus derechos laborales/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'No participar' }))

    expect(await screen.findByText('Mi desvinculación')).toBeInTheDocument()
    expect(resolverMiEntrevista).toHaveBeenCalledWith('declinacion', 1, expect.any(String))
    expect(guardarMiEntrevista).not.toHaveBeenCalled()
  })

  it('retoma el avance guardado', async () => {
    vi.mocked(obtenerMiEntrevista).mockResolvedValue(
      miEntrevista({ estado: 'EnCurso', version: 2, respuestas: { valoraciones: { 'DIM-GENERAL': 4 }, categorias: ['CAT-DESARROLLO'], abiertas: { 'ABI-01': 'Busqué crecer.' } } }),
    )
    renderPage()

    expect(await screen.findByDisplayValue('Busqué crecer.')).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: /Desarrollo profesional/ })).toBeChecked()
    expect(screen.getAllByRole('radio', { name: '4 · Satisfecho' }).filter((r) => (r as HTMLInputElement).checked)).toHaveLength(1)
  })

  it('una entrevista cerrada es de solo lectura y no ofrece guardar ni presentar (CA-15)', async () => {
    vi.mocked(obtenerMiEntrevista).mockResolvedValue(
      miEntrevista({ estado: 'Completada', cerrada: true, version: 3, respuestas: { valoraciones: { 'DIM-GENERAL': 4 }, categorias: ['CAT-COMPENSACION'], abiertas: { 'ABI-01': 'Busqué crecer.' } } }),
    )
    renderPage()

    expect(await screen.findByText('Ya presentaste tu entrevista; no puede modificarse.')).toBeInTheDocument()
    expect(screen.getByText('4 · Satisfecho')).toBeInTheDocument()
    expect(screen.getByText('Busqué crecer.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar avance' })).not.toBeInTheDocument()
  })

  it('una entrevista cerrada como no realizada lo dice sin mostrar respuestas', async () => {
    vi.mocked(obtenerMiEntrevista).mockResolvedValue(miEntrevista({ estado: 'NoRealizada', cerrada: true, version: 2 }))
    renderPage()

    expect(await screen.findByText('La entrevista fue cerrada como no realizada.')).toBeInTheDocument()
    expect(screen.queryByText('Respuestas originales')).not.toBeInTheDocument()
  })

  it('un conflicto de versión se explica y no reenvía en silencio', async () => {
    const user = userEvent.setup()
    vi.mocked(guardarMiEntrevista).mockRejectedValue(httpError(409, { code: 'EXPEDIENTE_MODIFICADO' }))
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Guardar avance' }))

    expect(await screen.findByText('El expediente cambió')).toBeInTheDocument()
    expect(guardarMiEntrevista).toHaveBeenCalledTimes(1)
  })

  it('una entrevista que se cerró mientras escribía se explica (ENTREVISTA_CERRADA)', async () => {
    const user = userEvent.setup()
    vi.mocked(guardarMiEntrevista).mockRejectedValue(httpError(409, { code: 'ENTREVISTA_CERRADA' }))
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Guardar avance' }))

    expect(await screen.findByText('La entrevista ya fue presentada o cerrada y no puede modificarse.')).toBeInTheDocument()
  })

  it('sin proceso de salida lo explica y un error de carga permite reintentar', async () => {
    vi.mocked(obtenerMiEntrevista).mockRejectedValue(httpError(404, { code: 'EXPEDIENTE_NO_ENCONTRADO' }))
    const { unmount } = renderPage()
    expect(await screen.findByText('No tienes un proceso de salida')).toBeInTheDocument()
    unmount()

    vi.mocked(obtenerMiEntrevista).mockRejectedValue(httpError(500))
    renderPage()
    expect(await screen.findByText('No se pudo cargar la entrevista')).toBeInTheDocument()
  })
})
