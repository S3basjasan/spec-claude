// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { accion } from '../../../../test/pdiFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import type { Accion } from '../api'
import { FilaDeAccion } from './FilaDeAccion'

const vinculada = (): Accion =>
  accion({ estado: 'EnCurso', estado_derivado: true, vinculo: { plan_accion_id: 55, plan_codigo: 'PF-0001', accion_nombre: 'Taller', actividad_id: 9, requiere_revision: true } as Accion['vinculo'] })

function renderFila(a: Accion, props: Partial<Parameters<typeof FilaDeAccion>[0]> = {}) {
  return renderRoutes(
    <Route path="/" element={<ul><FilaDeAccion accion={a} puedeRegistrarAvance={false} puedeVincular={false} {...props} /></ul>} />,
    '/',
  )
}

describe('FilaDeAccion', () => {
  it('sin permiso de avance el estado es solo lectura', () => {
    renderFila(accion())

    expect(screen.queryByLabelText('Estado de la acción')).not.toBeInTheDocument()
    expect(screen.getByText('Pendiente')).toBeInTheDocument()
  })

  it('con permiso de avance el selector registra el nuevo estado', async () => {
    const usuario = userEvent.setup()
    const onAvance = vi.fn()
    renderFila(accion(), { puedeRegistrarAvance: true, onAvance })

    await usuario.selectOptions(screen.getByLabelText('Estado de la acción'), 'Cumplida')

    expect(onAvance).toHaveBeenCalledWith('Cumplida')
  })

  it('una acción vinculada muestra el estado derivado, la revisión y el enlace a la actividad, sin selector', () => {
    renderFila(vinculada(), { puedeRegistrarAvance: true })

    expect(screen.queryByLabelText('Estado de la acción')).not.toBeInTheDocument()
    expect(screen.getByText('(Estado derivado del Plan de Formación)')).toBeInTheDocument()
    expect(screen.getByText('Requiere revisión')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver actividad' })).toHaveAttribute('href', '/actividades')
  })

  it('«Vincular» solo aparece en Curso, Mentoring y Codesarrollo; «Desvincular» en las vinculadas', async () => {
    const usuario = userEvent.setup()
    const onVincular = vi.fn()
    const onDesvincular = vi.fn()
    const { unmount } = renderFila(accion({ tipo: 'Coaching' }), { puedeVincular: true })
    expect(screen.queryByRole('button', { name: 'Vincular a Plan de Formación' })).not.toBeInTheDocument()
    unmount()

    const sin = renderFila(accion({ tipo: 'Mentoring' }), { puedeVincular: true, onVincular })
    await usuario.click(screen.getByRole('button', { name: 'Vincular a Plan de Formación' }))
    expect(onVincular).toHaveBeenCalled()
    sin.unmount()

    renderFila(vinculada(), { puedeVincular: true, onDesvincular })
    await usuario.click(screen.getByRole('button', { name: 'Desvincular' }))
    expect(onDesvincular).toHaveBeenCalled()
  })
})
