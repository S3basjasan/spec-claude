// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { competencia, fila } from '../../../../test/microFixtures'
import type { FilaMicro } from '../api'
import { TarjetaMicro } from './TarjetaMicro'

function acciones() {
  return {
    onVistaPrevia: vi.fn(),
    onEditar: vi.fn(),
    onPublicar: vi.fn(),
    onArchivar: vi.fn(),
    onReactivar: vi.fn(),
    onEliminar: vi.fn(),
    onHistorial: vi.fn(),
  }
}

function renderTarjeta(micro: FilaMicro, a = acciones()) {
  render(<TarjetaMicro micro={micro} {...a} />)
  return a
}

describe('TarjetaMicro', () => {
  it('muestra título, tipo, estado, duración, completaciones y la marca de conocimientos', () => {
    renderTarjeta(fila({ titulo: 'Guía de SQL', tipo: 'PDF', duracion_minutos: 75, completaciones: 3 }))

    expect(screen.getByRole('heading', { name: 'Guía de SQL' })).toBeInTheDocument()
    expect(screen.getByText('PDF')).toBeInTheDocument()
    expect(screen.getByText('Publicado')).toBeInTheDocument()
    expect(screen.getByText('1 h 15 min')).toBeInTheDocument()
    expect(screen.getByText('3 completaciones')).toBeInTheDocument()
    expect(screen.getByText('Apto para conocimientos')).toBeInTheDocument()
  })

  it('muestra la primera competencia y «+N» si hay varias', () => {
    renderTarjeta(fila({ competencias: [competencia({ nombre: 'Liderazgo' }), competencia({ id: 2, nombre: 'Negociación' }), competencia({ id: 3, nombre: 'Otra' })] }))

    expect(screen.getByText('Liderazgo')).toBeInTheDocument()
    expect(screen.getByText('+2')).toBeInTheDocument()
  })

  it('sin miniatura muestra una imagen genérica según el tipo', () => {
    renderTarjeta(fila({ tipo: 'Quiz', miniatura_url: null }))

    expect(screen.getByRole('img', { name: 'Imagen genérica de Quiz' })).toBeInTheDocument()
  })

  it('un borrador ofrece Vista previa, Editar, Publicar, Historial y Eliminar, pero no Archivar', async () => {
    const a = renderTarjeta(fila({ estado: 'Borrador', titulo: 'Borr' }))
    const user = userEvent.setup()

    expect(screen.queryByRole('button', { name: 'Archivar Borr' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reactivar Borr' })).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Publicar Borr' }))
    await user.click(screen.getByRole('button', { name: 'Editar Borr' }))
    await user.click(screen.getByRole('button', { name: 'Vista previa Borr' }))
    await user.click(screen.getByRole('button', { name: 'Eliminar Borr' }))

    expect(a.onPublicar).toHaveBeenCalledOnce()
    expect(a.onEditar).toHaveBeenCalledOnce()
    expect(a.onVistaPrevia).toHaveBeenCalledOnce()
    expect(a.onEliminar).toHaveBeenCalledOnce()
  })

  it('un publicado ofrece Archivar y no Publicar', async () => {
    const a = renderTarjeta(fila({ estado: 'Publicado', titulo: 'Pub' }))

    expect(screen.queryByRole('button', { name: 'Publicar Pub' })).not.toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Archivar Pub' }))

    expect(a.onArchivar).toHaveBeenCalledOnce()
  })

  it('un archivado ofrece Reactivar y no Editar ni Archivar', async () => {
    const a = renderTarjeta(fila({ estado: 'Archivado', titulo: 'Arc' }))

    expect(screen.queryByRole('button', { name: 'Editar Arc' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Archivar Arc' })).not.toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reactivar Arc' }))

    expect(a.onReactivar).toHaveBeenCalledOnce()
  })

  it('con progreso no ofrece Eliminar e indica que solo puede archivarse', () => {
    renderTarjeta(fila({ estado: 'Publicado', titulo: 'Con progreso', puede_eliminarse: false }))

    expect(screen.queryByRole('button', { name: /Eliminar/ })).not.toBeInTheDocument()
    expect(screen.getByText('Tiene progreso de colaboradores: solo puede archivarse.')).toBeInTheDocument()
  })

  it('bloquea las acciones mientras otra operación está en curso', () => {
    render(<TarjetaMicro micro={fila({ titulo: 'Oc' })} ocupado {...acciones()} />)

    expect(screen.getByRole('button', { name: 'Editar Oc' })).toBeDisabled()
  })
})
