// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { resultado, resultadoCumple, resultadoExperiencia } from '../../../../test/adecuacionFixtures'
import { TablaDeBrechas } from './TablaDeBrechas'

const resultados = [resultado(), resultadoCumple(), resultadoExperiencia()]

describe('TablaDeBrechas', () => {
  it('lista solo las brechas con un campo de meses por cada una', () => {
    render(<TablaDeBrechas resultados={resultados} tiempos={{}} onTiempo={vi.fn()} />)

    expect(screen.getByRole('heading', { name: 'Brechas y tiempo estimado de cierre' })).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'Tiempo estimado de cierre en meses: Comunicación' })).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'Tiempo estimado de cierre en meses: Experiencia acumulada' })).toBeInTheDocument()
    expect(screen.queryByRole('spinbutton', { name: /Análisis/ })).not.toBeInTheDocument()
    expect(screen.getByText('Faltan 2 tiempos por ingresar.')).toBeInTheDocument()
  })

  it('avisa los tiempos que faltan y cuando están completos', () => {
    const { rerender } = render(<TablaDeBrechas resultados={resultados} tiempos={{ 'Competencia:1': '6' }} onTiempo={vi.fn()} />)
    expect(screen.getByText('Falta ingresar el tiempo de 1 brecha.')).toBeInTheDocument()

    rerender(<TablaDeBrechas resultados={resultados} tiempos={{ 'Competencia:1': '6', 'Experiencia:0': '24' }} onTiempo={vi.fn()} />)
    expect(screen.getByText('Todos los tiempos de cierre están ingresados.')).toBeInTheDocument()
  })

  it('marca como inválido un tiempo fuera de 1 a 60 meses', () => {
    render(<TablaDeBrechas resultados={resultados} tiempos={{ 'Competencia:1': '99' }} onTiempo={vi.fn()} />)

    const campo = screen.getByRole('spinbutton', { name: 'Tiempo estimado de cierre en meses: Comunicación' })
    expect(campo).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText('Ingresa un número entero de meses entre 1 y 60.')).toBeInTheDocument()
  })

  it('informa lo escrito con la clave del requisito', async () => {
    const onTiempo = vi.fn()
    render(<TablaDeBrechas resultados={resultados} tiempos={{}} onTiempo={onTiempo} />)

    await userEvent.type(screen.getByRole('spinbutton', { name: /Comunicación/ }), '8')

    expect(onTiempo).toHaveBeenCalledWith('Competencia:1', '8')
  })

  it('muestra los errores que devuelve el servidor', () => {
    render(<TablaDeBrechas resultados={resultados} tiempos={{}} onTiempo={vi.fn()} erroresDelServidor={{ 'Competencia:1': 'Ingresa el tiempo estimado de cierre en meses.' }} />)

    expect(screen.getByText('Ingresa el tiempo estimado de cierre en meses.')).toBeInTheDocument()
  })

  it('sin brechas lo dice y no muestra la tabla', () => {
    render(<TablaDeBrechas resultados={[resultadoCumple()]} tiempos={{}} onTiempo={vi.fn()} />)

    expect(screen.getByText('No hay brechas: la persona cumple todos los requisitos del puesto objetivo.')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })
})
