// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { TIPOS, unidad } from '../../../../test/unidadesFixtures'
import { UnidadFila } from './UnidadFila'

function renderFila(datos: ReturnType<typeof unidad>, esAdmin = false) {
  return render(
    <table>
      <tbody>
        <UnidadFila unidad={datos} esAdmin={esAdmin} />
      </tbody>
    </table>,
  )
}

const raiz = unidad({ id: 1, codigo: 'UO-001', nombre: 'Gerencia general', tipo: TIPOS.Gerencia, unidad_padre: null, nivel: 0 })
const hija = unidad({ id: 2, codigo: 'UO-002', nombre: 'Talento humano', nivel: 1 })
const nieta = unidad({
  id: 9,
  codigo: 'UO-009',
  nombre: 'Reclutamiento',
  tipo: TIPOS.Seccion,
  unidad_padre: { id: 2, codigo: 'UO-002', nombre: 'Talento humano' },
  nivel: 2,
})

describe('UnidadFila', () => {
  it('sangra el nombre 24 px por cada nivel', () => {
    for (const [datos, esperado] of [
      [raiz, '0px'],
      [hija, '24px'],
      [nieta, '48px'],
    ] as const) {
      const { unmount } = renderFila(datos)
      expect(screen.getByText(datos.nombre)).toHaveStyle({ paddingLeft: esperado })
      unmount()
    }
  })

  it('las unidades con padre llevan un indicador de dependencia decorativo y la raíz no', () => {
    const conPadre = renderFila(hija)
    const indicador = conPadre.container.querySelector('svg')
    expect(indicador).toHaveAttribute('aria-hidden', 'true')
    conPadre.unmount()

    const sinPadre = renderFila(raiz)
    expect(sinPadre.container.querySelector('svg')).toBeNull()
  })

  it('antepone un prefijo visualmente oculto con el nivel para los lectores de pantalla', () => {
    renderFila(nieta)

    const prefijo = screen.getByText('Nivel 3:')
    expect(prefijo).toHaveClass('sr-only')
    expect(screen.getByRole('rowheader')).toHaveTextContent('Nivel 3: Reclutamiento')
  })

  it('repite la unidad padre en su columna y muestra "—" en la raíz', () => {
    const { unmount } = renderFila(nieta)
    expect(screen.getByRole('cell', { name: 'Talento humano' })).toBeInTheDocument()
    unmount()

    renderFila(raiz)
    expect(screen.getByRole('cell', { name: '—' })).toBeInTheDocument()
  })

  it('muestra código, tipo y el badge de estado: normal si está activa y neutro si está inactiva', () => {
    const activa = renderFila(hija)
    expect(screen.getByText('UO-002')).toBeInTheDocument()
    expect(screen.getByText('Departamento')).toBeInTheDocument()
    expect(screen.getByText('Activa')).toHaveClass('bg-soft')
    activa.unmount()

    renderFila(unidad({ estado: 'Inactiva' }))
    expect(screen.getByText('Inactiva')).toHaveClass('bg-border')
  })

  it('el Usuario no ve la celda de acciones', () => {
    renderFila(hija, false)

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('el Administrador ve Editar y Desactivar, nunca Eliminar', () => {
    renderFila(hija, true)

    expect(screen.getByRole('button', { name: 'Editar Talento humano' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Desactivar Talento humano' })).toBeEnabled()
    expect(screen.queryByRole('button', { name: /eliminar/i })).not.toBeInTheDocument()
  })
})
