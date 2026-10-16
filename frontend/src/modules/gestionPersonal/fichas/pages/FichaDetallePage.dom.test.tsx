// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle } from '../../../../test/fichasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerColaborador, obtenerMiFicha } from '../api'
import { FichaDetallePage } from './FichaDetallePage'
import { MiFichaPage } from './MiFichaPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerColaborador: vi.fn(),
  obtenerMiFicha: vi.fn(),
}))

function renderDetalle() {
  return renderRoutes(<Route path="/colaboradores/:colaboradorId" element={<FichaDetallePage />} />, '/colaboradores/1')
}

function renderMiFicha() {
  return renderRoutes(<Route path="/mi-ficha" element={<MiFichaPage />} />, '/mi-ficha')
}

beforeEach(() => {
  vi.mocked(obtenerColaborador).mockReset().mockResolvedValue(detalle())
  vi.mocked(obtenerMiFicha).mockReset().mockResolvedValue(detalle({ acciones: { editar: false } }))
})

describe('FichaDetallePage', () => {
  it('muestra los datos ausentes como «No registrado», 0 hijos como 0 y la trayectoria vacía', async () => {
    vi.mocked(obtenerColaborador).mockResolvedValue(detalle({ numero_hijos: 0 }))
    renderDetalle()

    expect(await screen.findByRole('heading', { name: 'Ana Núñez Paredes', level: 1 })).toBeInTheDocument()
    expect(screen.getByText('EMP-001')).toBeInTheDocument()
    expect(screen.getAllByText('Sin vínculo vigente').length).toBeGreaterThan(0)
    expect(screen.getByText('Cédula')).toBeInTheDocument()
    expect(screen.getByText('Número de hijos').nextElementSibling).toHaveTextContent('0')
    expect(screen.getAllByText('No registrado').length).toBeGreaterThan(5)
    expect(screen.getByText('Todavía no hay hechos laborales registrados para esta persona.')).toBeInTheDocument()
  })

  it('no muestra Desarrollo, Documentos, Registrar ingreso, Exportar ni acciones de eliminar', async () => {
    renderDetalle()
    await screen.findByRole('heading', { name: 'Ana Núñez Paredes', level: 1 })

    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
    expect(screen.queryByText(/Documentos|Desarrollo|Registrar ingreso|Exportar/)).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /eliminar|desactivar/i })).not.toBeInTheDocument()
    expect(screen.queryByText(/adecuación|potencial|salario/i)).not.toBeInTheDocument()
  })

  it('el Administrador ve Editar y el Colaborador en Mi ficha no', async () => {
    renderDetalle()
    expect(await screen.findByRole('link', { name: 'Editar' })).toHaveAttribute('href', '/colaboradores/1/editar')
  })

  it('muestra la trayectoria del más reciente al más antiguo con el hecho futuro «Programado»', async () => {
    vi.mocked(obtenerColaborador).mockResolvedValue(
      detalle({
        trayectoria: {
          disponibilidad: 'disponible',
          hechos: [
            { fecha_efectiva: '2999-01-01', tipo: 'Contratacion', programado: true, detalle: null },
            { fecha_efectiva: '2020-01-10', tipo: 'Ingreso', programado: false, detalle: null },
          ],
        },
      }),
    )
    renderDetalle()

    expect(await screen.findByText('Programado')).toBeInTheDocument()
    const tiempos = screen.getAllByText(/^\d{4}-\d{2}-\d{2}$/)
    expect(tiempos.map((t) => t.textContent)).toEqual(['2999-01-01', '2020-01-10'])
  })

  it('«Información no disponible» con Reintentar cuando la fuente laboral falla, y los datos personales siguen visibles', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerColaborador).mockResolvedValue(
      detalle({
        laboral: { disponibilidad: 'no_disponible', estado: null, puesto: null, unidad: null },
        trayectoria: { disponibilidad: 'no_disponible', hechos: [] },
      }),
    )
    renderDetalle()

    expect(await screen.findAllByText('Información no disponible')).toHaveLength(2)
    expect(screen.getByText('0102030405')).toBeInTheDocument()
    vi.mocked(obtenerColaborador).mockClear()
    await user.click(screen.getAllByRole('button', { name: 'Reintentar' })[0])

    expect(obtenerColaborador).toHaveBeenCalled()
  })

  it('informa cuando la ficha no existe', async () => {
    vi.mocked(obtenerColaborador).mockRejectedValue(httpError(404, { message: 'La ficha no existe.', code: 'FICHA_NO_ENCONTRADA' }))
    renderDetalle()

    expect(await screen.findByText('La ficha no existe')).toBeInTheDocument()
  })
})

describe('MiFichaPage', () => {
  it('muestra la ficha propia en solo lectura', async () => {
    renderMiFicha()

    expect(await screen.findByText('Ana Núñez Paredes')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Editar' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar' })).not.toBeInTheDocument()
  })

  it('informa cuenta sin ficha asociada', async () => {
    vi.mocked(obtenerMiFicha).mockRejectedValue(httpError(403, { message: 'x', code: 'CUENTA_SIN_FICHA' }))
    renderMiFicha()

    expect(await screen.findByText('Tu cuenta no tiene una ficha asociada')).toBeInTheDocument()
  })

  it('informa falta de permiso de consulta', async () => {
    vi.mocked(obtenerMiFicha).mockRejectedValue(httpError(403, { message: 'x', code: 'SIN_PERMISO_FICHA' }))
    renderMiFicha()

    expect(await screen.findByText('No tienes permiso para consultar tu ficha')).toBeInTheDocument()
  })

  it('no muestra la sección Documentos sin permiso documental', async () => {
    renderMiFicha()
    await screen.findByText('Ana Núñez Paredes')

    expect(screen.queryByRole('tab', { name: 'Documentos' })).not.toBeInTheDocument()
  })
})
