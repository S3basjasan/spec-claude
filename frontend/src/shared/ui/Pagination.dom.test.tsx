// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { Pagination } from './Pagination'

describe('Pagination', () => {
  it('muestra el resumen y la página actual', () => {
    render(<Pagination page={2} lastPage={3} total={25} from={11} to={20} onPageChange={vi.fn()} />)

    expect(screen.getByText('Mostrando 11–20 de 25')).toBeInTheDocument()
    expect(screen.getByText('Página 2 de 3')).toBeInTheDocument()
  })

  it('navega a la página anterior y a la siguiente', async () => {
    const onPageChange = vi.fn()
    render(<Pagination page={2} lastPage={3} total={25} from={11} to={20} onPageChange={onPageChange} />)
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: 'Anterior' }))
    await user.click(screen.getByRole('button', { name: 'Siguiente' }))

    expect(onPageChange).toHaveBeenNthCalledWith(1, 1)
    expect(onPageChange).toHaveBeenNthCalledWith(2, 3)
  })

  it('deshabilita Anterior en la primera página y Siguiente en la última', () => {
    const { rerender } = render(<Pagination page={1} lastPage={2} total={12} from={1} to={10} onPageChange={vi.fn()} />)
    expect(screen.getByRole('button', { name: 'Anterior' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Siguiente' })).toBeEnabled()

    rerender(<Pagination page={2} lastPage={2} total={12} from={11} to={12} onPageChange={vi.fn()} />)
    expect(screen.getByRole('button', { name: 'Siguiente' })).toBeDisabled()
  })

  it('indica "Sin resultados" cuando no hay elementos', () => {
    render(<Pagination page={1} lastPage={1} total={0} from={null} to={null} onPageChange={vi.fn()} />)

    expect(screen.getByText('Sin resultados')).toBeInTheDocument()
  })
})
