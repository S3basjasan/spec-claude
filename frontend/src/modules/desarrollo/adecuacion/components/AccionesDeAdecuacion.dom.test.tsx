// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { AccionesDeAdecuacion } from './AccionesDeAdecuacion'

describe('AccionesDeAdecuacion', () => {
  it('con brechas deja ambos botones deshabilitados y dice que dependen del Plan de Desarrollo Individual', () => {
    render(<AccionesDeAdecuacion conBrechas />)

    expect(screen.getByRole('button', { name: 'Crear plan de desarrollo' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Incluir en Programa de Talentos' })).toBeDisabled()
    expect(screen.getByText('Disponible cuando exista el Plan de Desarrollo Individual.')).toBeInTheDocument()
  })

  it('sin brechas el motivo visible es «No hay brechas.»', () => {
    render(<AccionesDeAdecuacion conBrechas={false} />)

    expect(screen.getByText('No hay brechas.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Crear plan de desarrollo' })).toBeDisabled()
  })

  it('el motivo se enlaza con los botones para los lectores de pantalla (no solo con title)', () => {
    render(<AccionesDeAdecuacion conBrechas />)

    const motivo = screen.getByText('Disponible cuando exista el Plan de Desarrollo Individual.')
    expect(screen.getByRole('button', { name: 'Crear plan de desarrollo' })).toHaveAccessibleDescription(motivo.textContent ?? '')
  })

  it('usa el motivo que trae el servidor cuando lo hay', () => {
    render(<AccionesDeAdecuacion conBrechas motivo="Motivo del servidor." />)

    expect(screen.getByText('Motivo del servidor.')).toBeInTheDocument()
  })
})
