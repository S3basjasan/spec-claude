// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { desarrollo, experiencia, formacion, movilidad } from '../../../../test/desarrolloFixtures'
import { detalle } from '../../../../test/fichasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { FichaContenido } from '../../fichas/components/FichaContenido'
import { obtenerDesarrollo } from '../api'
import { SeccionDesarrollo } from './SeccionDesarrollo'

vi.mock('../api', async (importOriginal) => ({ ...(await importOriginal<typeof import('../api')>()), obtenerDesarrollo: vi.fn() }))

beforeEach(() => {
  vi.mocked(obtenerDesarrollo)
    .mockReset()
    .mockResolvedValue(
      desarrollo({
        acciones: { editar: false },
        formaciones: [formacion({ titulo_programa: 'Maestría' })],
        experiencias: [experiencia({ cargo: 'Analista' })],
        movilidad: movilidad({ estado: 'Disponible' }),
      }),
    )
})

describe('SeccionDesarrollo solo lectura', () => {
  it('sin acciones.editar no hay Agregar, Editar ni Actualizar disponibilidad', async () => {
    renderRoutes(<Route path="/" element={<SeccionDesarrollo colaboradorId={1} />} />, '/')

    expect(await screen.findByText('Maestría')).toBeInTheDocument()
    expect(screen.getByText('Analista')).toBeInTheDocument()
    expect(screen.getByText('Disponible')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /agregar|editar|actualizar|eliminar/i })).not.toBeInTheDocument()
  })

  it('Mi ficha consulta el desarrollo propio al activar la pestaña', async () => {
    const ficha = detalle({ capacidades: { desarrollo: true, documentos: false, registrar_ingreso: false, exportar: false }, acciones: { editar: false } })
    renderRoutes(<Route path="/" element={<FichaContenido ficha={ficha} esMiFicha onReintentar={vi.fn()} />} />, '/')

    expect(obtenerDesarrollo).not.toHaveBeenCalled()
    await userEvent.setup().click(screen.getByRole('tab', { name: 'Desarrollo' }))

    expect(await screen.findByText('Maestría')).toBeInTheDocument()
    expect(obtenerDesarrollo).toHaveBeenCalledWith(null)
  })

  it('en la ficha del Administrador se consulta por el id de la ficha', async () => {
    const ficha = detalle({ id: 7, capacidades: { desarrollo: true, documentos: false, registrar_ingreso: false, exportar: false } })
    renderRoutes(<Route path="/" element={<FichaContenido ficha={ficha} onReintentar={vi.fn()} />} />, '/')

    await userEvent.setup().click(screen.getByRole('tab', { name: 'Desarrollo' }))

    await screen.findByText('Maestría')
    expect(obtenerDesarrollo).toHaveBeenCalledWith(7)
  })
})
