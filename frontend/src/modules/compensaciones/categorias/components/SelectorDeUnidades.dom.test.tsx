// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { unidadDisponible } from '../../../../test/categoriasReconocimientoFixtures'
import type { UnidadDisponible } from '../api'
import { SelectorDeUnidades, type SeleccionDeUnidades } from './SelectorDeUnidades'

const DISPONIBLES: UnidadDisponible[] = [
  unidadDisponible(),
  unidadDisponible({ id: 2, codigo: 'UO-002', nombre: 'Ventas' }),
  unidadDisponible({ id: 3, codigo: 'UO-003', nombre: 'Finanzas' }),
]

function Contenedor({ inicial = { unidades_ids: [], todas: false }, ...resto }: { inicial?: SeleccionDeUnidades; error?: string; inactivas?: string[] }) {
  const [seleccion, setSeleccion] = useState(inicial)
  return (
    <SelectorDeUnidades
      disponibles={DISPONIBLES}
      cargando={false}
      fallo={false}
      seleccion={seleccion}
      onCambiar={setSeleccion}
      onReintentar={vi.fn()}
      {...resto}
    />
  )
}

describe('SelectorDeUnidades', () => {
  it('sin selección muestra «Nadie la verá» y el error del campo', () => {
    render(<Contenedor error="Elige al menos una unidad." />)

    expect(screen.getByText('Nadie la verá.')).toBeInTheDocument()
    expect(screen.getByText('Elige al menos una unidad.')).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'Unidades a las que aplica' })).toBeInTheDocument()
  })

  it('«Todas las unidades» marca todas y cuenta las unidades del alcance', async () => {
    const usuario = userEvent.setup()
    render(<Contenedor />)

    await usuario.click(screen.getByRole('checkbox', { name: 'Todas las unidades' }))

    for (const nombre of ['Operaciones', 'Ventas', 'Finanzas']) {
      expect(screen.getByRole('checkbox', { name: nombre })).toBeChecked()
    }
    expect(screen.getByText('La verán los colaboradores de 3 unidades.')).toBeInTheDocument()
  })

  it('quitar una unidad desmarca «Todas» y conserva el resto; desmarcar «Todas» deja la selección vacía', async () => {
    const usuario = userEvent.setup()
    render(<Contenedor inicial={{ unidades_ids: [1, 2, 3], todas: true }} />)

    await usuario.click(screen.getByRole('checkbox', { name: 'Ventas' }))

    expect(screen.getByRole('checkbox', { name: 'Todas las unidades' })).not.toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Operaciones' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Ventas' })).not.toBeChecked()
    expect(screen.getByText('La verán los colaboradores de 2 unidades.')).toBeInTheDocument()

    await usuario.click(screen.getByRole('checkbox', { name: 'Todas las unidades' }))
    expect(screen.getByRole('checkbox', { name: 'Todas las unidades' })).toBeChecked()
    await usuario.click(screen.getByRole('checkbox', { name: 'Todas las unidades' }))
    expect(screen.getByText('Nadie la verá.')).toBeInTheDocument()
  })

  it('se puede operar con el teclado', async () => {
    const usuario = userEvent.setup()
    render(<Contenedor />)

    await usuario.tab()
    expect(screen.getByRole('checkbox', { name: 'Todas las unidades' })).toHaveFocus()
    await usuario.tab()
    await usuario.keyboard(' ')

    expect(screen.getByRole('checkbox', { name: 'Operaciones' })).toBeChecked()
    expect(screen.getByText('La verán los colaboradores de 1 unidad.')).toBeInTheDocument()
  })

  it('avisa de las unidades asignadas que ya no están activas', () => {
    render(<Contenedor inactivas={['Tecnología']} />)

    expect(screen.getByText('Ya no están activas y se quitarán al guardar: Tecnología.')).toBeInTheDocument()
  })

  it('muestra cargando, error con reintento y vacío sin unidades activas', async () => {
    const onReintentar = vi.fn()
    const base = { seleccion: { unidades_ids: [], todas: false }, onCambiar: vi.fn(), onReintentar }

    const { rerender } = render(<SelectorDeUnidades disponibles={undefined} cargando fallo={false} {...base} />)
    expect(screen.getByRole('status', { name: 'Cargando unidades…' })).toBeInTheDocument()

    rerender(<SelectorDeUnidades disponibles={undefined} cargando={false} fallo {...base} />)
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(onReintentar).toHaveBeenCalledTimes(1)

    rerender(<SelectorDeUnidades disponibles={[]} cargando={false} fallo={false} {...base} />)
    expect(screen.getByText('No hay unidades activas para asignar.')).toBeInTheDocument()
  })
})
