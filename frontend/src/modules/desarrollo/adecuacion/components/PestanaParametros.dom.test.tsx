// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AxiosError } from 'axios'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarParametros, obtenerParametrosDeAdecuacion, type Parametros } from '../api'
import { PestanaParametros } from './PestanaParametros'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerParametrosDeAdecuacion: vi.fn(),
  actualizarParametros: vi.fn(),
}))

function parametros(sobrescribir: Partial<Parametros> = {}): Parametros {
  return {
    pesos: { Competencia: 40, Conocimiento: 30, Experiencia: 30 },
    umbral_meses: 12,
    historial: [],
    meta: { page: 1, per_page: 10, total: 0, last_page: 1 },
    ...sobrescribir,
  }
}

function renderParametros() {
  return renderRoutes(<Route path="/adecuacion" element={<PestanaParametros />} />, '/adecuacion?pestana=parametros')
}

function rechazo422(errors: Record<string, string[]>, message = 'Datos no válidos.') {
  return new AxiosError('x', '422', undefined, undefined, { status: 422, data: { message, errors }, statusText: '', headers: {}, config: {} as never })
}

beforeEach(() => {
  vi.mocked(obtenerParametrosDeAdecuacion).mockReset().mockResolvedValue(parametros())
  vi.mocked(actualizarParametros).mockReset().mockResolvedValue(parametros())
})

describe('Parámetros de la adecuación', () => {
  it('muestra los pesos 40/30/30, el umbral de 12 meses, la suma y el historial vacío', async () => {
    renderParametros()

    expect(await screen.findByLabelText('Competencias (%)')).toHaveValue(40)
    expect(screen.getByLabelText('Conocimientos (%)')).toHaveValue(30)
    expect(screen.getByLabelText('Experiencia (%)')).toHaveValue(30)
    expect(screen.getByLabelText('Umbral de preparación (meses)')).toHaveValue(12)
    expect(screen.getByText('Suma: 100 %')).toBeInTheDocument()
    expect(screen.getByText('Aún no se ha cambiado ningún parámetro.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Guardar cambios' })).toBeDisabled()
  })

  it('con pesos que no suman 100 no envía y lo avisa junto a los campos', async () => {
    const usuario = userEvent.setup()
    renderParametros()
    const experiencia = await screen.findByLabelText('Experiencia (%)')

    await usuario.clear(experiencia)
    await usuario.type(experiencia, '20')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Los pesos deben sumar 100.')
    expect(screen.getByText('Suma: 90 %')).toBeInTheDocument()
    expect(actualizarParametros).not.toHaveBeenCalled()
  })

  it('rechaza un umbral inválido', async () => {
    const usuario = userEvent.setup()
    renderParametros()
    const umbral = await screen.findByLabelText('Umbral de preparación (meses)')

    await usuario.clear(umbral)
    await usuario.type(umbral, '0')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('El umbral debe ser un número entero de meses entre 1 y 120.')).toBeInTheDocument()
    expect(actualizarParametros).not.toHaveBeenCalled()
  })

  it('guarda pesos válidos y el umbral nuevo, y confirma', async () => {
    const usuario = userEvent.setup()
    renderParametros()
    const competencias = await screen.findByLabelText('Competencias (%)')
    const conocimientos = screen.getByLabelText('Conocimientos (%)')
    const umbral = screen.getByLabelText('Umbral de preparación (meses)')

    await usuario.clear(competencias)
    await usuario.type(competencias, '50')
    await usuario.clear(conocimientos)
    await usuario.type(conocimientos, '20')
    await usuario.clear(umbral)
    await usuario.type(umbral, '6')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(actualizarParametros).toHaveBeenCalledWith({ pesos: { Competencia: 50, Conocimiento: 20, Experiencia: 30 }, umbral_meses: 6 }))
    expect(await screen.findByText('Parámetros guardados.')).toBeInTheDocument()
  })

  it('conserva lo escrito y muestra el error del servidor por campo', async () => {
    vi.mocked(actualizarParametros).mockRejectedValue(rechazo422({ umbral_meses: ['El umbral debe ser de al menos un mes.'] }))
    const usuario = userEvent.setup()
    renderParametros()
    const umbral = await screen.findByLabelText('Umbral de preparación (meses)')

    await usuario.clear(umbral)
    await usuario.type(umbral, '9')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('El umbral debe ser de al menos un mes.')).toBeInTheDocument()
    expect(screen.getByLabelText('Umbral de preparación (meses)')).toHaveValue(9)
  })

  it('muestra el historial con usuario, fecha, valor anterior y valor nuevo', async () => {
    vi.mocked(obtenerParametrosDeAdecuacion).mockResolvedValue(
      parametros({
        historial: [{ campo: 'umbral_meses', valor_anterior: '12', valor_nuevo: '6', usuario: { id: 1, nombre: 'Marta Ríos' }, fecha: '2026-10-21 09:30:00' }],
        meta: { page: 1, per_page: 10, total: 1, last_page: 1 },
      }),
    )
    renderParametros()

    const historial = await screen.findByRole('region', { name: 'Historial de cambios' })
    const fila = within(historial).getAllByRole('row')[1]
    expect(within(fila).getByText('2026-10-21 09:30')).toBeInTheDocument()
    expect(within(fila).getByText('Marta Ríos')).toBeInTheDocument()
    expect(within(fila).getByText('Umbral de preparación')).toBeInTheDocument()
    expect(within(fila).getByText('12')).toBeInTheDocument()
    expect(within(fila).getByText('6')).toBeInTheDocument()
  })

  it('un fallo de carga permite reintentar', async () => {
    vi.mocked(obtenerParametrosDeAdecuacion).mockRejectedValueOnce(new Error('red')).mockResolvedValue(parametros())
    const usuario = userEvent.setup()
    renderParametros()

    await usuario.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByLabelText('Competencias (%)')).toBeInTheDocument()
  })
})
