// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import { Route } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { renderRoutes } from '../../../../test/renderRoutes'
import type { ItemQueSigue } from '../api'
import { QueSigue } from './QueSigue'

const item = (cambios: Partial<ItemQueSigue>): ItemQueSigue => ({
  tipo: 'accion',
  fecha: '2026-09-30',
  descripcion: 'Curso',
  plan_id: 1,
  plan_codigo: 'PDI-0001',
  tipo_plan: 'Mejora',
  vencido: false,
  actividad_id: null,
  ...cambios,
})

const renderQueSigue = (items: ItemQueSigue[]) => renderRoutes(<Route path="/" element={<QueSigue items={items} />} />, '/')

describe('QueSigue', () => {
  it('sin elementos muestra el mensaje vacío', () => {
    renderQueSigue([])

    expect(screen.getByText('No tienes acciones pendientes.')).toBeInTheDocument()
  })

  it('destaca lo vencido y enlaza solo las acciones con actividad', () => {
    renderQueSigue([item({ tipo: 'hito', descripcion: 'Revisión', vencido: true }), item({ descripcion: 'Taller', actividad_id: 7 })])

    const filas = screen.getAllByRole('listitem')
    expect(within(filas[0]).getByText('Vencido')).toBeInTheDocument()
    expect(within(filas[0]).queryByRole('link')).not.toBeInTheDocument()
    expect(within(filas[1]).getByRole('link', { name: 'Ver actividad' })).toHaveAttribute('href', '/actividades')
  })
})
