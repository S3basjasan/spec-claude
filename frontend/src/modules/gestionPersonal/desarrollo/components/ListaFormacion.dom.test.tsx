// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { formacion } from '../../../../test/desarrolloFixtures'
import { ListaFormacion } from './ListaFormacion'

describe('ListaFormacion', () => {
  it('muestra el estado vacío y el botón para agregar solo si se puede editar', () => {
    const { rerender } = render(<ListaFormacion formaciones={[]} editable onAgregar={vi.fn()} onEditar={vi.fn()} />)
    expect(screen.getByText('Formación académica no registrada')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Agregar formación' })).toBeInTheDocument()

    rerender(<ListaFormacion formaciones={[]} editable={false} onAgregar={vi.fn()} onEditar={vi.fn()} />)
    expect(screen.queryByRole('button', { name: 'Agregar formación' })).not.toBeInTheDocument()
  })

  it('muestra «No registrado» para fechas ausentes, el estado y el botón Editar', async () => {
    const onEditar = vi.fn()
    const fila = formacion({ titulo_programa: 'Maestría', estado: 'EnCurso' })
    render(<ListaFormacion formaciones={[fila]} editable onAgregar={vi.fn()} onEditar={onEditar} />)

    expect(screen.getByText('Maestría')).toBeInTheDocument()
    expect(screen.getByText('En curso')).toBeInTheDocument()
    expect(screen.getAllByText('No registrado').length).toBeGreaterThanOrEqual(2)

    await userEvent.setup().click(screen.getByRole('button', { name: 'Editar formación Maestría' }))
    expect(onEditar).toHaveBeenCalledWith(fila)
  })

  it('nunca ofrece eliminar y sin permiso no muestra ningún control', () => {
    render(<ListaFormacion formaciones={[formacion()]} editable={false} onAgregar={vi.fn()} onEditar={vi.fn()} />)

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
    expect(screen.queryByText(/eliminar/i)).not.toBeInTheDocument()
  })
})
