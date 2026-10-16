// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import { Tabs } from './Tabs'

function Ejemplo() {
  const [activa, setActiva] = useState('uno')
  return (
    <Tabs label="Vistas" tabs={[{ id: 'uno', label: 'Uno' }, { id: 'dos', label: 'Dos' }]} active={activa} onChange={setActiva}>
      <p>Contenido de {activa}</p>
    </Tabs>
  )
}

describe('Tabs', () => {
  it('marca la pestaña activa y asocia el panel', () => {
    render(<Ejemplo />)

    expect(screen.getByRole('tablist', { name: 'Vistas' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Uno' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Dos' })).toHaveAttribute('aria-selected', 'false')
    expect(screen.getByRole('tabpanel')).toHaveAccessibleName('Uno')
    expect(screen.getByText('Contenido de uno')).toBeInTheDocument()
  })

  it('cambia con clic y con las flechas del teclado', async () => {
    const usuario = userEvent.setup()
    render(<Ejemplo />)

    await usuario.click(screen.getByRole('tab', { name: 'Dos' }))
    expect(screen.getByText('Contenido de dos')).toBeInTheDocument()

    await usuario.keyboard('{ArrowRight}')
    expect(screen.getByRole('tab', { name: 'Uno' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Uno' })).toHaveFocus()

    await usuario.keyboard('{ArrowLeft}')
    expect(screen.getByRole('tab', { name: 'Dos' })).toHaveAttribute('aria-selected', 'true')
  })
})
