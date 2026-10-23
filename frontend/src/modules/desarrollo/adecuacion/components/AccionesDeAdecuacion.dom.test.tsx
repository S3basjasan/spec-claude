// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { AccionesDeAdecuacion } from './AccionesDeAdecuacion'

const habilitado = { habilitado: true, motivo: '' }
const plan = (cambios: Partial<{ crear: { habilitado: boolean; motivo: string }; incluir: { habilitado: boolean; motivo: string } }> = {}) => ({
  colaboradorId: 10,
  puestoObjetivoId: 2,
  crear: habilitado,
  incluir: habilitado,
  ...cambios,
})
const renderizar = (props: Parameters<typeof AccionesDeAdecuacion>[0]) =>
  render(
    <MemoryRouter>
      <AccionesDeAdecuacion {...props} />
    </MemoryRouter>,
  )

describe('AccionesDeAdecuacion', () => {
  it('en la vista previa deja ambos botones deshabilitados hasta registrar la evaluación', () => {
    renderizar({ conBrechas: true })

    expect(screen.getByRole('button', { name: 'Crear plan de desarrollo' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Incluir en Programa de Talentos' })).toBeDisabled()
    expect(screen.getByText('Disponible cuando registres la evaluación.')).toBeInTheDocument()
  })

  it('sin brechas el motivo visible es «No hay brechas.»', () => {
    renderizar({ conBrechas: false })

    expect(screen.getByText('No hay brechas.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Crear plan de desarrollo' })).toBeDisabled()
  })

  it('el motivo se enlaza con los botones para los lectores de pantalla (no solo con title)', () => {
    renderizar({ conBrechas: true })

    const motivo = screen.getByText('Disponible cuando registres la evaluación.')
    expect(screen.getByRole('button', { name: 'Crear plan de desarrollo' })).toHaveAccessibleDescription(motivo.textContent ?? '')
  })

  it('con la adecuación guardada y brechas los botones llevan al asistente del PDI con colaborador, tipo y puesto objetivo', () => {
    renderizar({ conBrechas: true, plan: plan() })

    expect(screen.getByRole('link', { name: 'Crear plan de desarrollo' })).toHaveAttribute('href', '/pdi/nuevo?colaborador=10&tipo=Carrera&puesto_objetivo=2')
    expect(screen.getByRole('link', { name: 'Incluir en Programa de Talentos' })).toHaveAttribute('href', '/pdi/nuevo?colaborador=10&tipo=Talento&puesto_objetivo=2')
  })

  it('un plan abierto del tipo deshabilita solo ese botón y dice el motivo', () => {
    renderizar({ conBrechas: true, plan: plan({ crear: { habilitado: false, motivo: 'La persona ya tiene un plan de Carrera abierto.' } }) })

    expect(screen.getByRole('button', { name: 'Crear plan de desarrollo' })).toBeDisabled()
    expect(screen.getByRole('link', { name: 'Incluir en Programa de Talentos' })).toBeInTheDocument()
    expect(screen.getByText('La persona ya tiene un plan de Carrera abierto.')).toBeInTheDocument()
  })
})
