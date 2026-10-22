// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { resultado, resultadoCumple, resultadoExperiencia } from '../../../../test/adecuacionFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerMiAdecuacion, type AdecuacionPropia, type MiAdecuacion } from '../api'
import { MiAdecuacionPage } from './MiAdecuacionPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerMiAdecuacion: vi.fn(),
}))

function propia(sobrescribir: Partial<AdecuacionPropia> = {}): AdecuacionPropia {
  return {
    id: 1,
    codigo: 'ADE-0001',
    fecha: '2026-10-21T10:00:00-05:00',
    puesto_objetivo: { id: 2, nombre: 'Analista de datos' },
    preparacion: 'EnDesarrollo',
    preparacion_etiqueta: 'En desarrollo',
    resultados: [resultado({ tiempo_cierre_meses: 6 }), resultadoCumple(), resultadoExperiencia()],
    cumplimiento_por_dimension: { Experiencia: 0.6, Conocimiento: null, Competencia: 0.83 },
    cumplimiento_por_dimension_actual: { Experiencia: null, Conocimiento: null, Competencia: 1 },
    brechas: [
      { dimension: 'Competencia', elemento_id: 1, nombre: 'Comunicación', diferencia: -1, tiempo_cierre_meses: 6, curso: { disponible: true, ruta: '/formacion?competencia=1' } },
      { dimension: 'Experiencia', elemento_id: null, nombre: null, diferencia: -2, tiempo_cierre_meses: 18, curso: null },
    ],
    ...sobrescribir,
  }
}

function datos(adecuaciones: AdecuacionPropia[], seleccionada: number | null = adecuaciones[0]?.id ?? null): MiAdecuacion {
  return { adecuaciones, seleccionada, aviso: 'Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.', fuentes_no_disponibles: [] }
}

function renderPagina(entrada = '/adecuacion') {
  return renderRoutes(
    <>
      <Route path="/adecuacion" element={<MiAdecuacionPage />} />
      <Route path="/formacion" element={<p>Catálogo formativo</p>} />
      <Route path="/rutas" element={<p>Mi ruta</p>} />
    </>,
    entrada,
  )
}

beforeEach(() => {
  vi.mocked(obtenerMiAdecuacion).mockReset().mockResolvedValue(datos([propia()]))
})

describe('Mi adecuación (Colaborador)', () => {
  it('muestra el puesto, el aviso permanente, la preparación y las tres tarjetas en el orden del tablero', async () => {
    renderPagina()

    expect(await screen.findByRole('heading', { level: 1, name: 'Mi adecuación a Analista de datos' })).toBeInTheDocument()
    expect(screen.getByRole('note')).toHaveTextContent('Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.')
    expect(screen.getByRole('note')).toHaveTextContent('Solo ves tu propia adecuación.')
    expect(screen.getByText('En desarrollo')).toBeInTheDocument()
    const titulos = screen.getAllByRole('heading', { level: 2 }).map((h) => h.textContent)
    expect(titulos.slice(0, 3)).toEqual(['Experiencia', 'Conocimientos', 'Competencias'])
  })

  it('cada requisito muestra su diferencia y el resumen «Nivel actual · Requerido · Diferencia»', async () => {
    renderPagina()

    const competencias = await screen.findByRole('region', { name: 'Competencias' })
    expect(within(competencias).getByText('Nivel actual: C · Requerido: B · Diferencia: −1')).toBeInTheDocument()
    expect(within(competencias).getByText('83 %')).toBeInTheDocument()
    expect(within(screen.getByRole('region', { name: 'Experiencia' })).getByText('Experiencia: 3 años · Requerida: 5 años · Diferencia: −2')).toBeInTheDocument()
    expect(within(screen.getByRole('region', { name: 'Conocimientos' })).getByText(/no asigna grado/)).toBeInTheDocument()
  })

  it('«Lo que me falta» ofrece cursos en las competencias, el mensaje de práctica en la experiencia y el tiempo de cierre', async () => {
    renderPagina()

    const falta = await screen.findByRole('region', { name: 'Lo que me falta' })
    const enlace = within(falta).getByRole('link', { name: 'Ver cursos sugeridos para Comunicación' })
    expect(enlace).toHaveAttribute('href', '/formacion?competencia=1')
    expect(within(falta).getByText('Tiempo estimado de cierre: 6 meses')).toBeInTheDocument()
    expect(within(falta).getByText('Tiempo estimado de cierre: 18 meses')).toBeInTheDocument()
    expect(within(falta).getByText('Se desarrolla con práctica; revisa tu plan de desarrollo')).toBeInTheDocument()
    expect(within(falta).getAllByRole('link')).toHaveLength(1)
  })

  it('sin oferta formativa dice que aún no hay cursos sugeridos', async () => {
    vi.mocked(obtenerMiAdecuacion).mockResolvedValue(datos([propia({ brechas: [{ dimension: 'Competencia', elemento_id: 1, nombre: 'Comunicación', diferencia: -1, tiempo_cierre_meses: 6, curso: null }] })]))
    renderPagina()

    expect(await screen.findByText('Aún no hay cursos sugeridos')).toBeInTheDocument()
  })

  it('sin brechas lo dice', async () => {
    vi.mocked(obtenerMiAdecuacion).mockResolvedValue(datos([propia({ brechas: [], preparacion: 'Lista', resultados: [resultadoCumple()] })]))
    renderPagina()

    expect(await screen.findByText('Cumples todos los requisitos de este puesto.')).toBeInTheDocument()
  })

  it('con adecuaciones a varios puestos permite cambiar entre ellos', async () => {
    const otra = propia({ id: 2, puesto_objetivo: { id: 3, nombre: 'Analista senior' }, preparacion: 'NoLista' })
    vi.mocked(obtenerMiAdecuacion).mockImplementation(async (puesto) => datos([propia(), otra], puesto === 3 ? 2 : 1))
    const usuario = userEvent.setup()
    renderPagina()

    const selector = await screen.findByLabelText('Puesto a comparar')
    expect(within(selector).getAllByRole('option').map((o) => o.textContent)).toEqual(['Analista de datos', 'Analista senior'])

    await usuario.selectOptions(selector, '3')

    expect(await screen.findByRole('heading', { level: 1, name: 'Mi adecuación a Analista senior' })).toBeInTheDocument()
    expect(obtenerMiAdecuacion).toHaveBeenLastCalledWith(3)
  })

  it('sin adecuación muestra el estado vacío', async () => {
    vi.mocked(obtenerMiAdecuacion).mockResolvedValue(datos([]))
    renderPagina()

    expect(await screen.findByText('Aún no tienes una adecuación')).toBeInTheDocument()
    expect(screen.getByText('Cuando Talento Humano la genere, la verás aquí.')).toBeInTheDocument()
  })

  it('nunca muestra puntajes de comparación y el programa de talentos es informativo', async () => {
    renderPagina()

    await screen.findByRole('heading', { level: 1 })
    expect(screen.queryByText(/puntaje/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/ranking/i)).not.toBeInTheDocument()
    expect(screen.getByText('Por ahora no estás incluido en un Programa de Talentos.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Mi ruta de carrera' })).toHaveAttribute('href', '/rutas')
  })

  it('un fallo de carga permite reintentar', async () => {
    vi.mocked(obtenerMiAdecuacion).mockRejectedValueOnce(new Error('red')).mockResolvedValue(datos([propia()]))
    const usuario = userEvent.setup()
    renderPagina()

    await usuario.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('heading', { level: 1, name: /Analista de datos/ })).toBeInTheDocument()
  })
})
