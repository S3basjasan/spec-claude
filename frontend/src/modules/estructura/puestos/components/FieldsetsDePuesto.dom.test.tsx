// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { FormProvider, useForm } from 'react-hook-form'
import { describe, expect, it } from 'vitest'
import { competenciaAsignada, competenciaDisponible } from '../../../../test/puestosFixtures'
import type { PuestoFormValues } from '../validation'
import { CompetenciasFieldset } from './CompetenciasFieldset'
import { FuncionesFieldset } from './FuncionesFieldset'
import { RequisitosFieldset } from './RequisitosFieldset'

function Harness({ children, valores = {} }: { children: ReactNode; valores?: Partial<PuestoFormValues> }) {
  const metodos = useForm<PuestoFormValues>({
    defaultValues: {
      nombre: '',
      descripcion: '',
      mision: '',
      funciones: [],
      requisitos: [],
      competencias: [],
      ...valores,
    },
  })
  return <FormProvider {...metodos}>{children}</FormProvider>
}

const tresFunciones: PuestoFormValues['funciones'] = [
  { tipo: 'Esencial', descripcion: 'Primera' },
  { tipo: 'Complementaria', descripcion: 'Segunda' },
  { tipo: 'Complementaria', descripcion: 'Tercera' },
]

const textosDeFunciones = () => screen.getAllByRole('textbox', { name: /^Descripción de la función/ }).map((campo) => (campo as HTMLInputElement).value)

describe('FuncionesFieldset', () => {
  it('numera las filas desde 1 y deshabilita Subir en la primera y Bajar en la última', () => {
    render(
      <Harness valores={{ funciones: tresFunciones }}>
        <FuncionesFieldset />
      </Harness>,
    )

    expect(textosDeFunciones()).toEqual(['Primera', 'Segunda', 'Tercera'])
    expect(screen.getByRole('button', { name: 'Subir función 1' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Subir función 2' })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Bajar función 2' })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Bajar función 3' })).toBeDisabled()
  })

  it('reordena con Subir y Bajar y anuncia la nueva posición', async () => {
    render(
      <Harness valores={{ funciones: tresFunciones }}>
        <FuncionesFieldset />
      </Harness>,
    )
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: 'Bajar función 1' }))
    expect(textosDeFunciones()).toEqual(['Segunda', 'Primera', 'Tercera'])
    expect(screen.getByRole('status')).toHaveTextContent('Función movida a la posición 2 de 3.')

    await user.click(screen.getByRole('button', { name: 'Subir función 3' }))
    expect(textosDeFunciones()).toEqual(['Segunda', 'Tercera', 'Primera'])
    expect(screen.getByRole('status')).toHaveTextContent('Función movida a la posición 2 de 3.')
  })

  it('conserva el tipo de cada función al reordenarla', async () => {
    render(
      <Harness valores={{ funciones: tresFunciones }}>
        <FuncionesFieldset />
      </Harness>,
    )

    await userEvent.setup().click(screen.getByRole('button', { name: 'Bajar función 1' }))

    expect(screen.getByRole('combobox', { name: 'Tipo de la función 1' })).toHaveValue('Complementaria')
    expect(screen.getByRole('combobox', { name: 'Tipo de la función 2' })).toHaveValue('Esencial')
  })

  it('agrega y quita filas y renumera sin saltos', async () => {
    render(
      <Harness valores={{ funciones: tresFunciones }}>
        <FuncionesFieldset />
      </Harness>,
    )
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: 'Agregar función' }))
    expect(textosDeFunciones()).toEqual(['Primera', 'Segunda', 'Tercera', ''])

    await user.click(screen.getByRole('button', { name: 'Quitar función 2' }))
    expect(textosDeFunciones()).toEqual(['Primera', 'Tercera', ''])
    expect(screen.getByRole('textbox', { name: 'Descripción de la función 3' })).toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: 'Descripción de la función 4' })).not.toBeInTheDocument()
  })

  it('muestra un contador n/500 por función', () => {
    render(
      <Harness valores={{ funciones: [{ tipo: 'Esencial', descripcion: 'Hola' }] }}>
        <FuncionesFieldset />
      </Harness>,
    )

    expect(screen.getByText('4/500')).toBeInTheDocument()
  })

  it('deshabilita Agregar al llegar a 20 e indica el máximo', () => {
    const veinte = Array.from({ length: 20 }, (_, n) => ({ tipo: 'Esencial' as const, descripcion: `Función ${n + 1}` }))
    render(
      <Harness valores={{ funciones: veinte }}>
        <FuncionesFieldset />
      </Harness>,
    )

    expect(screen.getByRole('button', { name: 'Agregar función' })).toBeDisabled()
    expect(screen.getByText('Máximo 20 elementos por lista.')).toBeInTheDocument()
  })
})

