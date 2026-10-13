// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { competenciaAsignada, detalle, puesto, requisito } from '../../../../test/puestosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerPuesto } from '../api'
import { PuestosList } from './PuestosList'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPuesto: vi.fn(),
}))

const items = [
  puesto({ id: 1, codigo: 'PU-001', nombre: 'Analista de datos' }),
  puesto({ id: 2, codigo: 'PU-002', nombre: 'Jefe de talento humano' }),
]

function renderLista(esAdmin = false) {
  return renderRoutes(<Route path="/puestos" element={<PuestosList items={items} esAdmin={esAdmin} />} />, '/puestos')
}

beforeEach(() => {
  vi.mocked(obtenerPuesto).mockReset().mockImplementation(async (id) => detalle({ id, nombre: id === 1 ? 'Analista de datos' : 'Jefe de talento humano' }))
})

describe('detalle desplegable de un puesto', () => {
  it('pide el detalle solo al desplegar la fila', async () => {
    renderLista()
    const user = userEvent.setup()

    expect(obtenerPuesto).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: /Analista de datos/ }))

    expect(await screen.findByRole('region', { name: 'Información de Analista de datos' })).toBeInTheDocument()
    expect(obtenerPuesto).toHaveBeenCalledTimes(1)
    expect(obtenerPuesto).toHaveBeenCalledWith(1)
  })

  it('muestra la misión, las funciones numeradas con su tipo, los requisitos por tipo y las competencias', async () => {
    renderLista()
    await userEvent.setup().click(screen.getByRole('button', { name: /Analista de datos/ }))

    const region = within(await screen.findByRole('region', { name: 'Información de Analista de datos' }))
    expect(region.getByRole('heading', { name: 'Misión' })).toBeInTheDocument()
    expect(region.getByText('Transformar los datos de la organización en información confiable.')).toBeInTheDocument()

    // La lista numerada de funciones va antes que las de requisitos.
    const elementos = region.getAllByRole('listitem')
    expect(elementos[0]).toHaveTextContent('Recopilar y consolidar datos.')
    expect(elementos[0]).toHaveTextContent('Esencial')
    expect(elementos[1]).toHaveTextContent('Documentar indicadores.')
    expect(elementos[1]).toHaveTextContent('Complementaria')

    const tipos = region.getAllByRole('heading', { level: 4 }).map((encabezado) => encabezado.textContent)
    expect(tipos).toEqual(['Formación', 'Experiencia', 'Capacitación'])
    expect(region.getByText('Curso de SQL.')).toBeInTheDocument()

    const tabla = within(region.getByRole('table'))
    expect(tabla.getByRole('columnheader', { name: 'Competencia' })).toBeInTheDocument()
    expect(tabla.getByRole('columnheader', { name: 'Tipo' })).toBeInTheDocument()
    expect(tabla.getByRole('columnheader', { name: 'Grado requerido' })).toBeInTheDocument()
    expect(tabla.getByRole('rowheader', { name: 'Trabajo en equipo' })).toBeInTheDocument()
    expect(tabla.getByText('Cardinal')).toBeInTheDocument()
    expect(tabla.getByLabelText('Grado B')).toHaveTextContent('B')
  })

  it('agrupa los requisitos solo con los tipos que tienen elementos', async () => {
    vi.mocked(obtenerPuesto).mockResolvedValue(detalle({ requisitos: [requisito(), requisito({ tipo: 'Experiencia', descripcion: '2 años.' })] }))
    renderLista()
    await userEvent.setup().click(screen.getByRole('button', { name: /Analista de datos/ }))

    const region = within(await screen.findByRole('region'))
    expect(region.getAllByRole('heading', { level: 4 }).map((encabezado) => encabezado.textContent)).toEqual(['Formación', 'Experiencia'])
  })

  it('señala la competencia inactiva y conserva su grado', async () => {
    vi.mocked(obtenerPuesto).mockResolvedValue(
      detalle({ competencias: [competenciaAsignada({ nombre: 'Adaptabilidad', estado: 'Inactivo', grado_requerido: 'C' })] }),
    )
    renderLista()
    await userEvent.setup().click(screen.getByRole('button', { name: /Analista de datos/ }))

    const tabla = within(await screen.findByRole('table'))
    expect(tabla.getByRole('rowheader', { name: /Adaptabilidad/ })).toHaveTextContent('Inactiva')
    expect(tabla.getByLabelText('Grado C')).toBeInTheDocument()
  })

  it('avisa cuando el puesto está inactivo', async () => {
    vi.mocked(obtenerPuesto).mockResolvedValue(detalle({ estado: 'Inactivo' }))
    renderLista()
    await userEvent.setup().click(screen.getByRole('button', { name: /Analista de datos/ }))

    expect(await screen.findByText('Este puesto está inactivo: no puede usarse para crear nuevas posiciones.')).toBeInTheDocument()
  })

  it('mantiene una sola fila abierta a la vez', async () => {
    renderLista()
    const user = userEvent.setup()

    const primera = screen.getByRole('button', { name: /Analista de datos/ })
    const segunda = screen.getByRole('button', { name: /Jefe de talento humano/ })
    await user.click(primera)
    await screen.findByRole('region', { name: 'Información de Analista de datos' })
    expect(primera).toHaveAttribute('aria-expanded', 'true')

    await user.click(segunda)
    await screen.findByRole('region', { name: 'Información de Jefe de talento humano' })

    expect(primera).toHaveAttribute('aria-expanded', 'false')
    expect(segunda).toHaveAttribute('aria-expanded', 'true')
    expect(screen.queryByRole('region', { name: 'Información de Analista de datos' })).not.toBeInTheDocument()
  })

  it('al pulsar de nuevo la fila abierta, la cierra', async () => {
    renderLista()
    const user = userEvent.setup()
    const fila = screen.getByRole('button', { name: /Analista de datos/ })

    await user.click(fila)
    await screen.findByRole('region')
    await user.click(fila)

    await waitFor(() => expect(screen.queryByRole('region')).not.toBeInTheDocument())
  })

  it('con el detalle desplegado el Usuario no ve Editar ni Activar/Desactivar y el Administrador sí ve Editar', async () => {
    const usuario = renderLista(false)
    await userEvent.setup().click(screen.getByRole('button', { name: /Analista de datos/ }))
    await screen.findByRole('region', { name: 'Información de Analista de datos' })

    expect(screen.queryByRole('link', { name: /^Editar/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^(Activar|Desactivar)/ })).not.toBeInTheDocument()
    usuario.unmount()

    renderLista(true)
    await userEvent.setup().click(screen.getByRole('button', { name: /Analista de datos/ }))

    expect(await screen.findByRole('link', { name: 'Editar Analista de datos' })).toHaveAttribute('href', '/puestos/1/editar')
  })

  it('muestra un esqueleto mientras carga el detalle', async () => {
    vi.mocked(obtenerPuesto).mockReturnValue(new Promise(() => {}))
    renderLista()
    await userEvent.setup().click(screen.getByRole('button', { name: /Analista de datos/ }))

    expect(await screen.findByRole('status', { name: 'Cargando información del puesto…' })).toBeInTheDocument()
  })

  it('ante un error ofrece Reintentar', async () => {
    vi.mocked(obtenerPuesto).mockRejectedValueOnce(httpError(500)).mockResolvedValue(detalle())
    renderLista()
    const user = userEvent.setup()
    await user.click(screen.getByRole('button', { name: /Analista de datos/ }))

    expect(await screen.findByText('No se pudo cargar el puesto')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('region', { name: 'Información de Analista de datos' })).toBeInTheDocument()
  })

  it('si el puesto ya no existe lo indica sin ofrecer reintento', async () => {
    vi.mocked(obtenerPuesto).mockRejectedValue(httpError(404))
    renderLista()
    await userEvent.setup().click(screen.getByRole('button', { name: /Analista de datos/ }))

    expect(await screen.findByText('El puesto ya no existe.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reintentar' })).not.toBeInTheDocument()
  })
})
