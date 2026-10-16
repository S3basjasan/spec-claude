// @vitest-environment jsdom
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { detalle } from '../../../../test/fichasFixtures'
import { FichaDatos } from './FichaDatos'
import { ProximoIngreso } from './ProximoIngreso'

describe('ProximoIngreso', () => {
  it('muestra fecha, contrato, puesto y unidad con la marca Programado', () => {
    render(
      <ProximoIngreso
        ingreso={{
          fecha_ingreso: '2026-11-01',
          tipo_contrato: { clave: 'plazo_fijo', etiqueta: 'Plazo fijo' },
          posicion: { id: 3, puesto: 'Analista de datos', unidad: 'Finanzas', jefatura: null },
          programado: true,
        }}
      />,
    )

    expect(screen.getByText('Ingreso programado')).toBeInTheDocument()
    expect(screen.getByText('Programado')).toBeInTheDocument()
    expect(screen.getByText('Desde el 2026-11-01 · Plazo fijo')).toBeInTheDocument()
    expect(screen.getByText('Analista de datos · Finanzas')).toBeInTheDocument()
  })

  it('el detalle de una ficha sin vínculo con ingreso programado lo muestra junto al estado', () => {
    render(
      <FichaDatos
        ficha={detalle({
          laboral: {
            disponibilidad: 'disponible',
            estado: 'SinVinculoVigente',
            puesto: null,
            unidad: null,
            proximo_ingreso: {
              fecha_ingreso: '2026-11-01',
              tipo_contrato: { clave: 'indefinido', etiqueta: 'Indefinido' },
              posicion: null,
              programado: true,
            },
          },
        })}
        onReintentar={() => {}}
      />,
    )

    expect(screen.getAllByText('Sin vínculo vigente').length).toBeGreaterThan(0)
    expect(screen.getByText('Ingreso programado')).toBeInTheDocument()
  })

  it('el detalle sin ingreso programado no muestra el bloque', () => {
    render(<FichaDatos ficha={detalle()} onReintentar={() => {}} />)

    expect(screen.queryByText('Ingreso programado')).not.toBeInTheDocument()
  })
})