describe('RequisitosFieldset', () => {
  it('ofrece los tres tipos con tilde, agrega y quita filas', async () => {
    render(
      <Harness valores={{ requisitos: [{ tipo: 'Formacion', descripcion: 'Título' }] }}>
        <RequisitosFieldset />
      </Harness>,
    )
    const user = userEvent.setup()

    const tipo = screen.getByRole('combobox', { name: 'Tipo del requisito 1' })
    expect(within(tipo).getAllByRole('option').map((opcion) => opcion.textContent)).toEqual(['Formación', 'Experiencia', 'Capacitación'])

    await user.click(screen.getByRole('button', { name: 'Agregar requisito' }))
    expect(screen.getByRole('textbox', { name: 'Descripción del requisito 2' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Tipo del requisito 2' })).toHaveValue('Capacitacion')

    await user.click(screen.getByRole('button', { name: 'Quitar requisito 1' }))
    expect(screen.queryByRole('textbox', { name: 'Descripción del requisito 2' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Subir|Bajar/ })).not.toBeInTheDocument()
  })

  it('deshabilita Agregar al llegar a 20', () => {
    const veinte = Array.from({ length: 20 }, (_, n) => ({ tipo: 'Capacitacion' as const, descripcion: `Curso ${n + 1}` }))
    render(
      <Harness valores={{ requisitos: veinte }}>
        <RequisitosFieldset />
      </Harness>,
    )

    expect(screen.getByRole('button', { name: 'Agregar requisito' })).toBeDisabled()
  })
})

const disponibles = [
  competenciaDisponible({ competencia_id: 1, codigo: 'COMP-0001', nombre: 'Liderazgo', tipo: 'Cardinal' }),
  competenciaDisponible({
    competencia_id: 2,
    codigo: 'COMP-0002',
    nombre: 'Gestión de proyectos',
    tipo: 'Especifica',
    grados: [
      { grado: 'A', descripcion: 'a' },
      { grado: 'B', descripcion: 'b' },
    ],
  }),
  competenciaDisponible({ competencia_id: 3, codigo: 'COMP-0003', nombre: 'Comunicación efectiva' }),
]

describe('CompetenciasFieldset', () => {
  it('solo ofrece competencias activas que aún no están en el puesto', async () => {
    render(
      <Harness valores={{ competencias: [{ competencia_id: 1, grado_requerido: 'A' }, { competencia_id: undefined as unknown as number, grado_requerido: '' }] }}>
        <CompetenciasFieldset disponibles={disponibles} />
      </Harness>,
    )
    const user = userEvent.setup()

    await user.click(screen.getByRole('combobox', { name: 'Competencia 2' }))
    const opciones = within(await screen.findByRole('listbox', { name: 'Competencia 2' })).getAllByRole('option')

    expect(opciones.map((opcion) => opcion.textContent)).toEqual(['Gestión de proyectosCOMP-0002', 'Comunicación efectivaCOMP-0003'])
  })

  it('el grado solo ofrece los de la competencia elegida y se reinicia al cambiarla', async () => {
    render(
      <Harness valores={{ competencias: [{ competencia_id: undefined as unknown as number, grado_requerido: '' }] }}>
        <CompetenciasFieldset disponibles={disponibles} />
      </Harness>,
    )
    const user = userEvent.setup()
    const grado = screen.getByRole('combobox', { name: 'Grado requerido' })
    expect(grado).toBeDisabled()

    await user.click(screen.getByRole('combobox', { name: 'Competencia 1' }))
    await user.click(await screen.findByRole('option', { name: /Gestión de proyectos/ }))
    expect(within(grado).getAllByRole('option').map((opcion) => opcion.textContent)).toEqual(['Elige un grado', 'A', 'B'])

    await user.selectOptions(grado, 'B')
    expect(grado).toHaveValue('B')

    await user.click(screen.getByRole('combobox', { name: 'Competencia 1' }))
    await user.click(await screen.findByRole('option', { name: /Liderazgo/ }))
    expect(within(screen.getByRole('combobox', { name: 'Grado requerido' })).getAllByRole('option')).toHaveLength(5)
    expect(screen.getByRole('combobox', { name: 'Grado requerido' })).toHaveValue('')
  })

  it('muestra código, tipo y estado de la competencia elegida como datos de solo lectura', () => {
    render(
      <Harness valores={{ competencias: [{ competencia_id: 2, grado_requerido: 'A' }] }}>
        <CompetenciasFieldset disponibles={disponibles} />
      </Harness>,
    )

    expect(screen.getByText(/COMP-0002 · Específica/)).toBeInTheDocument()
    expect(screen.getByText('Activo')).toBeInTheDocument()
  })

  it('agrega y quita filas', async () => {
    render(
      <Harness valores={{ competencias: [{ competencia_id: 1, grado_requerido: 'A' }] }}>
        <CompetenciasFieldset disponibles={disponibles} />
      </Harness>,
    )
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: 'Agregar competencia' }))
    expect(screen.getByRole('combobox', { name: 'Competencia 2' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Quitar competencia 1' }))
    expect(screen.queryByRole('combobox', { name: 'Competencia 2' })).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Competencia 1' })).toHaveValue('')
  })

  it('una competencia inactiva ya asignada muestra el grado bloqueado y solo se puede quitar', async () => {
    render(
      <Harness valores={{ competencias: [{ competencia_id: 7, grado_requerido: 'C' }] }}>
        <CompetenciasFieldset
          disponibles={disponibles}
          asignadas={[competenciaAsignada({ competencia_id: 7, codigo: 'COMP-0007', nombre: 'Adaptabilidad', estado: 'Inactivo', grado_requerido: 'C' })]}
        />
      </Harness>,
    )

    expect(screen.getByText('Adaptabilidad')).toBeInTheDocument()
    expect(screen.getByText('Inactiva')).toBeInTheDocument()
    expect(screen.getByLabelText('Grado C')).toBeInTheDocument()
    expect(screen.getByText(/La competencia está inactiva: solo puedes dejarla como está o quitarla./)).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()

    await userEvent.setup().click(screen.getByRole('button', { name: 'Quitar competencia 1' }))
    expect(screen.queryByText('Adaptabilidad')).not.toBeInTheDocument()
  })

  it('deshabilita Agregar al llegar a 20', () => {
    const veinte = Array.from({ length: 20 }, (_, n) => ({ competencia_id: n + 1, grado_requerido: 'A' }))
    render(
      <Harness valores={{ competencias: veinte }}>
        <CompetenciasFieldset disponibles={disponibles} />
      </Harness>,
    )

    expect(screen.getByRole('button', { name: 'Agregar competencia' })).toBeDisabled()
  })
})
