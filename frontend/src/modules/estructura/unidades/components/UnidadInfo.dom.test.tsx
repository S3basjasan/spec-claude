// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { detalle, detalleConAuditoria } from '../../../../test/unidadesFixtures'
import { UnidadInfo } from './UnidadInfo'

/** Valor (`dd`) que acompaña a un título (`dt`). */
function valorDe(titulo: string) {
  return screen.getByText(titulo).nextElementSibling
}

describe('UnidadInfo', () => {
  it('muestra en solo lectura el código, las fechas y las cantidades', () => {
    render(<UnidadInfo unidad={detalle({ codigo: 'UO-002', dependientes_activas: 2, posiciones_vigentes: 3 })} />)

    expect(screen.getByRole('group', { name: 'Datos de la unidad' })).toBeInTheDocument()
    expect(valorDe('Código')).toHaveTextContent('UO-002')
    expect(valorDe('Fecha de creación')).toHaveTextContent('14/10/2026')
    expect(valorDe('Unidades dependientes activas')).toHaveTextContent('2')
    expect(valorDe('Posiciones ocupadas o vacantes')).toHaveTextContent('3')
  })

  it('no ofrece ningún campo editable', () => {
    render(<UnidadInfo unidad={detalle()} />)

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })

  it('la fecha de supresión aparece vacía ("—") mientras la unidad está activa', () => {
    render(<UnidadInfo unidad={detalle({ fecha_supresion: null })} />)

    expect(valorDe('Fecha de supresión')).toHaveTextContent('—')
  })

  it('muestra la fecha de supresión de una unidad inactiva', () => {
    render(<UnidadInfo unidad={detalle({ estado: 'Inactiva', fecha_supresion: '2026-10-20' })} />)

    expect(valorDe('Fecha de supresión')).toHaveTextContent('20/10/2026')
  })

  it('sin auditoría en el detalle no muestra quién creó ni quién modificó', () => {
    render(<UnidadInfo unidad={detalle()} />)

    expect(screen.queryByText('Creada por')).not.toBeInTheDocument()
    expect(screen.queryByText('Última modificación')).not.toBeInTheDocument()
  })

  it('con auditoría muestra quién la creó y que aún no se modificó', () => {
    render(<UnidadInfo unidad={detalleConAuditoria()} />)

    expect(valorDe('Creada por')).toHaveTextContent(/Ana Administradora el 14\/10\/2026/)
    expect(valorDe('Última modificación')).toHaveTextContent('Sin modificaciones')
  })

  it('muestra quién modificó por última vez y avisa si el usuario ya no existe', () => {
    const unidad = detalleConAuditoria()
    render(
      <UnidadInfo
        unidad={{
          ...unidad,
          auditoria: {
            created_by: { id: 1, nombre: null },
            created_at: '2026-10-14T12:00:00Z',
            updated_by: { id: 2, nombre: 'Beto Administrador' },
            updated_at: '2026-10-15T12:00:00Z',
          },
        }}
      />,
    )

    expect(valorDe('Creada por')).toHaveTextContent(/Usuario desconocido el 14\/10\/2026/)
    expect(valorDe('Última modificación')).toHaveTextContent(/Beto Administrador el 15\/10\/2026/)
  })
})
