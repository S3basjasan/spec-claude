// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { accion, plan } from '../../../../test/planFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import type { Plan } from '../api'
import { DetallePlan } from './DetallePlan'

function renderDetalle(datos: Plan) {
  return renderRoutes(<Route path="/" element={<DetallePlan plan={datos} />} />, '/')
}

describe('DetallePlan', () => {
  it('muestra datos generales, totales y las acciones con su estado, tipo y participantes', () => {
    renderDetalle(plan({ estado: 'Aprobado', fecha_aprobacion: '2026-10-16T10:00:00-05:00' }))

    expect(screen.getByText('PLAN-0001')).toBeInTheDocument()
    expect(screen.getByText('Aprobado')).toBeInTheDocument()
    expect(screen.getByText('Marta Ríos', { selector: 'dd' })).toBeInTheDocument()
    expect(screen.getByText('Creado manualmente')).toBeInTheDocument()
    const tarjeta = screen.getByRole('article', { name: 'Análisis con SQL' })
    expect(within(tarjeta).getByText('Virtual')).toBeInTheDocument()
    expect(within(tarjeta).getByText('Programada')).toBeInTheDocument()
    expect(within(tarjeta).getByText('Ana Pérez')).toBeInTheDocument()
    expect(within(tarjeta).getByText('2/20')).toBeInTheDocument()
  })

  it('enlaza el origen con la DNF cuando el plan nació de una', () => {
    renderDetalle(plan({ origen_dnf: { id: 9, codigo: 'DNF-0009' } }))

    expect(screen.getByRole('link', { name: 'Generado desde la DNF DNF-0009' })).toHaveAttribute('href', '/dnf/9')
  })

  it('avisa de los cruces de fechas sin impedir nada', () => {
    renderDetalle(plan({ avisos_cruce: [{ colaborador_id: 10, nombre: 'Ana Pérez', accion_id: 7, con_accion_id: 9, con_plan_id: 2 }] }))

    expect(screen.getByRole('heading', { name: 'Avisos de cruce de fechas' })).toBeInTheDocument()
    expect(screen.getByText('Ana Pérez tiene acciones con fechas que se cruzan.')).toBeInTheDocument()
  })

  it('muestra la acción cancelada con su motivo y la competencia inactiva marcada', () => {
    renderDetalle(
      plan({
        acciones: [
          accion({
            cancelada: true,
            motivo_cancelacion: 'Sin cupo',
            estado_accion: 'Cancelada',
            competencias: [{ id: 2, codigo: 'COM-002', nombre: 'Liderazgo', activa: false }],
            participantes: [{ colaborador_id: 12, nombre: 'Eva Ríos', vinculo_vigente: false }],
          }),
        ],
      }),
    )

    expect(screen.getByText('Cancelada')).toBeInTheDocument()
    expect(screen.getByText('Motivo de la cancelación: Sin cupo')).toBeInTheDocument()
    expect(screen.getByText('Liderazgo (inactiva)')).toBeInTheDocument()
    expect(screen.getByText(/Sin vínculo vigente/)).toBeInTheDocument()
  })

  it('indica cuando el plan no tiene acciones', () => {
    renderDetalle(plan({ acciones: [], cantidad_acciones: 0 }))

    expect(screen.getByText('Este plan aún no tiene acciones.')).toBeInTheDocument()
  })
})
