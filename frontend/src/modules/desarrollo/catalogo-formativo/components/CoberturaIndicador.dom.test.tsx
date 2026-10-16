// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { cobertura } from '../../../../test/catalogoFormativoFixtures'
import { CoberturaIndicador } from './CoberturaIndicador'

describe('CoberturaIndicador', () => {
  it('muestra el porcentaje, la barra accesible y el resumen', () => {
    render(<CoberturaIndicador cobertura={cobertura()} onVerSinCobertura={vi.fn()} />)

    expect(screen.getByText('40%')).toBeInTheDocument()
    const barra = screen.getByRole('progressbar', { name: 'Cobertura formativa' })
    expect(barra).toHaveAttribute('aria-valuenow', '40')
    expect(barra).toHaveAttribute('aria-valuemin', '0')
    expect(barra).toHaveAttribute('aria-valuemax', '100')
    expect(screen.getByText('4 de 10 competencias activas')).toBeInTheDocument()
  })

  it('muestra 0 % sin error cuando no hay competencias activas', () => {
    render(<CoberturaIndicador cobertura={cobertura({ activas_totales: 0, activas_cubiertas: 0, porcentaje: 0 })} onVerSinCobertura={vi.fn()} />)

    expect(screen.getByText('0%')).toBeInTheDocument()
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '0')
  })

  it('muestra 100 %', () => {
    render(<CoberturaIndicador cobertura={cobertura({ activas_cubiertas: 10, porcentaje: 100 })} onVerSinCobertura={vi.fn()} />)

    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '100')
  })

  it('el enlace "Ver sin cobertura" pide aplicar el filtro', async () => {
    const onVer = vi.fn()
    render(<CoberturaIndicador cobertura={cobertura()} onVerSinCobertura={onVer} />)

    await userEvent.setup().click(screen.getByRole('button', { name: 'Ver sin cobertura' }))

    expect(onVer).toHaveBeenCalledOnce()
  })

  it('muestra un aviso si no se pudo calcular', () => {
    render(<CoberturaIndicador cobertura={undefined} error onVerSinCobertura={vi.fn()} />)

    expect(screen.getByRole('alert')).toHaveTextContent('No se pudo calcular la cobertura')
  })
})
