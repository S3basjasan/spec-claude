// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { actividadColaborador } from '../../../../test/actividadesFixtures'
import { TarjetaActividad } from './TarjetaActividad'

const hoy = new Date(2026, 2, 5) // 5 de marzo de 2026

describe('TarjetaActividad — aviso de proximidad', () => {
  it.each([
    ['2026-03-05', 'Es hoy'],
    ['2026-03-06', 'Empieza mañana'],
    ['2026-03-12', 'Empieza en 7 días'],
  ])('una actividad programada que empieza el %s dice «%s»', (inicio, texto) => {
    render(<TarjetaActividad hoy={hoy} actividad={actividadColaborador({ estado: 'Programada', fecha_inicio: inicio, fecha_fin: '2026-03-30' })} />)

    expect(screen.getByText(texto)).toBeInTheDocument()
  })

  it('una actividad en curso dice que está en curso', () => {
    render(<TarjetaActividad hoy={hoy} actividad={actividadColaborador({ estado: 'EnCurso' })} />)

    expect(screen.getByText('Está en curso')).toBeInTheDocument()
  })

  it('una actividad finalizada o cancelada no muestra aviso', () => {
    render(<TarjetaActividad hoy={hoy} actividad={actividadColaborador({ estado: 'Cancelada' })} />)

    expect(screen.queryByText(/Empieza|Es hoy|Está en curso/)).not.toBeInTheDocument()
  })

  it('el texto libre del lugar no es un enlace', () => {
    render(<TarjetaActividad hoy={hoy} actividad={actividadColaborador({ lugar_enlace: 'Sala 3' })} />)

    expect(screen.getByText('Sala 3')).toBeInTheDocument()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })
})
