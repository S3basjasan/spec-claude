// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { pdi, pdiAcordado } from '../../../../test/pdiFixtures'
import { PanelDeAcuerdo } from './PanelDeAcuerdo'

describe('PanelDeAcuerdo', () => {
  it('sin acuerdos muestra «Pendiente» en ambos y la guía del Borrador', () => {
    render(<PanelDeAcuerdo pdi={pdi()} />)

    expect(screen.getAllByText('Pendiente')).toHaveLength(2)
    expect(screen.getByText(/pasa a Acordado cuando el responsable confirma/)).toBeInTheDocument()
  })

  it('con acuerdo y aceptación muestra el usuario y no la guía', () => {
    render(<PanelDeAcuerdo pdi={pdiAcordado()} />)

    expect(screen.getByText(/Marta Ríos/)).toBeInTheDocument()
    expect(screen.queryByText('Pendiente')).not.toBeInTheDocument()
    expect(screen.queryByText(/pasa a Acordado cuando/)).not.toBeInTheDocument()
  })

  it('muestra el comentario del colaborador cuando devolvió el plan', () => {
    render(<PanelDeAcuerdo pdi={pdi({ comentario_colaborador: 'Necesito más tiempo.' })} />)

    expect(screen.getByText('Comentario del colaborador')).toBeInTheDocument()
    expect(screen.getByText('Necesito más tiempo.')).toBeInTheDocument()
  })
})
