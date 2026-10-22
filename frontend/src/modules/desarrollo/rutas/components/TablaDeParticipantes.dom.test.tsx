// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { inscripcion } from '../../../../test/rutasFixtures'
import { TablaDeParticipantes } from './TablaDeParticipantes'

function renderTabla(sobrescribir: Partial<React.ComponentProps<typeof TablaDeParticipantes>> = {}) {
  const props = {
    inscripciones: [
      inscripcion({ id: 1 }),
      inscripcion({ id: 2, colaborador: { id: 11, nombre: 'Luis Núñez', departamento: null }, nivel_actual: null, nivel_objetivo: null }),
      inscripcion({ id: 3, colaborador: { id: 12, nombre: 'María Pérez', departamento: null }, estado: 'Retirada', motivo: 'Cambió de área' }),
      inscripcion({ id: 4, colaborador: { id: 13, nombre: 'José Mora', departamento: null }, estado: 'Finalizada' }),
    ],
    puedeInscribir: true,
    ocupado: false,
    onCambiar: vi.fn(),
    onAlcanzar: vi.fn(),
    onRetirar: vi.fn(),
    ...sobrescribir,
  }
  render(<TablaDeParticipantes {...props} />)
  return props
}

describe('TablaDeParticipantes', () => {
  it('muestra colaborador, departamento, nivel actual, nivel objetivo y estado', () => {
    renderTabla()

    const fila = screen.getByText('Ana Núñez').closest('tr') as HTMLElement
    expect(fila).toHaveTextContent('Tecnología')
    expect(fila).toHaveTextContent('Nivel 1')
    expect(fila).toHaveTextContent('Nivel 3')
    expect(within(fila).getByText('Activa')).toBeInTheDocument()
  })

  it('los niveles sin definir se muestran como «Sin definir»', () => {
    renderTabla()

    const fila = screen.getByText('Luis Núñez').closest('tr') as HTMLElement
    expect(within(fila).getAllByText('Sin definir')).toHaveLength(2)
  })

  it('las acciones solo aparecen en las inscripciones Activas', () => {
    renderTabla()

    expect(screen.getAllByRole('button', { name: /Retirar a/ })).toHaveLength(2)
    expect(screen.queryByRole('button', { name: /Retirar a María Pérez/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Cambiar los niveles de José Mora/ })).not.toBeInTheDocument()
    expect(screen.getByText('Motivo: Cambió de área')).toBeInTheDocument()
  })

  it('cambiar niveles, registrar nivel alcanzado y retirar avisan con la inscripción', async () => {
    const props = renderTabla()

    await userEvent.click(screen.getByRole('button', { name: 'Cambiar los niveles de Ana Núñez' }))
    await userEvent.click(screen.getByRole('button', { name: 'Registrar el nivel alcanzado de Ana Núñez' }))
    await userEvent.click(screen.getByRole('button', { name: 'Retirar a Ana Núñez de la ruta' }))
    expect(props.onCambiar).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }))
    expect(props.onAlcanzar).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }))
    expect(props.onRetirar).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }))
  })

  it('sin nivel objetivo no se puede registrar el nivel alcanzado', () => {
    renderTabla()

    expect(screen.getByRole('button', { name: 'Registrar el nivel alcanzado de Luis Núñez' })).toBeDisabled()
  })

  it('sin permiso de inscribir no muestra la columna de acciones', () => {
    renderTabla({ puedeInscribir: false })

    expect(screen.queryByRole('columnheader', { name: 'Acciones' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Retirar/ })).not.toBeInTheDocument()
  })
})
