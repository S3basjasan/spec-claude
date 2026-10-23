// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { opcionesDeColaborador, opcionesGenerales, pdi, precarga } from '../../../../test/pdiFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarPdi, crearPdi, listarAccionesVinculables, obtenerOpciones, obtenerOpcionesDeColaborador, obtenerPdi, obtenerPrecarga } from '../api'
import { AsistenteDePdiPage } from './AsistenteDePdiPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerOpciones: vi.fn(),
  obtenerOpcionesDeColaborador: vi.fn(),
  obtenerPrecarga: vi.fn(),
  obtenerPdi: vi.fn(),
  crearPdi: vi.fn(),
  actualizarPdi: vi.fn(),
  listarAccionesVinculables: vi.fn(),
}))
vi.mock('../hooks/useUsuarioActual', () => ({ useUsuarioActual: () => 1 }))

function renderAsistente(entrada: string) {
  return renderRoutes(
    <>
      <Route path="/pdi/nuevo" element={<AsistenteDePdiPage />} />
      <Route path="/pdi/:id/editar" element={<AsistenteDePdiPage />} />
      <Route path="/pdi/:id" element={<p>Detalle del plan</p>} />
      <Route path="/pdi" element={<p>Lista</p>} />
    </>,
    entrada,
  )
}

beforeEach(() => {
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opcionesGenerales())
  vi.mocked(obtenerOpcionesDeColaborador).mockReset().mockResolvedValue(opcionesDeColaborador())
  vi.mocked(obtenerPrecarga).mockReset().mockResolvedValue(precarga())
  vi.mocked(obtenerPdi).mockReset().mockResolvedValue(pdi())
  vi.mocked(crearPdi).mockReset().mockResolvedValue(pdi({ id: 7 }))
  vi.mocked(actualizarPdi).mockReset().mockResolvedValue(pdi({ version: 2 }))
  vi.mocked(listarAccionesVinculables).mockReset().mockResolvedValue([])
})

async function llegarAlPaso2(usuario: ReturnType<typeof userEvent.setup>) {
  await usuario.type(await screen.findByLabelText('Objetivo del plan'), 'Cerrar brechas de comunicación')
  await screen.findByText('1 brecha detectada', { exact: false })
  await usuario.click(screen.getByRole('button', { name: 'Continuar' }))
}

