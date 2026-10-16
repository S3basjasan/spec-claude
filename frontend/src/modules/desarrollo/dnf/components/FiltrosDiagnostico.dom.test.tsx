// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { SIN_FILTROS } from '../orden'
import { FiltrosDiagnostico } from './FiltrosDiagnostico'

describe('FiltrosDiagnostico', () => {
  it('cambia la prioridad, el estado y «solo con brecha» sin perder los demás filtros', async () => {
    const onChange = vi.fn()
    render(<FiltrosDiagnostico filtros={{ prioridad: 'Alta', estado: '', soloConBrecha: false }} onChange={onChange} />)

    await userEvent.selectOptions(screen.getByLabelText('Filtrar por estado'), 'Cumple')
    expect(onChange).toHaveBeenLastCalledWith({ prioridad: 'Alta', estado: 'Cumple', soloConBrecha: false })

    await userEvent.selectOptions(screen.getByLabelText('Filtrar por prioridad'), 'Critica')
    expect(onChange).toHaveBeenLastCalledWith({ prioridad: 'Critica', estado: '', soloConBrecha: false })

    await userEvent.click(screen.getByRole('checkbox', { name: 'Mostrar solo competencias con brecha' }))
    expect(onChange).toHaveBeenLastCalledWith({ prioridad: 'Alta', estado: '', soloConBrecha: true })
  })

  it('ofrece todas las prioridades y estados', () => {
    render(<FiltrosDiagnostico filtros={SIN_FILTROS} onChange={vi.fn()} />)

    expect(screen.getByRole('option', { name: 'Todas las prioridades' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Crítica' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Sin evaluación' })).toBeInTheDocument()
  })
})
