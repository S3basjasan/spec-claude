// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { resultado } from '../../../../test/dnfFixtures'
import { ResumenPorColaborador } from './ResumenPorColaborador'

const colaboradores = [
  resultado({ colaborador_id: 10, nombre: 'Diego Paredes', cantidad_brechas: 3, prioridad_mas_alta: 'Critica' }),
  resultado({ colaborador_id: 11, nombre: 'Camila Andrade', cantidad_brechas: 0, prioridad_mas_alta: null }),
]

describe('ResumenPorColaborador', () => {
  it('muestra colaborador, puesto, cantidad de brechas y prioridad más alta', () => {
    render(<ResumenPorColaborador colaboradores={colaboradores} onElegir={vi.fn()} />)

    const filas = screen.getAllByRole('row').slice(1)
    expect(within(filas[0]).getByText('Diego Paredes')).toBeInTheDocument()
    expect(within(filas[0]).getByText('Analista de datos')).toBeInTheDocument()
    expect(within(filas[0]).getByText('3')).toBeInTheDocument()
    expect(within(filas[0]).getByText('Crítica')).toBeInTheDocument()
    expect(within(filas[1]).getByLabelText('Sin prioridad')).toBeInTheDocument()
  })

  it('al elegir un colaborador avisa con su id', async () => {
    const onElegir = vi.fn()
    render(<ResumenPorColaborador colaboradores={colaboradores} onElegir={onElegir} />)

    await userEvent.click(screen.getByRole('button', { name: 'Ver diagnóstico de Camila Andrade' }))

    expect(onElegir).toHaveBeenCalledWith(11)
  })
})
