// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { fila } from '../../../../test/catalogoFormativoFixtures'
import { CatalogoMatriz } from './CatalogoMatriz'

const items = [
  fila({ competencia_id: 1, nombre: 'Comunicación efectiva', codigo: 'COMP-0001', cursos_count: 2, microcontenidos_count: 1 }),
  fila({ competencia_id: 2, nombre: 'Adaptabilidad', codigo: 'COMP-0007', estado: 'Inactivo', area_formativa: null, cursos_count: 0, microcontenidos_count: 0 }),
]

describe('CatalogoMatriz', () => {
  it('muestra las columnas del contrato', () => {
    render(<CatalogoMatriz items={items} onVerDetalle={vi.fn()} />)

    const encabezados = screen.getAllByRole('columnheader').map((th) => th.textContent)
    expect(encabezados).toEqual(['Competencia', 'Tipo', 'Área formativa', 'Cursos asociados', 'Micro-contenidos asociados', 'Acciones'])
  })

  it('cada fila muestra código, nombre, tipo, área y cantidades', () => {
    render(<CatalogoMatriz items={items} onVerDetalle={vi.fn()} />)

    const primera = within(screen.getAllByRole('row')[1])
    expect(primera.getByText('Comunicación efectiva')).toBeInTheDocument()
    expect(primera.getByText('COMP-0001')).toBeInTheDocument()
    expect(primera.getByText('Cardinal')).toBeInTheDocument()
    expect(primera.getByText('Habilidades interpersonales')).toBeInTheDocument()
    expect(primera.getByText('2 cursos')).toBeInTheDocument()
    expect(primera.getByText('1 micro-contenido')).toBeInTheDocument()
  })

  it('una competencia inactiva sin recursos lo indica con texto', () => {
    render(<CatalogoMatriz items={items} onVerDetalle={vi.fn()} />)

    const segunda = within(screen.getAllByRole('row')[2])
    expect(segunda.getByText('Inactiva')).toBeInTheDocument()
    expect(segunda.getByText('Sin cursos')).toBeInTheDocument()
    expect(segunda.getByText('Sin micro-contenidos')).toBeInTheDocument()
    expect(segunda.getByText('Sin área formativa')).toBeInTheDocument()
  })

  it('"Ver detalle" incluye el nombre de la competencia y abre su detalle', async () => {
    const onVer = vi.fn()
    render(<CatalogoMatriz items={items} onVerDetalle={onVer} />)

    await userEvent.setup().click(screen.getByRole('button', { name: 'Ver detalle de Adaptabilidad' }))

    expect(onVer).toHaveBeenCalledWith(2)
  })
})
