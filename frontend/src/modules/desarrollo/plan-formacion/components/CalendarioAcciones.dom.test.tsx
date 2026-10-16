// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { render } from '@testing-library/react'
import { filaAccion } from '../../../../test/planFixtures'
import { CalendarioAcciones } from './CalendarioAcciones'

function renderCalendario(acciones = [filaAccion()], extra: Partial<Parameters<typeof CalendarioAcciones>[0]> = {}) {
  const onMesChange = vi.fn()
  const onSeleccionar = vi.fn()
  render(<CalendarioAcciones anio={2026} mes={9} acciones={acciones} hayMas={false} onMesChange={onMesChange} onSeleccionar={onSeleccionar} {...extra} />)
  return { onMesChange, onSeleccionar }
}

describe('CalendarioAcciones', () => {
  it('pinta la acción en cada día que abarca, con su nombre y tipo como texto', () => {
    renderCalendario()

    const tabla = screen.getByRole('table', { name: 'Calendario de Octubre 2026' })
    expect(within(tabla).getAllByRole('button', { name: 'Análisis con SQL, Virtual' })).toHaveLength(19) // del 5 al 23 de octubre
    expect(screen.getByRole('heading', { name: 'Octubre 2026' })).toBeInTheDocument()
    expect(within(tabla).getAllByRole('columnheader').map((c) => c.textContent)).toEqual(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'])
  })

  it('al hacer clic en una acción pide abrir su detalle', async () => {
    const { onSeleccionar } = renderCalendario()

    await userEvent.click(within(screen.getByRole('table')).getAllByRole('button', { name: /Análisis con SQL/ })[0])

    expect(onSeleccionar).toHaveBeenCalledWith(expect.objectContaining({ id: 7 }))
  })

  it('limita a 3 acciones por día y muestra «+N más»', () => {
    const acciones = [1, 2, 3, 4, 5].map((id) => filaAccion({ id, nombre: `Acción ${id}`, fecha_inicio: '2026-10-07', fecha_fin: '2026-10-07' }))
    renderCalendario(acciones)

    const tabla = screen.getByRole('table')
    expect(within(tabla).getAllByRole('button')).toHaveLength(3)
    expect(within(tabla).getByText('+2 más')).toBeInTheDocument()
  })

  it('navega al mes anterior, al siguiente y a hoy', async () => {
    const { onMesChange } = renderCalendario([])

    await userEvent.click(screen.getByRole('button', { name: 'Mes anterior' }))
    expect(onMesChange).toHaveBeenLastCalledWith(2026, 8)
    await userEvent.click(screen.getByRole('button', { name: 'Mes siguiente' }))
    expect(onMesChange).toHaveBeenLastCalledWith(2026, 10)
    await userEvent.click(screen.getByRole('button', { name: 'Hoy' }))
    const hoy = new Date()
    expect(onMesChange).toHaveBeenLastCalledWith(hoy.getFullYear(), hoy.getMonth())
  })

  it('cruza el cambio de año al navegar desde enero y diciembre', async () => {
    const { onMesChange } = renderCalendario([], { anio: 2026, mes: 0 })
    await userEvent.click(screen.getByRole('button', { name: 'Mes anterior' }))
    expect(onMesChange).toHaveBeenLastCalledWith(2025, 11)
  })

  it('avisa si hay más acciones de las que se pueden mostrar y de las que no tienen fechas', () => {
    renderCalendario([filaAccion({ id: 8, fecha_inicio: null, fecha_fin: null })], { hayMas: true })

    expect(screen.getByText(/Hay más acciones de las que se pueden mostrar/)).toBeInTheDocument()
    expect(screen.getByText('Las acciones sin fechas no aparecen en el calendario.')).toBeInTheDocument()
  })

  it('tachado y con texto para acciones canceladas, sin depender solo del color', () => {
    renderCalendario([filaAccion({ cancelada: true, estado_accion: 'Cancelada', fecha_inicio: '2026-10-07', fecha_fin: '2026-10-07' })])

    const chip = within(screen.getByRole('table')).getByRole('button', { name: 'Análisis con SQL, Virtual' })
    expect(chip).toHaveClass('line-through')
  })
})
