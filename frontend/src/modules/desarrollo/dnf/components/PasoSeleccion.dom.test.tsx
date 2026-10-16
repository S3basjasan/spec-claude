// @vitest-environment jsdom
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { opciones } from '../../../../test/dnfFixtures'
import { PasoSeleccion } from './PasoSeleccion'

function renderPaso(props: Partial<Parameters<typeof PasoSeleccion>[0]> = {}) {
  const onSubmit = vi.fn()
  render(<PasoSeleccion opciones={opciones()} enviando={false} errores={{}} onSubmit={onSubmit} {...props} />)
  return { onSubmit }
}

describe('PasoSeleccion', () => {
  it('exige elegir un colaborador antes de enviar', async () => {
    const { onSubmit } = renderPaso()

    await userEvent.click(screen.getByRole('button', { name: 'Detectar necesidades' }))

    expect(await screen.findByText('Elige un colaborador.')).toBeInTheDocument()
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('envía el colaborador elegido con búsqueda y muestra su puesto', async () => {
    const { onSubmit } = renderPaso()

    await userEvent.type(screen.getByRole('combobox', { name: 'Colaborador' }), 'Dieg')
    await userEvent.click(await screen.findByRole('option', { name: /Diego Paredes/ }))
    expect(screen.getByText('Analista de datos', { selector: 'dd' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Detectar necesidades' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    expect(onSubmit.mock.calls[0][0]).toEqual({ alcance: 'Individual', colaborador_id: 10, departamento_id: null })
  })

  it('al elegir departamento exige un departamento y muestra cuántos colaboradores evalúa', async () => {
    const { onSubmit } = renderPaso()

    await userEvent.click(screen.getByRole('radio', { name: /Por departamento/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Detectar necesidades' }))
    expect(await screen.findByText('Elige un departamento.')).toBeInTheDocument()
    expect(onSubmit).not.toHaveBeenCalled()

    await userEvent.selectOptions(screen.getByLabelText('Departamento'), 'Tecnología')
    expect(screen.getByText('2 colaboradores evaluables')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Detectar necesidades' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    expect(onSubmit.mock.calls[0][0]).toMatchObject({ alcance: 'Departamento', departamento_id: 5 })
  })

  it('bloquea el envío mientras la mutación está pendiente', () => {
    renderPaso({ enviando: true })

    expect(screen.getByRole('button', { name: 'Detectando…' })).toBeDisabled()
  })

  it('muestra los errores del servidor por campo y el general', () => {
    renderPaso({ errores: { colaborador_id: 'El colaborador no tiene un puesto asignado.', general: 'Algo falló.' } })

    expect(screen.getByText('El colaborador no tiene un puesto asignado.')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('Algo falló.')
  })
})
