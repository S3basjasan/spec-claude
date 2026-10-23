// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../../../../test/renderRoutes'
import type { AccionVinculable } from '../api'
import { listarAccionesVinculables } from '../api'
import { SelectorDeAccionVinculable } from './SelectorDeAccionVinculable'

vi.mock('../api', async (importOriginal) => ({ ...(await importOriginal<typeof import('../api')>()), listarAccionesVinculables: vi.fn() }))

const fila = (id: number, nombre: string): AccionVinculable => ({ plan_accion_id: id, plan_id: 3, plan_codigo: 'PF-0001', plan_nombre: 'Plan 2026', plan_estado: 'Ejecutando', nombre, tipo: 'Presencial', fecha_inicio: '2026-08-01', fecha_fin: '2026-08-10', formador: null })

function renderSelector(props: Partial<Parameters<typeof SelectorDeAccionVinculable>[0]> = {}) {
  return renderRoutes(<Route path="/" element={<SelectorDeAccionVinculable open colaboradorId={10} onElegir={vi.fn()} onClose={vi.fn()} {...props} />} />, '/')
}

beforeEach(() => {
  vi.mocked(listarAccionesVinculables).mockReset().mockResolvedValue([fila(55, 'Taller de liderazgo'), fila(56, 'Curso de comunicación')])
})

describe('SelectorDeAccionVinculable', () => {
  it('lista las acciones vinculables y elige una', async () => {
    const usuario = userEvent.setup()
    const onElegir = vi.fn()
    renderSelector({ onElegir })

    await usuario.click(await screen.findByRole('button', { name: 'Vincular: Taller de liderazgo' }))

    expect(onElegir).toHaveBeenCalledWith(expect.objectContaining({ plan_accion_id: 55 }))
    expect(screen.getAllByText(/PF-0001 · Plan 2026/)).toHaveLength(2)
  })

  it('oculta la acción ya vinculada', async () => {
    renderSelector({ actual: 55 })

    expect(await screen.findByRole('button', { name: 'Vincular: Curso de comunicación' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Vincular: Taller de liderazgo' })).not.toBeInTheDocument()
  })

  it('la búsqueda consulta con el texto escrito', async () => {
    const usuario = userEvent.setup()
    renderSelector()

    await usuario.type(await screen.findByLabelText('Buscar acción'), 'lider')

    expect(vi.mocked(listarAccionesVinculables).mock.lastCall).toEqual([10, expect.stringContaining('lider')])
  })

  it('sin resultados y con error lo explica', async () => {
    vi.mocked(listarAccionesVinculables).mockResolvedValueOnce([])
    const { unmount } = renderSelector()
    expect(await screen.findByText('No hay acciones disponibles para vincular.')).toBeInTheDocument()
    unmount()

    vi.mocked(listarAccionesVinculables).mockRejectedValueOnce(new Error('falla'))
    renderSelector()
    expect(await screen.findByText('No se pudieron cargar las acciones.')).toBeInTheDocument()
  })
})
