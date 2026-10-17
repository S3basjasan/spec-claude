// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { accionDisponible, detalle, opciones } from '../../../../test/actividadesFixtures'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarActividad, crearActividad, listarAccionesDisponibles, obtenerActividad, obtenerOpciones } from '../api'
import { FormularioActividadModal } from './FormularioActividadModal'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarAccionesDisponibles: vi.fn(),
  obtenerOpciones: vi.fn(),
  obtenerActividad: vi.fn(),
  crearActividad: vi.fn(),
  actualizarActividad: vi.fn(),
}))

const meta = { page: 1, per_page: 50, total: 2, last_page: 1 }
const alGuardar = vi.fn()
const alCerrar = vi.fn()

function renderModal(actividadId: number | null = null) {
  return renderRoutes(<Route path="/" element={<FormularioActividadModal actividadId={actividadId} onCerrar={alCerrar} onGuardado={alGuardar} />} />, '/')
}

beforeEach(() => {
  alGuardar.mockReset()
  alCerrar.mockReset()
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opciones())
  vi.mocked(listarAccionesDisponibles)
    .mockReset()
    .mockResolvedValue({ data: [accionDisponible(), accionDisponible({ accion_id: 32, nombre: 'Gestión del tiempo', tipo: 'Virtual' })], meta })
  vi.mocked(obtenerActividad).mockReset().mockResolvedValue(detalle())
  vi.mocked(crearActividad).mockReset().mockResolvedValue(detalle({ id: 9, codigo: 'ACT-0009' }))
  vi.mocked(actualizarActividad).mockReset().mockResolvedValue(detalle({ lugar_enlace: 'Auditorio' }))
})

async function elegirAccion(user: ReturnType<typeof userEvent.setup>, nombre: string) {
  await user.click(await screen.findByRole('combobox', { name: 'Acción del plan' }))
  await user.click(await screen.findByRole('option', { name: new RegExp(nombre) }))
}

