// @vitest-environment jsdom
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { definicionDe } from '../../../../test/politicasFixtures'
import type { TipoPolitica } from '../api'
import type { ParametrosEnEdicion } from '../validation'
import { CondicionesFijas } from './CondicionesFijas'
import { EditorDeTareas } from './EditorDeTareas'
import { FormularioDeParametros } from './FormularioDeParametros'

function Contenedor({ tipo, inicial = {}, errores = {} }: { tipo: TipoPolitica; inicial?: ParametrosEnEdicion; errores?: Record<string, string> }) {
  const [valores, setValores] = useState<ParametrosEnEdicion>(inicial)
  return (
    <>
      <FormularioDeParametros id="p" definicion={definicionDe(tipo)} valores={valores} onChange={setValores} errores={errores} />
      <output data-testid="valores">{JSON.stringify(valores)}</output>
    </>
  )
}

const valores = () => JSON.parse(screen.getByTestId('valores').textContent ?? '{}') as Record<string, unknown>

describe('FormularioDeParametros', () => {
  it('ofrece solo los parámetros del tipo: Permisos con anticipación y Ausencias sin ella', () => {
    const { unmount } = render(<Contenedor tipo="permisos" />)
    expect(screen.getByLabelText('Permite nuevas solicitudes')).toBeInTheDocument()
    expect(screen.getByLabelText('Exige justificación')).toBeInTheDocument()
    expect(screen.getByLabelText('Exige documento de respaldo')).toBeInTheDocument()
    expect(screen.getByLabelText('Anticipación mínima (días calendario)')).toBeInTheDocument()
    unmount()

    render(<Contenedor tipo="ausencias" />)
    expect(screen.queryByLabelText(/anticipación/i)).not.toBeInTheDocument()
  })

  it('no asigna valores de negocio supuestos: todo comienza «Sin definir»', () => {
    render(<Contenedor tipo="permisos" />)

    for (const etiqueta of ['Permite nuevas solicitudes', 'Exige justificación', 'Exige documento de respaldo']) {
      expect(screen.getByLabelText(etiqueta)).toHaveValue('')
    }
    expect(screen.getByLabelText('Anticipación mínima (días calendario)')).toHaveValue(null)
  })

  it('explica la unidad de la anticipación y guarda las elecciones', async () => {
    const user = userEvent.setup()
    render(<Contenedor tipo="permisos" />)

    expect(screen.getByText(/días calendario enteros, iguales o mayores que 0/i)).toBeInTheDocument()
    await user.selectOptions(screen.getByLabelText('Permite nuevas solicitudes'), 'true')
    await user.selectOptions(screen.getByLabelText('Exige documento de respaldo'), 'false')
    await user.type(screen.getByLabelText('Anticipación mínima (días calendario)'), '3')

    expect(valores()).toEqual({ disponible: true, documento_obligatorio: false, anticipacion_dias: '3' })

    await user.selectOptions(screen.getByLabelText('Permite nuevas solicitudes'), '')
    expect(valores().disponible).toBeUndefined()
  })

  it('muestra el error del servidor junto al campo', () => {
    render(<Contenedor tipo="permisos" errores={{ 'parametros.anticipacion_dias': 'Debe ser igual o mayor que 0.' }} />)

    expect(screen.getByLabelText('Anticipación mínima (días calendario)')).toHaveAccessibleDescription('Debe ser igual o mayor que 0.')
  })

  it('Offboarding muestra el editor de tareas', () => {
    render(<Contenedor tipo="offboarding" />)

    expect(screen.getByRole('group', { name: 'Tareas del cierre administrativo' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Agregar tarea' })).toBeInTheDocument()
  })
})

function EditorConEstado({ inicial = [], errores = {} }: { inicial?: { nombre: string; descripcion: string | null; exige_evidencia: boolean }[]; errores?: Record<string, string> }) {
  const [tareas, setTareas] = useState(inicial)
  return (
    <>
      <EditorDeTareas id="t" tareas={tareas} onChange={setTareas} errores={errores} />
      <output data-testid="tareas">{JSON.stringify(tareas)}</output>
    </>
  )
}

const tareas = () => JSON.parse(screen.getByTestId('tareas').textContent ?? '[]') as { nombre: string; descripcion: string | null; exige_evidencia: boolean }[]

describe('EditorDeTareas', () => {
  it('agrega, edita, reordena y quita tareas', async () => {
    const user = userEvent.setup()
    render(<EditorConEstado />)

    expect(screen.getByText(/una plantilla vacía es válida/i)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Agregar tarea' }))
    await user.click(screen.getByRole('button', { name: 'Agregar tarea' }))
    await user.type(screen.getByLabelText('Nombre de la tarea 1'), 'Devolver equipos')
    await user.type(screen.getByLabelText('Nombre de la tarea 2'), 'Cerrar accesos')
    await user.click(screen.getAllByLabelText('Exige evidencia para completarse')[1])

    expect(tareas().map((t) => t.nombre)).toEqual(['Devolver equipos', 'Cerrar accesos'])
    expect(tareas()[1].exige_evidencia).toBe(true)

    await user.click(screen.getByRole('button', { name: 'Subir la tarea 2' }))
    expect(tareas().map((t) => t.nombre)).toEqual(['Cerrar accesos', 'Devolver equipos'])
    expect(screen.getByRole('button', { name: 'Subir la tarea 1' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Bajar la tarea 2' })).toBeDisabled()

    await user.click(screen.getByRole('button', { name: 'Quitar la tarea 1' }))
    expect(tareas().map((t) => t.nombre)).toEqual(['Devolver equipos'])
  })

  it('muestra los errores por tarea y bloquea agregar al llegar al máximo de 30', () => {
    const treinta = Array.from({ length: 30 }, (_, i) => ({ nombre: `Tarea ${i + 1}`, descripcion: null, exige_evidencia: false }))
    render(<EditorConEstado inicial={treinta} errores={{ 'parametros.tareas.0.nombre': 'Ya existe una tarea con este nombre.' }} />)

    expect(screen.getByLabelText('Nombre de la tarea 1')).toHaveAccessibleDescription('Ya existe una tarea con este nombre.')
    expect(screen.getByRole('button', { name: 'Agregar tarea' })).toBeDisabled()
    expect(screen.getByText('Alcanzaste el máximo de tareas.')).toBeInTheDocument()
  })
})

describe('CondicionesFijas', () => {
  it('muestra las reglas del servidor como información de solo lectura, sin controles de edición', () => {
    const onClick = vi.fn()
    render(<CondicionesFijas reglas={definicionDe('offboarding').reglas_fijas} />)

    const region = screen.getByRole('region', { name: 'Reglas fijas' })
    expect(within(region).getAllByRole('listitem')).toHaveLength(2)
    expect(within(region).getByText(/motivo y la fecha efectiva de salida son siempre obligatorios/i)).toBeInTheDocument()
    expect(within(region).queryByRole('button')).not.toBeInTheDocument()
    expect(within(region).queryByRole('textbox')).not.toBeInTheDocument()
    expect(onClick).not.toHaveBeenCalled()
  })

  it('no muestra nada si no hay reglas', () => {
    const { container } = render(<CondicionesFijas reglas={[]} />)

    expect(container).toBeEmptyDOMElement()
  })
})
