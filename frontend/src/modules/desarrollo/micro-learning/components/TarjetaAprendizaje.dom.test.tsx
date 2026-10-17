// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { fila, progreso } from '../../../../test/microFixtures'
import type { FilaMicro } from '../api'
import { ResumenAprendizaje } from './ResumenAprendizaje'
import { TarjetaAprendizaje } from './TarjetaAprendizaje'

function renderTarjeta(micro: FilaMicro, pestana: 'asignado' | 'completado', onCertificado = vi.fn()) {
  render(
    <MemoryRouter>
      <TarjetaAprendizaje micro={micro} pestana={pestana} onCertificado={onCertificado} />
    </MemoryRouter>,
  )
  return onCertificado
}

describe('TarjetaAprendizaje', () => {
  it('un contenido asignado sin iniciar ofrece Iniciar y enlaza a la reproducción', () => {
    renderTarjeta(fila({ id: 9, titulo: 'Nuevo', progreso: null, motivo: 'brecha' }), 'asignado')

    const enlace = screen.getByRole('link', { name: 'Iniciar Nuevo' })
    expect(enlace).toHaveAttribute('href', '/micro-learning/9')
    expect(screen.getByText('Sin iniciar')).toBeInTheDocument()
    expect(screen.getByText('Por una brecha detectada')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /certificado/i })).not.toBeInTheDocument()
  })

  it('un video en progreso ofrece Continuar y muestra dónde lo dejó', () => {
    renderTarjeta(fila({ titulo: 'Video', tipo: 'Video', progreso: progreso({ posicion_segundos: 95 }), motivo: 'iniciado' }), 'asignado')

    expect(screen.getByRole('link', { name: 'Continuar Video' })).toBeInTheDocument()
    expect(screen.getByText('En progreso')).toBeInTheDocument()
    expect(screen.getByText('Retomas en 1:35')).toBeInTheDocument()
  })

  it('un completado muestra Ver, el último puntaje y descarga el certificado', async () => {
    const onCertificado = renderTarjeta(
      fila({ titulo: 'Quiz', tipo: 'Quiz', progreso: progreso({ estado: 'Completado' }), ultimo_puntaje: { correctas: 4, total: 5 }, completado_en: '2026-10-16T10:00:00Z' }),
      'completado',
    )

    expect(screen.getByRole('link', { name: 'Ver Quiz' })).toBeInTheDocument()
    expect(screen.getByText('Completado')).toBeInTheDocument()
    expect(screen.getByText('Último puntaje: 4 de 5')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Descargar certificado de Quiz' }))

    expect(onCertificado).toHaveBeenCalledOnce()
  })

  it('muestra competencia y duración y la marca de conocimientos', () => {
    renderTarjeta(fila({ duracion_minutos: 90 }), 'asignado')

    expect(screen.getByText('Comunicación efectiva')).toBeInTheDocument()
    expect(screen.getByText('1 h 30 min')).toBeInTheDocument()
    expect(screen.getByText('Apto para conocimientos')).toBeInTheDocument()
  })
})

describe('ResumenAprendizaje', () => {
  it('muestra completados, en progreso, horas acumuladas y certificados', () => {
    render(<ResumenAprendizaje resumen={{ completados: 3, en_progreso: 2, horas_acumuladas: 1.5, certificados: 3 }} />)

    expect(screen.getByText('Completados').nextSibling).toHaveTextContent('3')
    expect(screen.getByText('En progreso').nextSibling).toHaveTextContent('2')
    expect(screen.getByText('Horas acumuladas').nextSibling).toHaveTextContent('1,5')
    expect(screen.getByText('Certificados').nextSibling).toHaveTextContent('3')
  })
})
