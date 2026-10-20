// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import type { Participante } from '../api'
import { borradorDesde, cambiarCalificacion, cambiarObservaciones, elegirAsistencia } from '../registros'
import { TablaAsistencia } from './TablaAsistencia'

const participantes: Participante[] = [
  { colaborador_id: 1, nombre: 'Ana Pérez', asistencia: 'Pendiente', calificacion: null, observaciones: null },
  { colaborador_id: 2, nombre: 'Luis Mora', asistencia: 'Presente', calificacion: 4, observaciones: 'Buen ritmo' },
]

function Envoltura({ deshabilitada = false }: { deshabilitada?: boolean }) {
  const [filas, setFilas] = useState(borradorDesde(participantes))
  const sobre = (id: number, f: (x: (typeof filas)[number]) => (typeof filas)[number]) => setFilas((a) => a.map((x) => (x.colaborador_id === id ? f(x) : x)))
  return (
    <TablaAsistencia
      filas={filas}
      deshabilitada={deshabilitada}
      onElegir={(id, opcion) => sobre(id, (x) => elegirAsistencia(x, opcion))}
      onCalificacion={(id, valor) => sobre(id, (x) => cambiarCalificacion(x, valor))}
      onObservaciones={(id, texto) => sobre(id, (x) => cambiarObservaciones(x, texto))}
    />
  )
}

describe('TablaAsistencia', () => {
  it('muestra cada inscrito con su control de tres opciones, calificación y observaciones', () => {
    render(<Envoltura />)

    const grupo = screen.getByRole('group', { name: 'Asistencia de Ana Pérez' })
    expect(within(grupo).getAllByRole('button').map((b) => b.textContent)).toEqual(['Presente', 'Ausente', 'Justificado'])
    expect(screen.getByRole('spinbutton', { name: 'Calificación de Luis Mora' })).toHaveValue(4)
    expect(screen.getByRole('textbox', { name: 'Observaciones de Luis Mora' })).toHaveValue('Buen ritmo')
  })

  it('la calificación y las observaciones están deshabilitadas mientras la asistencia es Pendiente', () => {
    render(<Envoltura />)

    expect(screen.getByRole('spinbutton', { name: 'Calificación de Ana Pérez' })).toBeDisabled()
    expect(screen.getByRole('textbox', { name: 'Observaciones de Ana Pérez' })).toBeDisabled()
    expect(screen.getByRole('spinbutton', { name: 'Calificación de Luis Mora' })).toBeEnabled()
  })

  it('al marcar Presente se habilita la calificación; con Ausente se deshabilita y se borra', async () => {
    render(<Envoltura />)
    const user = userEvent.setup()
    const calificacion = screen.getByRole('spinbutton', { name: 'Calificación de Ana Pérez' })

    await user.click(within(screen.getByRole('group', { name: 'Asistencia de Ana Pérez' })).getByRole('button', { name: 'Presente' }))
    expect(calificacion).toBeEnabled()
    await user.type(calificacion, '5')
    expect(calificacion).toHaveValue(5)
    expect(screen.getByRole('textbox', { name: 'Observaciones de Ana Pérez' })).toBeEnabled()

    await user.click(within(screen.getByRole('group', { name: 'Asistencia de Ana Pérez' })).getByRole('button', { name: 'Ausente' }))
    expect(calificacion).toBeDisabled()
    expect(calificacion).toHaveValue(null)
  })

  it('pulsar de nuevo la opción seleccionada vuelve a Pendiente y limpia calificación y observaciones', async () => {
    render(<Envoltura />)
    const user = userEvent.setup()

    await user.click(within(screen.getByRole('group', { name: 'Asistencia de Luis Mora' })).getByRole('button', { name: 'Presente' }))

    expect(within(screen.getByRole('group', { name: 'Asistencia de Luis Mora' })).getByRole('button', { name: 'Presente' })).toHaveAttribute('aria-pressed', 'false')
    expect(screen.getByRole('spinbutton', { name: 'Calificación de Luis Mora' })).toBeDisabled()
    expect(screen.getByRole('textbox', { name: 'Observaciones de Luis Mora' })).toHaveValue('')
  })

  it('marca la opción elegida con aria-pressed y no solo con color', () => {
    render(<Envoltura />)

    expect(within(screen.getByRole('group', { name: 'Asistencia de Luis Mora' })).getByRole('button', { name: 'Presente' })).toHaveAttribute('aria-pressed', 'true')
    expect(within(screen.getByRole('group', { name: 'Asistencia de Luis Mora' })).getByRole('button', { name: 'Ausente' })).toHaveAttribute('aria-pressed', 'false')
  })

  it('una calificación fuera de rango muestra el error en el campo', async () => {
    render(<Envoltura />)
    const user = userEvent.setup()
    const calificacion = screen.getByRole('spinbutton', { name: 'Calificación de Luis Mora' })

    await user.clear(calificacion)
    await user.type(calificacion, '7')

    expect(calificacion).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByRole('alert')).toHaveTextContent('La calificación debe ser un entero de 1 a 5.')
  })

  it('con la vista deshabilitada no se puede editar nada', () => {
    render(<Envoltura deshabilitada />)

    for (const boton of screen.getAllByRole('button')) expect(boton).toBeDisabled()
    expect(screen.getByRole('spinbutton', { name: 'Calificación de Luis Mora' })).toBeDisabled()
    expect(screen.getByRole('textbox', { name: 'Observaciones de Luis Mora' })).toBeDisabled()
  })
})
