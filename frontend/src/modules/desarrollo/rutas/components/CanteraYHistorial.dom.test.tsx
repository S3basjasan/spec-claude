// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { evento, nivel } from '../../../../test/rutasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarHistorial } from '../api'
import { CanteraPorNivel } from './CanteraPorNivel'
import { HistorialDeRuta } from './HistorialDeRuta'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarHistorial: vi.fn(),
}))

describe('CanteraPorNivel', () => {
  const niveles = [nivel({ id: 1, orden: 1, nombre: 'Base', inscritos_activos: 2 }), nivel({ id: 2, orden: 2, nombre: 'Medio', inscritos_activos: 0 }), nivel({ id: 3, orden: 3, nombre: 'Cima', inscritos_activos: 1 })]

  it('muestra las personas por nivel con un resumen accesible', () => {
    render(<CanteraPorNivel niveles={niveles} sinNivel={0} />)

    expect(screen.getByRole('img', { name: 'Personas por nivel: nivel 1, 2; nivel 2, 0; nivel 3, 1' })).toBeInTheDocument()
    expect(screen.getByText('Nivel 1').closest('div')).toHaveTextContent('2')
  })

  it('avisa de los niveles sin cantera, salvo el de ingreso', () => {
    render(<CanteraPorNivel niveles={niveles} sinNivel={0} />)

    expect(screen.getByText('Sin cantera en nivel 2.')).toBeInTheDocument()
  })

  it('informa aparte los participantes activos sin nivel actual', () => {
    render(<CanteraPorNivel niveles={niveles} sinNivel={3} />)

    expect(screen.getByText('3 participantes activos sin nivel actual definido.')).toBeInTheDocument()
  })
})

describe('HistorialDeRuta', () => {
  beforeEach(() => {
    vi.mocked(listarHistorial).mockReset()
  })

  function renderHistorial() {
    return renderRoutes(<Route path="/" element={<HistorialDeRuta rutaId={1} pagina={1} onPagina={() => {}} />} />, '/')
  }

  it('muestra usuario, fecha, acción, valor anterior, valor nuevo y motivo', async () => {
    vi.mocked(listarHistorial).mockResolvedValue({
      data: [
        evento(),
        evento({ id: 2, tipo: 'Inscripcion', accion: 'retirada', colaborador: 'Ana Núñez', usuario: 'Sistema', motivo: 'Baja del colaborador', valor_anterior: { estado: 'Activa' }, valor_nuevo: { estado: 'Retirada' } }),
      ],
      meta: { page: 1, per_page: 10, total: 2, last_page: 1 },
    })
    renderHistorial()

    const tabla = await screen.findByRole('table', { name: 'Historial de cambios de la ruta' })
    expect(tabla).toHaveTextContent('Cambio de estado')
    expect(tabla).toHaveTextContent('Estado: Borrador')
    expect(tabla).toHaveTextContent('Estado: Vigente')
    expect(tabla).toHaveTextContent('21 oct 2026')
    expect(tabla).toHaveTextContent('Inscripción retirada')
    expect(tabla).toHaveTextContent('Ana Núñez')
    expect(tabla).toHaveTextContent('Sistema')
    expect(tabla).toHaveTextContent('Baja del colaborador')
  })

  it('es de solo lectura: no ofrece ninguna acción de edición', async () => {
    vi.mocked(listarHistorial).mockResolvedValue({ data: [evento()], meta: { page: 1, per_page: 10, total: 1, last_page: 1 } })
    renderHistorial()
    await screen.findByRole('table')

    expect(screen.queryByRole('button', { name: /Editar|Eliminar|Borrar/ })).not.toBeInTheDocument()
  })

  it('sin eventos muestra el estado vacío', async () => {
    vi.mocked(listarHistorial).mockResolvedValue({ data: [], meta: { page: 1, per_page: 10, total: 0, last_page: 1 } })
    renderHistorial()

    expect(await screen.findByText('Todavía no hay cambios registrados.')).toBeInTheDocument()
  })

  it('con error ofrece «Reintentar»', async () => {
    vi.mocked(listarHistorial).mockRejectedValue(new Error('caída'))
    renderHistorial()

    expect(await screen.findByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
  })
})
