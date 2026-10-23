// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { AxiosError } from 'axios'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { adecuacion, diagnostico, opcionesDeColaboradores, opcionesDeColaborador, resultado, resultadoCumple } from '../../../../test/adecuacionFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { buscarColaboradores, generar, obtenerOpcionesDeColaborador, obtenerPermisos, previsualizar, type ClavePermiso } from '../api'
import { AdecuacionPage } from './AdecuacionPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPermisos: vi.fn(),
  buscarColaboradores: vi.fn(),
  obtenerOpcionesDeColaborador: vi.fn(),
  previsualizar: vi.fn(),
  generar: vi.fn(),
}))

function renderPagina(permisos: ClavePermiso[] = ['desarrollo.adecuacion.generar', 'desarrollo.adecuacion.ver']) {
  vi.mocked(obtenerPermisos).mockResolvedValue(permisos)
  return renderRoutes(<Route path="/adecuacion" element={<AdecuacionPage />} />, '/adecuacion')
}

async function elegirColaborador(nombre = 'Diego Paredes') {
  const usuario = userEvent.setup()
  await usuario.click(await screen.findByRole('combobox', { name: 'Colaborador' }))
  await usuario.click(await screen.findByRole('option', { name: new RegExp(nombre) }))

  return usuario
}

beforeEach(() => {
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue(opcionesDeColaboradores())
  vi.mocked(obtenerOpcionesDeColaborador).mockReset().mockResolvedValue(opcionesDeColaborador())
  vi.mocked(previsualizar).mockReset().mockResolvedValue(diagnostico())
  vi.mocked(generar).mockReset().mockResolvedValue(adecuacion())
})

