// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { Asistencia, EstadoActividad, TipoAccion } from '../api'
import { BadgeAsistencia, BadgeAsistenciaPendiente } from './BadgeAsistencia'
import { BadgeEstadoActividad } from './BadgeEstadoActividad'
import { EtiquetaTipo } from './EtiquetaTipo'

describe('badges de actividades', () => {
  it.each([
    ['Programada', 'Programada'],
    ['EnCurso', 'En curso'],
    ['Finalizada', 'Finalizada'],
    ['Cancelada', 'Cancelada'],
  ] as [EstadoActividad, string][])('el estado %s se muestra con texto', (estado, texto) => {
    render(<BadgeEstadoActividad estado={estado} />)

    expect(screen.getByText(texto)).toBeInTheDocument()
  })

  it.each(['Pendiente', 'Presente', 'Ausente', 'Justificado'] as Asistencia[])('la asistencia %s se muestra con texto', (asistencia) => {
    render(<BadgeAsistencia asistencia={asistencia} />)

    expect(screen.getByText(asistencia)).toBeInTheDocument()
  })

  it('el badge «Asistencia pendiente» tiene texto', () => {
    render(<BadgeAsistenciaPendiente />)

    expect(screen.getByText('Asistencia pendiente')).toBeInTheDocument()
  })

  it.each([
    ['ELearning', 'E-learning'],
    ['OJT', 'OJT'],
    ['Codesarrollo', 'Codesarrollo'],
  ] as [TipoAccion, string][])('el tipo %s se muestra con su etiqueta', (tipo, texto) => {
    render(<EtiquetaTipo tipo={tipo} />)

    expect(screen.getByText(texto)).toBeInTheDocument()
  })

  it('un tipo desconocido muestra «—»', () => {
    render(<EtiquetaTipo tipo={null} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