describe('FormularioActividadModal — crear', () => {
  it('lista las acciones disponibles y no muestra campos editables hasta elegir una', async () => {
    renderModal()

    const dialogo = await screen.findByRole('dialog', { name: 'Nueva actividad formativa' })
    expect(await within(dialogo).findByRole('combobox', { name: 'Acción del plan' })).toBeInTheDocument()
    expect(within(dialogo).queryByLabelText('Lugar')).not.toBeInTheDocument()
    expect(listarAccionesDisponibles).toHaveBeenCalledWith({ plan_id: undefined, per_page: 50 })
  })

  it('al elegir la acción muestra sus datos heredados en solo lectura', async () => {
    renderModal()
    const user = userEvent.setup()

    await elegirAccion(user, 'Excel avanzado')

    const heredados = screen.getByRole('region', { name: 'Datos de la acción (solo lectura)' })
    expect(heredados).toHaveTextContent('Excel avanzado')
    expect(heredados).toHaveTextContent('PLAN-0004 · Plan de desarrollo 2026')
    expect(heredados).toHaveTextContent('Análisis de datos')
    expect(heredados).toHaveTextContent('6–10 abr 2026')
    expect(heredados).toHaveTextContent('Mg. Andrés Molina')
    expect(heredados).toHaveTextContent('15')
    expect(within(heredados).queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Lugar')).toBeInTheDocument()
  })

  it('para acciones Virtual pide un enlace y valida que sea una URL', async () => {
    renderModal()
    const user = userEvent.setup()

    await elegirAccion(user, 'Gestión del tiempo')
    await user.type(screen.getByLabelText('Enlace'), 'sala 3')
    await user.click(screen.getByRole('button', { name: 'Crear actividad' }))

    expect(await screen.findByText('Ingresa una URL válida que empiece por http:// o https://.')).toBeInTheDocument()
    expect(crearActividad).not.toHaveBeenCalled()
  })

  it('exige elegir la acción y el lugar, con el error en cada campo', async () => {
    renderModal()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Crear actividad' }))

    expect(await screen.findByText('Elige la acción del plan.')).toBeInTheDocument()
    expect(crearActividad).not.toHaveBeenCalled()
  })

  it('exige el lugar o enlace una vez elegida la acción', async () => {
    renderModal()
    const user = userEvent.setup()

    await elegirAccion(user, 'Excel avanzado')
    await user.click(screen.getByRole('button', { name: 'Crear actividad' }))

    expect(await screen.findByText('El lugar o enlace es obligatorio.')).toBeInTheDocument()
  })

  it('crea la actividad con la acción, el lugar y el departamento', async () => {
    renderModal()
    const user = userEvent.setup()

    await elegirAccion(user, 'Excel avanzado')
    await user.type(screen.getByLabelText('Lugar'), 'Sala 3')
    await user.selectOptions(screen.getByLabelText(/Departamento/), 'Talento Humano')
    await user.click(screen.getByRole('button', { name: 'Crear actividad' }))

    await waitFor(() => expect(crearActividad).toHaveBeenCalledWith({ plan_accion_id: 31, lugar_enlace: 'Sala 3', departamento_id: 2 }))
    await waitFor(() => expect(alGuardar).toHaveBeenCalledWith(expect.objectContaining({ codigo: 'ACT-0009' }), true))
  })

  it('muestra el error del servidor en el campo y conserva los valores', async () => {
    vi.mocked(crearActividad).mockRejectedValue(httpError(422, { message: 'Inválido', code: 'lugar_invalido', errors: { lugar_enlace: ['El lugar o enlace no es válido.'] } }))
    renderModal()
    const user = userEvent.setup()

    await elegirAccion(user, 'Excel avanzado')
    await user.type(screen.getByLabelText('Lugar'), 'Sala 3')
    await user.click(screen.getByRole('button', { name: 'Crear actividad' }))

    expect(await screen.findByText('El lugar o enlace no es válido.')).toBeInTheDocument()
    expect(screen.getByLabelText('Lugar')).toHaveValue('Sala 3')
    expect(alGuardar).not.toHaveBeenCalled()
  })

  it('si la acción dejó de ser elegible lo muestra en el selector', async () => {
    vi.mocked(crearActividad).mockRejectedValue(httpError(422, { message: 'La acción no admite una actividad.', code: 'accion_no_elegible', errors: { plan_accion_id: ['La acción no admite una actividad.'] } }))
    renderModal()
    const user = userEvent.setup()

    await elegirAccion(user, 'Excel avanzado')
    await user.type(screen.getByLabelText('Lugar'), 'Sala 3')
    await user.click(screen.getByRole('button', { name: 'Crear actividad' }))

    expect(await screen.findByText('La acción no admite una actividad.')).toBeInTheDocument()
  })

  it('sin acciones disponibles explica por qué', async () => {
    vi.mocked(listarAccionesDisponibles).mockResolvedValue({ data: [], meta: { ...meta, total: 0 } })
    renderModal()

    expect(await screen.findAllByText(/No hay acciones disponibles/)).not.toHaveLength(0)
  })

  it('filtra las acciones por plan', async () => {
    renderModal()
    const user = userEvent.setup()

    await user.selectOptions(await screen.findByLabelText('Filtrar por plan'), 'PLAN-0004 · Plan de desarrollo 2026')

    await waitFor(() => expect(listarAccionesDisponibles).toHaveBeenLastCalledWith({ plan_id: 4, per_page: 50 }))
  })

  it('Cancelar cierra el modal', async () => {
    renderModal()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Cancelar' }))

    expect(alCerrar).toHaveBeenCalled()
  })
})

describe('FormularioActividadModal — editar', () => {
  it('parte de los valores guardados, deja la acción fija y guarda lugar y departamento', async () => {
    renderModal(1)
    const user = userEvent.setup()

    const lugar = await screen.findByLabelText('Lugar')
    expect(lugar).toHaveValue('Sala de capacitación, piso 3')
    expect(screen.queryByRole('combobox', { name: 'Acción del plan' })).not.toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Datos de la acción (solo lectura)' })).toHaveTextContent('Liderazgo situacional')

    await user.clear(lugar)
    await user.type(lugar, 'Auditorio')
    await user.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(actualizarActividad).toHaveBeenCalledWith(1, { lugar_enlace: 'Auditorio', departamento_id: 2 }))
    expect(alGuardar).toHaveBeenCalledWith(expect.anything(), false)
  })

  it('con el plan Cerrado queda de solo lectura', async () => {
    vi.mocked(obtenerActividad).mockResolvedValue(detalle({ puede_editar: false }))
    renderModal(1)

    expect(await screen.findByText(/El plan está cerrado/)).toBeInTheDocument()
    expect(screen.getByLabelText('Lugar')).toBeDisabled()
    expect(screen.queryByRole('button', { name: 'Guardar cambios' })).not.toBeInTheDocument()
  })

  it('conserva el departamento actual aunque ya no esté activo', async () => {
    vi.mocked(obtenerActividad).mockResolvedValue(detalle({ departamento: { id: 9, nombre: 'Unidad antigua' } }))
    renderModal(1)

    const select = await screen.findByLabelText(/Departamento/)
    expect(within(select).getByRole('option', { name: 'Unidad antigua' })).toBeInTheDocument()
    expect(select).toHaveValue('9')
  })

  it('si falla la carga ofrece reintentar', async () => {
    vi.mocked(obtenerActividad).mockRejectedValueOnce(httpError(500)).mockResolvedValue(detalle())
    renderModal(1)
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByLabelText('Lugar')).toBeInTheDocument()
  })
})
