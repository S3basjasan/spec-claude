// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { opcionesDeColaboradores, resumenAdecuacion } from '../../../../test/adecuacionFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { buscarColaboradores, listarAdecuaciones, type FiltrosEvaluaciones, type Pagina, type ResumenAdecuacion } from '../api'
import { PestanaEvaluaciones } from './PestanaEvaluaciones'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  buscarColaboradores: vi.fn(),
  listarAdecuaciones: vi.fn(),
}))

const todas = [
  resumenAdecuacion({ id: 1, codigo: 'ADE-0001', fecha: '2026-01-10 09:00:00', preparacion: 'Lista', preparacion_etiqueta: 'Lista', brechas: 0, es_vigente: false }),
  resumenAdecuacion({ id: 2, codigo: 'ADE-0002', fecha: '2026-02-15 09:00:00', colaborador: { id: 11, nombre: 'Lucía Andrade' }, puesto_objetivo: { id: 3, nombre: 'Analista senior' }, preparacion: 'NoLista', preparacion_etiqueta: 'No lista', brechas: 3 }),
  resumenAdecuacion({ id: 3, codigo: 'ADE-0003', fecha: '2026-03-20 09:00:00' }),
]

function pagina(items: ResumenAdecuacion[], page = 1): Pagina<ResumenAdecuacion> {
  return { data: items, meta: { page, per_page: 15, total: items.length, last_page: 1 } }
}

function renderLista(entrada = '/adecuacion') {
  return renderRoutes(<Route path="/adecuacion" element={<PestanaEvaluaciones />} />, entrada)
}

beforeEach(() => {
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue(opcionesDeColaboradores())
  vi.mocked(listarAdecuaciones).mockReset().mockImplementation(async (f: FiltrosEvaluaciones) => {
    let r = todas
    if (f.preparacion) r = r.filter((x) => x.preparacion === f.preparacion)
    if (f.colaborador_id) r = r.filter((x) => x.colaborador.id === f.colaborador_id)

    return pagina(r)
  })
})

describe('Evaluaciones (historial)', () => {
  it('lista las evaluaciones con código, fecha, colaborador, puesto, preparación y brechas, sin acciones de edición', async () => {
    renderLista()

    const tabla = await screen.findByRole('table')
    const filas = within(tabla).getAllByRole('row')
    expect(filas).toHaveLength(4)
    expect(within(filas[1]).getByText('ADE-0001')).toBeInTheDocument()
    expect(within(filas[2]).getByText('Lucía Andrade')).toBeInTheDocument()
    expect(within(filas[2]).getByText('Analista senior')).toBeInTheDocument()
    expect(within(filas[2]).getByText('No lista')).toBeInTheDocument()
    expect(within(filas[1]).getByText('2026-01-10')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /editar|eliminar/i })).not.toBeInTheDocument()
    expect(screen.getByText('3 evaluaciones')).toBeInTheDocument()
  })

  it('cada fila enlaza al detalle de solo lectura', async () => {
    renderLista()

    expect(await screen.findByRole('link', { name: 'Ver el detalle de ADE-0002' })).toHaveAttribute('href', '/adecuacion/evaluaciones/2')
  })

  it('los filtros viven en la URL y se aplican al listado', async () => {
    const usuario = userEvent.setup()
    renderLista()
    await screen.findByRole('table')

    await usuario.selectOptions(screen.getByLabelText('Preparación'), 'NoLista')

    await waitFor(() => expect(listarAdecuaciones).toHaveBeenLastCalledWith(expect.objectContaining({ preparacion: 'NoLista', page: 1 })))
    expect(await screen.findAllByRole('row')).toHaveLength(2)
    expect(screen.getByRole('button', { name: 'Limpiar filtros' })).toBeInTheDocument()
  })

  it('lee los filtros de la URL al abrir', async () => {
    renderLista('/adecuacion?colaborador=11&preparacion=NoLista&desde=2026-02-01')

    await waitFor(() => expect(listarAdecuaciones).toHaveBeenCalledWith(expect.objectContaining({ colaborador_id: 11, preparacion: 'NoLista', fecha_desde: '2026-02-01' })))
    expect(await screen.findByLabelText('Desde')).toHaveValue('2026-02-01')
  })

  it('«Limpiar filtros» los quita', async () => {
    const usuario = userEvent.setup()
    renderLista('/adecuacion?preparacion=NoLista')
    await screen.findByRole('table')

    await usuario.click(screen.getByRole('button', { name: 'Limpiar filtros' }))

    await waitFor(() => expect(listarAdecuaciones).toHaveBeenLastCalledWith(expect.not.objectContaining({ preparacion: 'NoLista' })))
    expect(screen.queryByRole('button', { name: 'Limpiar filtros' })).not.toBeInTheDocument()
  })

  it('sin evaluaciones muestra el vacío y con filtros que no coinciden lo dice', async () => {
    vi.mocked(listarAdecuaciones).mockResolvedValue(pagina([]))
    const { unmount } = renderLista()
    expect(await screen.findByText('No hay evaluaciones')).toBeInTheDocument()
    unmount()

    renderLista('/adecuacion?preparacion=Lista')
    expect(await screen.findByText('Ninguna evaluación coincide con los filtros')).toBeInTheDocument()
  })

  it('un fallo de carga permite reintentar', async () => {
    vi.mocked(listarAdecuaciones).mockRejectedValueOnce(new Error('red')).mockResolvedValue(pagina(todas))
    const usuario = userEvent.setup()
    renderLista()

    await usuario.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('table')).toBeInTheDocument()
  })
})
