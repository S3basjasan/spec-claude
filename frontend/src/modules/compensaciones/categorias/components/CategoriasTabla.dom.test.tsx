// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { categoriaReconocimiento } from '../../../../test/categoriasReconocimientoFixtures'
import { CategoriasTabla } from './CategoriasTabla'

const activa = categoriaReconocimiento()
const inactiva = categoriaReconocimiento({ id: 2, codigo: 'CAT-002', nombre: 'Antigua', estado: 'Inactivo' })
const huerfana = categoriaReconocimiento({
  id: 3,
  codigo: 'CAT-003',
  nombre: 'Huérfana',
  competencia: { id: 11, codigo: 'COMP-0011', nombre: 'Retirada', estado: 'Inactivo' },
  competencia_activa: false,
})

function renderTabla(esAdmin: boolean, categorias = [activa, inactiva, huerfana]) {
  const acciones = { onVer: vi.fn(), onEditar: vi.fn(), onCambiarEstado: vi.fn() }
  render(<CategoriasTabla categorias={categorias} esAdmin={esAdmin} {...acciones} />)
  return acciones
}

function simularAncho(ancho: boolean) {
  vi.stubGlobal('matchMedia', (consulta: string) => ({
    matches: ancho,
    media: consulta,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  }))
}

afterEach(() => vi.unstubAllGlobals())

describe('CategoriasTabla · pantalla ancha', () => {
  it('muestra las siete columnas de la spec', () => {
    simularAncho(true)
    renderTabla(true)

    const tabla = screen.getByRole('table', { name: 'Categorías de reconocimiento, ordenadas por código' })
    const encabezados = within(tabla).getAllByRole('columnheader').map((c) => c.textContent)
    expect(encabezados).toEqual(['Código', 'Ícono', 'Nombre', 'Competencia asociada', 'Puntuación base', 'Estado', 'Acciones'])
  })

  it('el Administrador ve Ver, Editar y Activar / Desactivar con texto según el estado', () => {
    simularAncho(true)
    renderTabla(true)

    expect(screen.getByRole('button', { name: 'Desactivar Trabajo en equipo' })).toHaveTextContent('Desactivar')
    expect(screen.getByRole('button', { name: 'Activar Antigua' })).toHaveTextContent('Activar')
    expect(screen.getAllByRole('button', { name: /^Editar / })).toHaveLength(3)
    expect(screen.getAllByRole('button', { name: /^Ver / })).toHaveLength(3)
    expect(screen.queryByRole('button', { name: /eliminar/i })).not.toBeInTheDocument()
  })

  it('un Usuario solo ve «Ver»', () => {
    simularAncho(true)
    renderTabla(false)

    expect(screen.getAllByRole('button').map((b) => b.getAttribute('aria-label'))).toEqual([
      'Ver Trabajo en equipo',
      'Ver Antigua',
      'Ver Huérfana',
    ])
  })

  it('advierte «Competencia inactiva» solo en la categoría afectada', () => {
    simularAncho(true)
    renderTabla(false)

    const filas = screen.getAllByRole('row')
    expect(within(filas[1]).queryByText('Competencia inactiva')).not.toBeInTheDocument()
    expect(within(filas[3]).getByText('Competencia inactiva')).toBeInTheDocument()
    expect(within(filas[3]).getByText('Retirada')).toBeInTheDocument()
  })

  it('invoca la acción elegida con la categoría de su fila', async () => {
    simularAncho(true)
    const acciones = renderTabla(true)
    const usuario = userEvent.setup()

    await usuario.click(screen.getByRole('button', { name: 'Ver Antigua' }))
    await usuario.click(screen.getByRole('button', { name: 'Editar Antigua' }))
    await usuario.click(screen.getByRole('button', { name: 'Activar Antigua' }))

    expect(acciones.onVer).toHaveBeenCalledWith(inactiva)
    expect(acciones.onEditar).toHaveBeenCalledWith(inactiva)
    expect(acciones.onCambiarEstado).toHaveBeenCalledWith(inactiva)
  })

  it('muestra el estado con su badge', () => {
    simularAncho(true)
    renderTabla(true)

    expect(screen.getAllByText('Activo')).toHaveLength(2)
    expect(screen.getByText('Inactivo')).toBeInTheDocument()
  })
})

describe('CategoriasTabla · pantalla estrecha', () => {
  it('muestra tarjetas con los mismos datos y acciones, sin tabla', () => {
    simularAncho(false)
    renderTabla(true)

    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    const tarjetas = screen.getAllByRole('listitem')
    expect(tarjetas).toHaveLength(3)
    expect(within(tarjetas[0]).getByText('Trabajo en equipo')).toBeInTheDocument()
    expect(within(tarjetas[0]).getByText('CAT-001')).toBeInTheDocument()
    expect(within(tarjetas[0]).getByRole('button', { name: 'Desactivar Trabajo en equipo' })).toBeInTheDocument()
    expect(within(tarjetas[2]).getByText('Competencia inactiva')).toBeInTheDocument()
  })

  it('un Usuario solo ve «Ver» en las tarjetas', () => {
    simularAncho(false)
    renderTabla(false)

    expect(screen.queryByRole('button', { name: /^(Editar|Activar|Desactivar) / })).not.toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: /^Ver / })).toHaveLength(3)
  })
})
