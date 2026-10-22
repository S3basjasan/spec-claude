// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { nivel, nivelesDeEjemplo } from '../../../../test/rutasFixtures'
import { EscaleraDeNiveles } from './EscaleraDeNiveles'

function renderEscalera(sobrescribir: Partial<React.ComponentProps<typeof EscaleraDeNiveles>> = {}) {
  const props = {
    niveles: nivelesDeEjemplo(),
    seleccionado: 102,
    onSeleccionar: vi.fn(),
    puedeGestionar: true,
    ocupado: false,
    onMover: vi.fn(),
    onQuitar: vi.fn(),
    ...sobrescribir,
  }
  render(<EscaleraDeNiveles {...props} />)
  return props
}

describe('EscaleraDeNiveles', () => {
  it('dibuja el nivel más alto arriba y el nivel 1 abajo', () => {
    renderEscalera()

    const items = within(screen.getByRole('list', { name: 'Escalera de niveles' })).getAllByRole('listitem')
    expect(items.map((li) => li.textContent?.match(/Nivel \d/)?.[0])).toEqual(['Nivel 3', 'Nivel 2', 'Nivel 1'])
  })

  it('marca el nivel seleccionado y avisa al elegir otro', async () => {
    const props = renderEscalera()

    expect(screen.getByRole('button', { name: /Ver nivel 2/ })).toHaveAttribute('aria-pressed', 'true')
    await userEvent.click(screen.getByRole('button', { name: /Ver nivel 1/ }))
    expect(props.onSeleccionar).toHaveBeenCalledWith(101)
  })

  it('subir, bajar y quitar avisan con el nivel correspondiente; los extremos no se pueden mover más', async () => {
    const props = renderEscalera()

    expect(screen.getByRole('button', { name: /Subir Analista senior/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: /Bajar Asistente de datos/ })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: /Subir Analista de datos/ }))
    await userEvent.click(screen.getByRole('button', { name: /Bajar Analista de datos/ }))
    await userEvent.click(screen.getByRole('button', { name: /Quitar Analista de datos/ }))
    expect(props.onMover).toHaveBeenNthCalledWith(1, 102, 1)
    expect(props.onMover).toHaveBeenNthCalledWith(2, 102, -1)
    expect(props.onQuitar).toHaveBeenCalledWith(expect.objectContaining({ id: 102 }))
  })

  it('un nivel en uso no se puede quitar', () => {
    renderEscalera({ niveles: [nivel({ id: 1, orden: 1, nombre: 'Base', en_uso: true }), nivel({ id: 2, orden: 2, nombre: 'Cima' })] })

    expect(screen.getByRole('button', { name: /Quitar Base/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: /Quitar Cima/ })).toBeEnabled()
  })

  it('sin permiso de gestión no ofrece subir, bajar ni quitar', () => {
    renderEscalera({ puedeGestionar: false })

    expect(screen.queryByRole('button', { name: /Subir/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Quitar/ })).not.toBeInTheDocument()
  })

  it('mientras hay una operación pendiente bloquea los controles', () => {
    renderEscalera({ ocupado: true })

    expect(screen.getByRole('button', { name: /Subir Analista de datos/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: /Quitar Analista de datos/ })).toBeDisabled()
  })

  it('advierte con texto, no solo con color, cuando el puesto de un nivel está inactivo', () => {
    renderEscalera({ niveles: [nivel({ puesto: { codigo: 'PU-009', nombre: 'Puesto antiguo', activo: false } })] })

    expect(screen.getByText(/puesto de este nivel está inactivo/)).toBeInTheDocument()
  })
})
