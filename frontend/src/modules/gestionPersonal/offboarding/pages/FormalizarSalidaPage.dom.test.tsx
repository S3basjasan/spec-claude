// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { contexto, detalle } from '../../../../test/offboardingFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { buscarColaboradores, formalizarSalida, listarRespaldos, obtenerContextoDeSalida } from '../api'
import { FormalizarSalidaPage } from './FormalizarSalidaPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  buscarColaboradores: vi.fn(),
  obtenerContextoDeSalida: vi.fn(),
  listarRespaldos: vi.fn(),
  formalizarSalida: vi.fn(),
}))

function renderPage() {
  return renderRoutes(
    <>
      <Route path="/offboarding/formalizar" element={<FormalizarSalidaPage />} />
      <Route path="/offboarding/:id" element={<p>Expediente de la salida</p>} />
    </>,
    '/offboarding/formalizar',
  )
}

async function elegirPersona(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('combobox', { name: 'Colaborador' }))
  await user.click(await screen.findByRole('option', { name: /Ana Núñez/ }))
}

async function llenar(user: ReturnType<typeof userEvent.setup>) {
  await elegirPersona(user)
  await screen.findByLabelText('Política de offboarding')
  await user.selectOptions(screen.getByLabelText('Motivo administrativo'), 'Renuncia')
  await user.type(screen.getByLabelText('Fecha efectiva'), '2999-12-01')
}

beforeEach(() => {
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue([{ id: 3, codigo: 'EMP-003', nombre: 'Ana Núñez' }])
  vi.mocked(obtenerContextoDeSalida).mockReset().mockResolvedValue(contexto())
  vi.mocked(listarRespaldos).mockReset().mockResolvedValue([{ id: 9, titulo: 'Carta de renuncia' }])
  vi.mocked(formalizarSalida).mockReset().mockResolvedValue(detalle())
})

describe('FormalizarSalidaPage', () => {
  it('exige colaborador, motivo y fecha efectiva antes de enviar (CA-03)', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Formalizar salida' }))

    expect(await screen.findByText('Elige al colaborador.')).toBeInTheDocument()
    expect(screen.getByText('Elige el motivo administrativo.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa una fecha válida (AAAA-MM-DD).')).toBeInTheDocument()
    expect(formalizarSalida).not.toHaveBeenCalled()
  })

  it('muestra la política vigente y las tareas que se copiarán, incluida la entrevista (CA-02)', async () => {
    const user = userEvent.setup()
    renderPage()

    await elegirPersona(user)

    const politica = await screen.findByLabelText('Política de offboarding')
    expect(politica).toHaveTextContent('Política vigente (versión 2)')
    expect(politica).toHaveTextContent('Devolver equipos')
    expect(politica).toHaveTextContent('Exige evidencia')
    expect(politica).toHaveTextContent('Cerrar accesos')
    expect(politica).toHaveTextContent('Entrevista de salida')
    expect(screen.getByText(/Al formalizar se fija la versión de política vigente/)).toBeInTheDocument()
  })

  it('formaliza con clave de operación y abre el expediente sin dar por terminado el vínculo (CA-01)', async () => {
    const user = userEvent.setup()
    renderPage()

    await llenar(user)
    await user.click(screen.getByRole('checkbox', { name: 'Carta de renuncia' }))
    await user.click(screen.getByRole('button', { name: 'Formalizar salida' }))

    expect(await screen.findByText('Expediente de la salida')).toBeInTheDocument()
    expect(formalizarSalida).toHaveBeenCalledTimes(1)
    const [datos, clave] = vi.mocked(formalizarSalida).mock.calls[0]
    expect(datos).toEqual({ colaborador_id: 3, motivo: 'Renuncia', fecha_efectiva: '2999-12-01', documento_ids: [9] })
    expect(clave).toMatch(/^[0-9a-f-]{36}$/)
  })

  it('«Política sin configurar» impide formalizar y lo explica', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerContextoDeSalida).mockResolvedValue(contexto({ politica: { situacion: 'SinConfigurar', numero: null, tareas: [] } }))
    renderPage()

    await elegirPersona(user)

    expect(await screen.findByText(/Política sin configurar: no se puede formalizar/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Formalizar salida' })).toBeDisabled()
  })

  it('una persona sin vínculo vigente no se puede formalizar', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerContextoDeSalida).mockResolvedValue(contexto({ vinculo_vigente: null }))
    renderPage()

    await elegirPersona(user)

    expect(await screen.findByText(/no tiene un vínculo laboral vigente: no se puede formalizar/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Formalizar salida' })).toBeDisabled()
  })

  it('si ya hay una salida viva ofrece abrirla en vez de formalizar otra', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerContextoDeSalida).mockResolvedValue(contexto({ salida_existente: { id: 8, estado: 'Programada', fecha_efectiva: '2026-11-10' } }))
    renderPage()

    await elegirPersona(user)

    expect(await screen.findByText('Esta persona ya tiene una salida programada.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Abrir el expediente' })).toHaveAttribute('href', '/offboarding/8')
    expect(screen.getByRole('button', { name: 'Formalizar salida' })).toBeDisabled()
  })

  it('un error de política del servidor se distingue de un error genérico y conserva lo escrito', async () => {
    const user = userEvent.setup()
    vi.mocked(formalizarSalida).mockRejectedValue(httpError(503, { code: 'POLITICA_NO_DISPONIBLE', message: 'x' }))
    renderPage()

    await llenar(user)
    await user.click(screen.getByRole('button', { name: 'Formalizar salida' }))

    expect(await screen.findByText(/No se pudo determinar la política de offboarding de forma confiable/)).toBeInTheDocument()
    expect(screen.getByLabelText('Fecha efectiva')).toHaveValue('2999-12-01')
  })

  it('un conflicto se explica sin reenviar y un reintento con lo mismo reutiliza la clave', async () => {
    const user = userEvent.setup()
    vi.mocked(formalizarSalida).mockRejectedValueOnce(httpError(409, { code: 'SALIDA_YA_FORMALIZADA', message: 'x' })).mockResolvedValue(detalle())
    renderPage()

    await llenar(user)
    await user.click(screen.getByRole('button', { name: 'Formalizar salida' }))
    expect(await screen.findByText('Este vínculo ya tiene una salida formalizada.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Formalizar salida' }))
    expect(await screen.findByText('Expediente de la salida')).toBeInTheDocument()
    const claves = vi.mocked(formalizarSalida).mock.calls.map((llamada) => llamada[1])
    expect(claves[0]).toBe(claves[1])
  })
})
