// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { hito, resumen } from '../../../../test/pdiFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import type { FilaDeGantt } from '../api'
import { listarSinPlan } from '../api'
import { BloqueSinPlan } from './BloqueSinPlan'
import { KpisDePdi } from './KpisDePdi'
import { LineaDeTiempoGantt } from './LineaDeTiempoGantt'
import { TablaDePdi } from './TablaDePdi'

vi.mock('../api', async (importOriginal) => ({ ...(await importOriginal<typeof import('../api')>()), listarSinPlan: vi.fn() }))

describe('TablaDePdi', () => {
  it('muestra el código, el departamento, el responsable y los hitos vencidos con texto', () => {
    renderRoutes(<Route path="/" element={<TablaDePdi planes={[resumen({ hitos_vencidos: 2, avance: 50 }), resumen({ id: 2, codigo: 'PDI-0002' })]} />} />, '/')

    const tabla = screen.getByRole('table')
    expect(within(tabla).getByText(/PDI-0001 · Tecnología/)).toBeInTheDocument()
    expect(within(tabla).getByText('2 · Vencido')).toBeInTheDocument()
    expect(within(tabla).getByText('50 %')).toBeInTheDocument()
    expect(within(tabla).getByRole('link', { name: 'Abrir PDI-0002' })).toHaveAttribute('href', '/pdi/2')
  })
})

describe('KpisDePdi', () => {
  it('muestra los cuatro indicadores', () => {
    render(<KpisDePdi kpis={{ borrador: 1, acordado: 2, cerrado: 3, hitos_vencidos: 4 }} />)

    for (const [texto, valor] of [['Borradores', '1'], ['Acordados', '2'], ['Cerrados', '3'], ['Hitos vencidos', '4']]) {
      expect(screen.getByText(texto).parentElement).toHaveTextContent(valor)
    }
  })
})

describe('BloqueSinPlan', () => {
  beforeEach(() => vi.mocked(listarSinPlan).mockReset())

  it('sin permiso de gestión no se muestra ni consulta', () => {
    renderRoutes(<Route path="/" element={<BloqueSinPlan departamentoId={undefined} pagina={1} habilitado={false} onPagina={vi.fn()} />} />, '/')

    expect(screen.queryByText('Personas con brecha y sin PDI')).not.toBeInTheDocument()
    expect(listarSinPlan).not.toHaveBeenCalled()
  })

  it('sin personas muestra que todas tienen su plan', async () => {
    vi.mocked(listarSinPlan).mockResolvedValue({ data: [], meta: { page: 1, per_page: 5, total: 0, last_page: 1 } })
    renderRoutes(<Route path="/" element={<BloqueSinPlan departamentoId={undefined} pagina={1} habilitado onPagina={vi.fn()} />} />, '/')

    expect(await screen.findByText('Todas las personas con brechas tienen su plan.')).toBeInTheDocument()
  })

  it('un error muestra un aviso seguro', async () => {
    vi.mocked(listarSinPlan).mockRejectedValueOnce(new Error('falla'))
    renderRoutes(<Route path="/" element={<BloqueSinPlan departamentoId={undefined} pagina={1} habilitado onPagina={vi.fn()} />} />, '/')

    expect(await screen.findByText('No se pudo cargar este indicador.')).toBeInTheDocument()
  })
})

const filaGantt = (): FilaDeGantt => ({
  plan: { id: 1, codigo: 'PDI-0001', colaborador: { id: 10, nombre: 'Diego Paredes', departamento: 'Tecnología' }, tipo: 'Mejora', estado: 'Acordado', responsable: { id: 1, nombre: 'Marta Ríos', sin_permiso: false }, fecha_inicio: '2026-07-01', fecha_fin: '2026-12-31', avance: 40, hitos_vencidos: 1 },
  acciones: [{ id: 100, compromiso: 'Comunicación', descripcion: 'Curso de comunicación', tipo: 'Curso', fecha_inicio: '2026-07-15', fecha_fin: '2026-09-30', estado: 'EnCurso' }],
  hitos: [hito({ vencido: true })],
})

describe('LineaDeTiempoGantt', () => {
  it('muestra la leyenda con texto, la franja y una alternativa tabular con acciones e hitos', () => {
    renderRoutes(<Route path="/" element={<LineaDeTiempoGantt filas={[filaGantt()]} hoy="2026-09-01" />} />, '/')

    const leyenda = screen.getByRole('list', { name: 'Leyenda' })
    for (const e of ['Cumplida', 'En curso', 'Pendiente', 'Cancelada', 'Hito']) expect(within(leyenda).getByText(e)).toBeInTheDocument()
    const tabla = screen.getByRole('table', { name: /Tabla de acciones y hitos · PDI-0001/ })
    expect(within(tabla).getByText('Curso de comunicación')).toBeInTheDocument()
    expect(within(tabla).getByText(/Hito: Primera revisión/)).toBeInTheDocument()
    expect(within(tabla).getByText('Vencido')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Abrir plan PDI-0001' })).toHaveAttribute('href', '/pdi/1')
  })
})
