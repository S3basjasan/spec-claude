// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { Combobox } from './Combobox'

const opciones = [
  { value: '1', label: 'Comunicación efectiva', description: 'COMP-0003' },
  { value: '2', label: 'Pensamiento analítico', description: 'COMP-0005' },
  { value: '3', label: 'Liderazgo' },
]

function Controlado({ onChange = () => {}, error }: { onChange?: (valor: string | null) => void; error?: string }) {
  const [valor, setValor] = useState<string | null>(null)
  return (
    <Combobox
      id="competencia"
      label="Competencia"
      options={opciones}
      value={valor}
      error={error}
      onChange={(nuevo) => {
        setValor(nuevo)
        onChange(nuevo)
      }}
    />
  )
}

describe('Combobox', () => {
  it('expone el rol combobox con aria-expanded y abre la lista al enfocar', async () => {
    render(<Controlado />)
    const campo = screen.getByRole('combobox', { name: 'Competencia' })

    expect(campo).toHaveAttribute('aria-expanded', 'false')
    await userEvent.setup().click(campo)

    expect(campo).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getAllByRole('option')).toHaveLength(3)
  })

  it('filtra al escribir sin distinguir mayúsculas ni acentos', async () => {
    render(<Controlado />)
    const user = userEvent.setup()

    await user.type(screen.getByRole('combobox'), 'COMUNICACION')

    expect(screen.getAllByRole('option')).toHaveLength(1)
    expect(screen.getByRole('option', { name: /Comunicación efectiva/ })).toBeInTheDocument()
  })

  it('muestra "Sin resultados" cuando nada coincide', async () => {
    render(<Controlado />)

    await userEvent.setup().type(screen.getByRole('combobox'), 'zzz')

    expect(screen.queryAllByRole('option')).toHaveLength(0)
    expect(screen.getByText('Sin resultados')).toBeInTheDocument()
  })

  it('las flechas mueven la opción activa y Enter la elige', async () => {
    const onChange = vi.fn()
    render(<Controlado onChange={onChange} />)
    const user = userEvent.setup()
    const campo = screen.getByRole('combobox')

    await user.click(campo)
    await user.keyboard('{ArrowDown}')
    expect(campo).toHaveAttribute('aria-activedescendant', 'competencia-opcion-2')
    await user.keyboard('{Enter}')

    expect(onChange).toHaveBeenCalledWith('2')
    expect(campo).toHaveValue('Pensamiento analítico')
    expect(campo).toHaveAttribute('aria-expanded', 'false')
  })

  it('Escape cierra la lista sin cambiar la selección', async () => {
    render(<Controlado />)
    const user = userEvent.setup()
    const campo = screen.getByRole('combobox')

    await user.type(campo, 'lid')
    await user.keyboard('{Escape}')

    expect(campo).toHaveAttribute('aria-expanded', 'false')
    expect(campo).toHaveValue('')
  })

  it('elegir con el ratón selecciona la opción y vaciar el campo quita la selección', async () => {
    const onChange = vi.fn()
    render(<Controlado onChange={onChange} />)
    const user = userEvent.setup()
    const campo = screen.getByRole('combobox')

    await user.click(campo)
    await user.click(screen.getByRole('option', { name: /Liderazgo/ }))
    expect(onChange).toHaveBeenLastCalledWith('3')
    expect(campo).toHaveValue('Liderazgo')

    await user.clear(campo)
    expect(onChange).toHaveBeenLastCalledWith(null)
  })

  it('muestra el error enlazado al campo', () => {
    render(<Controlado error="Selecciona una competencia." />)

    const campo = screen.getByRole('combobox')
    expect(campo).toHaveAttribute('aria-invalid', 'true')
    expect(campo).toHaveAccessibleDescription('Selecciona una competencia.')
  })
})
