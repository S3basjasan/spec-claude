// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import { ListaDeCampos } from './ListaDeCampos'

function Controlada({ inicial = [''], itemErrors, error }: { inicial?: string[]; itemErrors?: (string | undefined)[]; error?: string }) {
  const [values, setValues] = useState(inicial)
  return (
    <ListaDeCampos
      id="grado-A"
      legend="Grado A"
      values={values}
      onChange={setValues}
      itemLabel={(n) => `Comportamiento ${n} del grado A`}
      addLabel="Agregar comportamiento"
      removeLabel={(n) => `Quitar comportamiento ${n} del grado A`}
      itemErrors={itemErrors}
      error={error}
    />
  )
}

describe('ListaDeCampos', () => {
  it('agrupa los campos en un fieldset con leyenda y etiqueta cada campo', () => {
    render(<Controlada inicial={['a', 'b']} />)

    expect(screen.getByRole('group', { name: 'Grado A' })).toBeInTheDocument()
    expect(screen.getByLabelText('Comportamiento 1 del grado A')).toHaveValue('a')
    expect(screen.getByLabelText('Comportamiento 2 del grado A')).toHaveValue('b')
  })

  it('agrega campos hasta 4 y deshabilita Agregar en el máximo', async () => {
    render(<Controlada />)
    const user = userEvent.setup()
    const agregar = screen.getByRole('button', { name: 'Agregar comportamiento' })

    await user.click(agregar)
    await user.click(agregar)
    await user.click(agregar)

    expect(screen.getAllByRole('textbox')).toHaveLength(4)
    expect(agregar).toBeDisabled()
  })

  it('pasa el foco al campo recién agregado', async () => {
    render(<Controlada />)

    await userEvent.setup().click(screen.getByRole('button', { name: 'Agregar comportamiento' }))

    expect(screen.getByLabelText('Comportamiento 2 del grado A')).toHaveFocus()
  })

  it('quita campos hasta dejar 1 y deshabilita Quitar en el mínimo', async () => {
    render(<Controlada inicial={['a', 'b']} />)
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: 'Quitar comportamiento 1 del grado A' }))

    expect(screen.getAllByRole('textbox')).toHaveLength(1)
    expect(screen.getByLabelText('Comportamiento 1 del grado A')).toHaveValue('b')
    expect(screen.getByRole('button', { name: 'Quitar comportamiento 1 del grado A' })).toBeDisabled()
  })

  it('muestra el contador n/300 por campo y lo actualiza al escribir', async () => {
    render(<Controlada inicial={['abc']} />)
    expect(screen.getByText('3/300')).toBeInTheDocument()

    await userEvent.setup().type(screen.getByLabelText('Comportamiento 1 del grado A'), 'de')

    expect(screen.getByText('5/300')).toBeInTheDocument()
  })

  it('muestra el error de cada campo enlazado y el error del grupo', () => {
    render(<Controlada inicial={['a', 'a']} itemErrors={[undefined, 'Este comportamiento ya está en el grado.']} error="Grupo inválido" />)

    const segundo = screen.getByLabelText('Comportamiento 2 del grado A')
    expect(segundo).toHaveAttribute('aria-invalid', 'true')
    expect(segundo).toHaveAccessibleDescription(/Este comportamiento ya está en el grado\./)
    expect(screen.getByLabelText('Comportamiento 1 del grado A')).not.toHaveAttribute('aria-invalid')
    expect(screen.getByText('Grupo inválido')).toBeInTheDocument()
  })
})