describe('Adecuación a puesto futuro (Administrador)', () => {
  it('muestra el título, el aviso «guía, no promesa» y solo las pestañas permitidas', async () => {
    renderPagina()

    expect(await screen.findByRole('heading', { level: 1, name: 'Adecuación a puesto futuro' })).toBeInTheDocument()
    expect(screen.getByRole('note')).toHaveTextContent('Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.')
    expect(await screen.findByRole('tab', { name: 'Diagnóstico' })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Comparar candidatos' })).not.toBeInTheDocument()
    expect(screen.getByText('Elige un colaborador y un puesto objetivo para ver el diagnóstico.')).toBeInTheDocument()
  })

  it('al elegir un colaborador con una sola inscripción preselecciona su nivel objetivo y calcula el diagnóstico', async () => {
    renderPagina()
    await elegirColaborador()

    await waitFor(() => expect(previsualizar).toHaveBeenCalledWith({ colaborador_id: 10, inscripcion_id: 9 }))
    expect(await screen.findByRole('region', { name: 'Resumen' })).toHaveTextContent('Asistente de datos → Analista de datos')
    expect(screen.getByRole('region', { name: 'Experiencia' })).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Conocimientos' })).toHaveTextContent('Estructura Empresarial no asigna grado')
    expect(screen.getByRole('region', { name: 'Competencias' })).toBeInTheDocument()
    expect(screen.getByLabelText('Puesto objetivo')).toHaveValue('i:9')
  })

  it('con puesto directo pide la experiencia requerida opcional y la envía', async () => {
    renderPagina()
    const usuario = await elegirColaborador()
    await screen.findByRole('region', { name: 'Resumen' })

    await usuario.selectOptions(screen.getByLabelText('Puesto objetivo'), 'p:3')
    await usuario.type(await screen.findByLabelText('Experiencia requerida (años)'), '5')

    await waitFor(() => expect(previsualizar).toHaveBeenLastCalledWith({ colaborador_id: 10, puesto_objetivo_id: 3, experiencia_requerida_anios: 5 }))
  })

  it('con varias inscripciones el administrador elige cuál usar', async () => {
    const base = opcionesDeColaborador()
    vi.mocked(obtenerOpcionesDeColaborador).mockResolvedValue(
      opcionesDeColaborador({ inscripciones: [base.inscripciones[0], { ...base.inscripciones[0], inscripcion_id: 12, ruta_nombre: 'Carrera gerencial' }] }),
    )
    renderPagina()
    const usuario = await elegirColaborador()

    expect(await screen.findByText('Elige un colaborador y un puesto objetivo para ver el diagnóstico.')).toBeInTheDocument()
    expect(previsualizar).not.toHaveBeenCalled()

    await usuario.selectOptions(screen.getByLabelText('Puesto objetivo'), 'i:12')
    await waitFor(() => expect(previsualizar).toHaveBeenCalledWith({ colaborador_id: 10, inscripcion_id: 12 }))
  })

  it('sin inscripción con objetivo avisa que debe elegir un puesto', async () => {
    vi.mocked(obtenerOpcionesDeColaborador).mockResolvedValue(opcionesDeColaborador({ inscripciones: [] }))
    renderPagina()
    await elegirColaborador()

    expect(await screen.findByText(/no tiene un siguiente nivel en su ruta/)).toBeInTheDocument()
  })

  it('bloquea el registro mientras falten tiempos y lo permite al completarlos', async () => {
    renderPagina()
    const usuario = await elegirColaborador()
    const registrar = await screen.findByRole('button', { name: 'Registrar esta evaluación' })

    expect(registrar).toBeDisabled()
    expect(screen.getByText('Ingresa el tiempo de cierre de cada brecha para poder registrar.')).toBeInTheDocument()

    await usuario.type(screen.getByRole('spinbutton', { name: /Comunicación/ }), '6')
    await usuario.type(screen.getByRole('spinbutton', { name: /Experiencia acumulada/ }), '18')

    expect(registrar).toBeEnabled()
    expect(within(screen.getByRole('region', { name: 'Resumen' })).getByText('No lista')).toBeInTheDocument()
  })

  it('registra la evaluación con los tiempos y las observaciones, tras confirmar', async () => {
    renderPagina()
    const usuario = await elegirColaborador()
    await usuario.type(await screen.findByRole('spinbutton', { name: /Comunicación/ }), '6')
    await usuario.type(screen.getByRole('spinbutton', { name: /Experiencia acumulada/ }), '10')
    await usuario.type(screen.getByLabelText('Observaciones'), 'Candidato prometedor')

    await usuario.click(screen.getByRole('button', { name: 'Registrar esta evaluación' }))
    const dialogo = await screen.findByRole('alertdialog')
    expect(dialogo).toHaveTextContent('Una evaluación registrada no se edita ni se elimina.')
    await usuario.click(within(dialogo).getByRole('button', { name: 'Registrar' }))

    await waitFor(() => expect(generar).toHaveBeenCalledTimes(1))
    expect(vi.mocked(generar).mock.calls[0][0]).toEqual({
      colaborador_id: 10,
      inscripcion_id: 9,
      puesto_objetivo_esperado_id: 2,
      tiempos_cierre: [
        { dimension: 'Competencia', elemento_id: 1, meses: 6 },
        { dimension: 'Experiencia', elemento_id: null, meses: 10 },
      ],
      observaciones: 'Candidato prometedor',
    })
    expect(await screen.findByText('Evaluación registrada como ADE-0001.')).toBeInTheDocument()
  })

  it('en la vista previa los botones del plan de desarrollo quedan deshabilitados hasta registrar la evaluación', async () => {
    renderPagina()
    await elegirColaborador()

    expect(await screen.findByRole('button', { name: 'Crear plan de desarrollo' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Incluir en Programa de Talentos' })).toBeDisabled()
    expect(screen.getByText('Disponible cuando registres la evaluación.')).toBeInTheDocument()
  })

  it('sin brechas la preparación es Lista y el motivo es «No hay brechas.»', async () => {
    vi.mocked(previsualizar).mockResolvedValue(diagnostico({ resultados: [resultadoCumple()] }))
    renderPagina()
    await elegirColaborador()

    expect(await screen.findAllByText('Lista', { selector: 'span' })).not.toHaveLength(0)
    expect(screen.getByRole('button', { name: 'Registrar esta evaluación' })).toBeEnabled()
    expect(screen.getByText('No hay brechas.')).toBeInTheDocument()
  })

  it('con 409 pide recargar el diagnóstico', async () => {
    vi.mocked(generar).mockRejectedValue(new AxiosError('conflicto', '409', undefined, undefined, { status: 409, data: { code: 'objetivo_cambio', message: 'cambió' }, statusText: '', headers: {}, config: {} as never }))
    renderPagina()
    const usuario = await elegirColaborador()
    await usuario.type(await screen.findByRole('spinbutton', { name: /Comunicación/ }), '6')
    await usuario.type(screen.getByRole('spinbutton', { name: /Experiencia acumulada/ }), '10')
    await usuario.click(screen.getByRole('button', { name: 'Registrar esta evaluación' }))
    await usuario.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Registrar' }))

    expect(await screen.findByText(/El puesto objetivo cambió mientras generabas/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Recargar diagnóstico' })).toBeInTheDocument()
  })

  it('muestra los errores de tiempo que devuelve el servidor junto al campo', async () => {
    vi.mocked(generar).mockRejectedValue(
      new AxiosError('invalido', '422', undefined, undefined, {
        status: 422,
        data: { message: 'Ingresa el tiempo estimado de cierre de cada brecha.', errors: { 'tiempos_cierre.Competencia:1': ['Ingresa el tiempo estimado de cierre en meses.'] } },
        statusText: '',
        headers: {},
        config: {} as never,
      }),
    )
    renderPagina()
    const usuario = await elegirColaborador()
    await usuario.type(await screen.findByRole('spinbutton', { name: /Comunicación/ }), '6')
    await usuario.type(screen.getByRole('spinbutton', { name: /Experiencia acumulada/ }), '10')
    await usuario.click(screen.getByRole('button', { name: 'Registrar esta evaluación' }))
    await usuario.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Registrar' }))

    expect(await screen.findByText('Ingresa el tiempo estimado de cierre en meses.')).toBeInTheDocument()
  })

  it('un error del diagnóstico por regla de negocio (422) se explica con su mensaje', async () => {
    vi.mocked(previsualizar).mockRejectedValue(
      new AxiosError('x', '422', undefined, undefined, { status: 422, data: { message: 'El puesto objetivo no tiene requisitos que evaluar.' }, statusText: '', headers: {}, config: {} as never }),
    )
    renderPagina()
    await elegirColaborador()

    expect(await screen.findByText('El puesto objetivo no tiene requisitos que evaluar.')).toBeInTheDocument()
  })

  it('un fallo de carga permite reintentar', async () => {
    vi.mocked(previsualizar).mockRejectedValueOnce(new Error('red')).mockResolvedValue(diagnostico({ resultados: [resultado(), resultadoCumple()] }))
    renderPagina()
    const usuario = await elegirColaborador()

    await usuario.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('region', { name: 'Resumen' })).toBeInTheDocument()
  })
})