describe('Asistente de creación del PDI', () => {
  it('crea un plan de Mejora precargado desde la DNF en tres pasos y envía el cuerpo completo', async () => {
    const usuario = userEvent.setup()
    renderAsistente('/pdi/nuevo?colaborador=10&tipo=Mejora')

    await llegarAlPaso2(usuario)
    expect(await screen.findByRole('region', { name: 'Compromiso 1' })).toBeInTheDocument()
    expect(screen.getByLabelText('Competencia o conocimiento')).toHaveValue('1')
    expect(screen.getByLabelText('Nivel actual')).toHaveValue('C')
    expect(screen.getByLabelText('Nivel a alcanzar')).toHaveValue('B')
    await usuario.type(screen.getByLabelText('Descripción de la acción'), 'Curso de comunicación')
    await usuario.click(screen.getByRole('button', { name: 'Continuar' }))

    await usuario.click(await screen.findByRole('button', { name: 'Agregar hito' }))
    const fecha = (await screen.findByLabelText('Inicio del periodo')) as HTMLInputElement
    await usuario.type(screen.getByLabelText('Descripción del hito'), 'Primera revisión')
    await usuario.type(screen.getByLabelText('Fecha'), fecha.value)
    await usuario.click(screen.getByRole('button', { name: 'Crear PDI (Borrador)' }))

    await waitFor(() => expect(crearPdi).toHaveBeenCalledTimes(1))
    const cuerpo = vi.mocked(crearPdi).mock.calls[0][0]
    expect(cuerpo).toMatchObject({
      colaborador_id: 10,
      tipo: 'Mejora',
      objetivo: 'Cerrar brechas de comunicación',
      puesto_objetivo_id: null,
      responsable_id: 1,
      origen: { tipo: 'dnf', id: 31 },
      hitos: [{ descripcion: 'Primera revisión' }],
    })
    expect(cuerpo.compromisos).toHaveLength(1)
    expect(cuerpo.compromisos[0]).toMatchObject({ dimension: 'Competencia', elemento_id: 1, nivel_actual: 'C', nivel_objetivo: 'B' })
    expect(cuerpo.compromisos[0].acciones[0]).toMatchObject({ tipo: 'Curso', descripcion: 'Curso de comunicación', plan_accion_id: null })
    expect(await screen.findByText('Detalle del plan')).toBeInTheDocument()
  })

  it('Carrera exige el puesto objetivo y el aviso de plan abierto bloquea el avance con enlace al plan', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerOpcionesDeColaborador).mockResolvedValue(opcionesDeColaborador({ plan_abierto: { Mejora: null, Carrera: { id: 9, codigo: 'PDI-0009' }, Talento: null } }))
    renderAsistente('/pdi/nuevo?colaborador=10&tipo=Carrera')

    await usuario.type(await screen.findByLabelText('Objetivo del plan'), 'Prepararse para analista')
    expect(await screen.findByText(/ya tiene un plan abierto de este tipo \(PDI-0009\)/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver el plan abierto' })).toHaveAttribute('href', '/pdi/9')
    expect(screen.getByRole('button', { name: 'Continuar' })).toBeDisabled()
  })

  it('sin puesto objetivo en Carrera no avanza y muestra el error del campo', async () => {
    const usuario = userEvent.setup()
    renderAsistente('/pdi/nuevo?colaborador=10&tipo=Carrera')

    await usuario.type(await screen.findByLabelText('Objetivo del plan'), 'Objetivo')
    await usuario.click(screen.getByRole('button', { name: 'Continuar' }))

    expect(await screen.findByText('El puesto objetivo es obligatorio en Carrera y Talento.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Compromiso 1' })).not.toBeInTheDocument()
  })

  it('la URL preselecciona colaborador, tipo y puesto objetivo', async () => {
    renderAsistente('/pdi/nuevo?colaborador=10&tipo=Talento&puesto_objetivo=3')

    expect(await screen.findByLabelText('Tipo de PDI')).toHaveValue('Talento')
    expect(await screen.findByLabelText('Puesto objetivo')).toHaveValue('3')
    await waitFor(() => expect(obtenerPrecarga).toHaveBeenCalledWith(10, 'Talento', 3))
  })

  it('se pueden quitar los compromisos precargados y agregar uno a mano, y se exige al menos una acción válida', async () => {
    const usuario = userEvent.setup()
    renderAsistente('/pdi/nuevo?colaborador=10&tipo=Mejora')

    await llegarAlPaso2(usuario)
    await usuario.click(await screen.findByRole('button', { name: 'Quitar' }))
    expect(screen.getByText('Aún no hay compromisos.')).toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Agregar compromiso' }))
    expect(await screen.findByRole('region', { name: 'Compromiso 1' })).toBeInTheDocument()

    await usuario.click(screen.getByRole('button', { name: 'Continuar' }))

    expect(await screen.findByText('Elige el elemento.')).toBeInTheDocument()
    expect(screen.getAllByText('La descripción es obligatoria.').length).toBeGreaterThan(0)
  })

  it('en Experiencia y Meta pide descripción en lugar del elemento', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerPrecarga).mockResolvedValue(precarga({ compromisos: [], origen: null }))
    renderAsistente('/pdi/nuevo?colaborador=10&tipo=Mejora')

    await usuario.type(await screen.findByLabelText('Objetivo del plan'), 'Objetivo')
    await usuario.click(await screen.findByRole('button', { name: 'Continuar' }))
    await usuario.click(await screen.findByRole('button', { name: 'Agregar compromiso' }))
    await usuario.selectOptions(await screen.findByLabelText('Dimensión'), 'Meta')

    expect(screen.queryByLabelText('Competencia o conocimiento')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Descripción')).toBeInTheDocument()
    expect(screen.queryByLabelText('Nivel actual')).not.toBeInTheDocument()
    expect(within(screen.getByLabelText('Dimensión')).queryByRole('option', { name: 'Conocimiento' })).not.toBeInTheDocument()
  })

  it('«Vincular a Plan de Formación» solo aparece en Curso, Mentoring y Codesarrollo', async () => {
    const usuario = userEvent.setup()
    renderAsistente('/pdi/nuevo?colaborador=10&tipo=Mejora')

    await llegarAlPaso2(usuario)
    expect(await screen.findByRole('button', { name: 'Vincular a Plan de Formación' })).toBeInTheDocument()
    await usuario.selectOptions(screen.getByLabelText('Tipo de acción'), 'Coaching')
    expect(screen.queryByRole('button', { name: 'Vincular a Plan de Formación' })).not.toBeInTheDocument()
    await usuario.selectOptions(screen.getByLabelText('Tipo de acción'), 'Mentoring')
    expect(screen.getByRole('button', { name: 'Vincular a Plan de Formación' })).toBeInTheDocument()
  })

  it('elegir una acción vinculable la asocia a la acción del compromiso', async () => {
    const usuario = userEvent.setup()
    vi.mocked(listarAccionesVinculables).mockResolvedValue([
      { plan_accion_id: 55, plan_id: 3, plan_codigo: 'PF-0001', plan_nombre: 'Plan 2026', plan_estado: 'Ejecutando', nombre: 'Taller de liderazgo', tipo: 'Presencial', fecha_inicio: '2026-08-01', fecha_fin: '2026-08-10', formador: null },
    ])
    renderAsistente('/pdi/nuevo?colaborador=10&tipo=Mejora')

    await llegarAlPaso2(usuario)
    await usuario.click(await screen.findByRole('button', { name: 'Vincular a Plan de Formación' }))
    const dialogo = await screen.findByRole('dialog')
    await usuario.click(await within(dialogo).findByRole('button', { name: /Vincular: Taller de liderazgo/ }))

    expect(await screen.findByText('Vinculada a: Taller de liderazgo')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Desvincular' })).toBeInTheDocument()
  })

  it('un error 422 del servidor se muestra y lleva al paso del campo', async () => {
    const usuario = userEvent.setup()
    vi.mocked(crearPdi).mockRejectedValue({ isAxiosError: true, response: { status: 422, data: { message: 'Revisa los datos del plan.', errors: { objetivo: ['El objetivo del plan es obligatorio.'] } } } })
    renderAsistente('/pdi/nuevo?colaborador=10&tipo=Mejora')

    await llegarAlPaso2(usuario)
    await usuario.type(await screen.findByLabelText('Descripción de la acción'), 'Curso')
    await usuario.click(screen.getByRole('button', { name: 'Continuar' }))
    await usuario.click(await screen.findByRole('button', { name: 'Crear PDI (Borrador)' }))

    expect(await screen.findByText('El objetivo del plan es obligatorio.')).toBeInTheDocument()
  })

  it('editar un Borrador carga sus datos, bloquea colaborador y tipo y envía la versión', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerPdi).mockResolvedValue(pdi({ id: 5, version: 4 }))
    renderAsistente('/pdi/5/editar')

    expect(await screen.findByLabelText('Objetivo del plan')).toHaveValue('Cerrar las brechas de comunicación.')
    expect(screen.getByLabelText('Tipo de PDI')).toBeDisabled()
    await usuario.click(screen.getByRole('button', { name: 'Continuar' }))
    await usuario.click(await screen.findByRole('button', { name: 'Continuar' }))
    await usuario.click(await screen.findByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(actualizarPdi).toHaveBeenCalledWith(5, expect.objectContaining({ colaborador_id: 10, tipo: 'Mejora' }), 4))
  })

  it('un plan que no está en Borrador no se edita', async () => {
    vi.mocked(obtenerPdi).mockResolvedValue(pdi({ estado: 'Acordado', acciones_permitidas: { ...pdi().acciones_permitidas, editar: false } }))
    renderAsistente('/pdi/5/editar')

    expect(await screen.findByText('Solo se puede editar un PDI en Borrador.')).toBeInTheDocument()
  })
})
