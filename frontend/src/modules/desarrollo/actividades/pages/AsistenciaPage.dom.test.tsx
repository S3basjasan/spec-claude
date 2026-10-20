// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle, participante } from '../../../../test/actividadesFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarHistorial, obtenerActividad, registrarAsistencia } from '../api'
import { AsistenciaPage } from './AsistenciaPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerActividad: vi.fn(),
  registrarAsistencia: vi.fn(),
  listarHistorial: vi.fn(),
}))

function renderPage() {
  return renderRoutes(<Route path="/actividades/:id/asistencia" element={<AsistenciaPage />} />, '/actividades/1/asistencia')
}

const control = async (nombre: string) => within(await screen.findByRole('group', { name: `Asistencia de ${nombre}` }))

beforeEach(() => {
  vi.mocked(obtenerActividad).mockReset().mockResolvedValue(detalle())
  vi.mocked(registrarAsistencia).mockReset().mockResolvedValue(detalle({ version: 2 }))
  vi.mocked(listarHistorial).mockReset().mockResolvedValue({ data: [], meta: { page: 1, per_page: 10, total: 0, last_page: 1 } })
})

describe('AsistenciaPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(obtenerActividad).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando actividades' })).toBeInTheDocument()
  })

  it('muestra la actividad, el resumen y los inscritos con asistencia Pendiente', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Asistencia — Liderazgo situacional' })).toBeInTheDocument()
    expect(screen.getByText('ACT-0001')).toBeInTheDocument()
    expect(screen.getByRole('list', { name: 'Resumen de asistencia' })).toHaveTextContent('Sin registrar: 3')
    expect(screen.getByRole('table', { name: 'Registro de asistencia' })).toBeInTheDocument()
    expect(screen.getByText('Ana Pérez')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Guardar' })).toBeDisabled()
  })

  it('«Marcar todos presentes» marca solo a los pendientes y Guardar los envía juntos con la versión', async () => {
    vi.mocked(obtenerActividad).mockResolvedValue(
      detalle({ participantes: [participante({ colaborador_id: 10, nombre: 'Ana Pérez' }), participante({ colaborador_id: 11, nombre: 'Luis Mora', asistencia: 'Ausente' }), participante({ colaborador_id: 12, nombre: 'Eva Ríos' })] }),
    )
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Marcar todos presentes' }))

    expect(screen.getByRole('list', { name: 'Resumen de asistencia' })).toHaveTextContent('Presentes: 2')
    expect(screen.getByRole('list', { name: 'Resumen de asistencia' })).toHaveTextContent('Ausentes: 1')
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    await waitFor(() =>
      expect(registrarAsistencia).toHaveBeenCalledWith(1, 1, [
        { colaborador_id: 10, asistencia: 'Presente', calificacion: null, observaciones: null },
        { colaborador_id: 12, asistencia: 'Presente', calificacion: null, observaciones: null },
      ]),
    )
    expect(await screen.findByText('Registro guardado.')).toBeInTheDocument()
  })

  it('envía calificación y observaciones del participante presente', async () => {
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Marcar todos presentes' }))
    await user.type(screen.getByRole('spinbutton', { name: 'Calificación de Ana Pérez' }), '5')
    await user.type(screen.getByRole('textbox', { name: 'Observaciones de Ana Pérez' }), 'Participó')
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(registrarAsistencia).toHaveBeenCalled())
    expect(vi.mocked(registrarAsistencia).mock.calls[0][2][0]).toEqual({ colaborador_id: 10, asistencia: 'Presente', calificacion: 5, observaciones: 'Participó' })
  })

  it('pide confirmación antes de guardar cambios sobre un registro existente', async () => {
    vi.mocked(obtenerActividad).mockResolvedValue(detalle({ participantes: [participante({ colaborador_id: 11, nombre: 'Luis Mora', asistencia: 'Presente', calificacion: 4 })] }))
    renderPage()
    const user = userEvent.setup()

    await user.click((await control('Luis Mora')).getByRole('button', { name: 'Ausente' }))
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    const dialogo = await screen.findByRole('dialog', { name: 'Confirma los cambios en registros existentes' })
    expect(dialogo).toHaveTextContent('Luis Mora: Presente (4) → Ausente')
    expect(registrarAsistencia).not.toHaveBeenCalled()

    await user.click(within(dialogo).getByRole('button', { name: 'Confirmar y guardar' }))
    await waitFor(() => expect(registrarAsistencia).toHaveBeenCalledWith(1, 1, [{ colaborador_id: 11, asistencia: 'Ausente', calificacion: null, observaciones: null }]))
  })

  it('cancelar la confirmación no guarda', async () => {
    vi.mocked(obtenerActividad).mockResolvedValue(detalle({ participantes: [participante({ colaborador_id: 11, nombre: 'Luis Mora', asistencia: 'Presente' })] }))
    renderPage()
    const user = userEvent.setup()

    await user.click((await control('Luis Mora')).getByRole('button', { name: 'Justificado' }))
    await user.click(screen.getByRole('button', { name: 'Guardar' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Volver' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(registrarAsistencia).not.toHaveBeenCalled()
  })

  it('bloquea Guardar mientras una calificación esté fuera de rango', async () => {
    renderPage()
    const user = userEvent.setup()

    await user.click((await control('Ana Pérez')).getByRole('button', { name: 'Presente' }))
    await user.type(screen.getByRole('spinbutton', { name: 'Calificación de Ana Pérez' }), '9')

    expect(screen.getByRole('button', { name: 'Guardar' })).toBeDisabled()
  })

  it.each([
    ['actividad_programada', 'La actividad aún no ha comenzado'],
    ['actividad_cancelada', 'La actividad está cancelada'],
    ['plan_cerrado', 'El plan está cerrado'],
  ] as const)('con la actividad bloqueada por %s la vista queda deshabilitada y lo explica', async (motivo, texto) => {
    vi.mocked(obtenerActividad).mockResolvedValue(detalle({ puede_registrar_asistencia: false, motivo_bloqueo: motivo }))
    renderPage()

    expect(await screen.findByText(new RegExp(texto))).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Marcar todos presentes' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Guardar' })).toBeDisabled()
    for (const boton of within(screen.getByRole('table')).getAllByRole('button')) expect(boton).toBeDisabled()
  })

  it('si otro administrador guardó antes muestra el conflicto y conserva lo escrito', async () => {
    vi.mocked(registrarAsistencia).mockRejectedValue(httpError(409, { message: 'Otro administrador modificó el registro', code: 'version_desactualizada' }))
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Marcar todos presentes' }))
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Otro administrador modificó el registro')
    expect(screen.getByRole('button', { name: 'Recargar' })).toBeInTheDocument()
    expect(screen.getByRole('list', { name: 'Resumen de asistencia' })).toHaveTextContent('Presentes: 3')
  })

  it('muestra el error del servidor al guardar', async () => {
    vi.mocked(registrarAsistencia).mockRejectedValue(httpError(422, { message: 'Los registros de asistencia no son válidos.', errors: { 'registros.0.calificacion': ['La calificación debe ser un entero de 1 a 5.'] } }))
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Marcar todos presentes' }))
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('La calificación debe ser un entero de 1 a 5.')
  })

  it('sin inscritos muestra un estado vacío', async () => {
    vi.mocked(obtenerActividad).mockResolvedValue(detalle({ participantes: [], inscritos: 0 }))
    renderPage()

    expect(await screen.findByText('No hay inscritos')).toBeInTheDocument()
  })

  it('si falla la carga ofrece reintentar', async () => {
    vi.mocked(obtenerActividad).mockRejectedValueOnce(httpError(500)).mockResolvedValue(detalle())
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Ana Pérez')).toBeInTheDocument()
  })

  it('abre el historial de registros', async () => {
    vi.mocked(listarHistorial).mockResolvedValue({
      data: [{ id: 1, fecha: '2026-03-05T10:00:00Z', usuario: 'Marta Ríos', colaborador_id: 10, participante: 'Ana Pérez', asistencia_anterior: null, asistencia_nueva: 'Presente', calificacion_anterior: null, calificacion_nueva: 4 }],
      meta: { page: 1, per_page: 10, total: 1, last_page: 1 },
    })
    renderPage()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Ver historial' }))

    const dialogo = await screen.findByRole('dialog', { name: 'Historial de registros' })
    expect(await within(dialogo).findByText('Marta Ríos')).toBeInTheDocument()
    expect(dialogo).toHaveTextContent('Pendiente → Presente (4)')
  })
})
