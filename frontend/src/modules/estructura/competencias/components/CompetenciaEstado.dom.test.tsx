// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { competencia, detalle, escalas, pagina } from '../../../../test/competenciasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { actualizarPreguntas, cambiarEstadoCompetencia, crearCompetencia, listarCompetencias, listarEscalas, obtenerCompetencia } from '../api'
import { CompetenciasPage } from '../pages/CompetenciasPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarCompetencias: vi.fn(),
  obtenerCompetencia: vi.fn(),
  listarEscalas: vi.fn(),
  cambiarEstadoCompetencia: vi.fn(),
  crearCompetencia: vi.fn(),
  actualizarPreguntas: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function renderPage() {
  return renderRoutes(<Route path="/competencias" element={<CompetenciasPage />} />, '/competencias')
}

const activa = competencia({ id: 1, nombre: 'Liderazgo' })
const inactiva = competencia({ id: 2, codigo: 'COMP-0002', nombre: 'Adaptabilidad', estado: 'Inactivo' })

beforeEach(() => {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role: 'admin' } } as ReturnType<typeof useCurrentUser>)
  vi.mocked(listarCompetencias).mockReset().mockResolvedValue(pagina([activa, inactiva]))
  vi.mocked(obtenerCompetencia).mockReset().mockResolvedValue(detalle())
  vi.mocked(listarEscalas).mockReset().mockResolvedValue(escalas())
  vi.mocked(cambiarEstadoCompetencia).mockReset().mockResolvedValue(detalle())
  vi.mocked(crearCompetencia).mockReset()
  vi.mocked(actualizarPreguntas).mockReset().mockResolvedValue(detalle())
})

describe('Activar y desactivar una competencia', () => {
  it('Desactivar pide confirmación con la consecuencia y Cancelar no cambia nada', async () => {
    const usuario = userEvent.setup()
    renderPage()

    await usuario.click(await screen.findByRole('button', { name: 'Desactivar Liderazgo' }))

    const dialogo = screen.getByRole('alertdialog', { name: '¿Desactivar «Liderazgo»?' })
    expect(within(dialogo).getByText('La competencia, con sus grados, comportamientos y preguntas, no podrá asignarse en registros nuevos.')).toBeInTheDocument()
    await usuario.click(within(dialogo).getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(cambiarEstadoCompetencia).not.toHaveBeenCalled()
  })

  it('al confirmar desactiva y avisa "Competencia desactivada."', async () => {
    const usuario = userEvent.setup()
    renderPage()

    await usuario.click(await screen.findByRole('button', { name: 'Desactivar Liderazgo' }))
    await usuario.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))

    await waitFor(() => expect(cambiarEstadoCompetencia).toHaveBeenCalledWith(1, 'Inactivo'))
    expect(await screen.findByRole('alertdialog', { name: 'Competencia desactivada.' })).toBeInTheDocument()
  })

  it('Activar actúa de inmediato, sin confirmación, y avisa "Competencia activada."', async () => {
    const usuario = userEvent.setup()
    renderPage()

    await usuario.click(await screen.findByRole('button', { name: 'Activar Adaptabilidad' }))

    await waitFor(() => expect(cambiarEstadoCompetencia).toHaveBeenCalledWith(2, 'Activo'))
    expect(await screen.findByRole('alertdialog', { name: 'Competencia activada.' })).toBeInTheDocument()
  })

  it('el aviso se cierra con Aceptar y con Escape, y Aceptar tiene el foco inicial', async () => {
    const usuario = userEvent.setup()
    renderPage()
    await usuario.click(await screen.findByRole('button', { name: 'Activar Adaptabilidad' }))

    const aviso = await screen.findByRole('alertdialog', { name: 'Competencia activada.' })
    expect(within(aviso).getByRole('button', { name: 'Aceptar' })).toHaveFocus()
    await usuario.click(within(aviso).getByRole('button', { name: 'Aceptar' }))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()

    await usuario.click(screen.getByRole('button', { name: 'Activar Adaptabilidad' }))
    await screen.findByRole('alertdialog', { name: 'Competencia activada.' })
    await usuario.keyboard('{Escape}')
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('al activar una competencia incompleta muestra lo que falta que indica el servidor', async () => {
    const usuario = userEvent.setup()
    vi.mocked(cambiarEstadoCompetencia).mockRejectedValue(
      httpError(422, { message: 'x', errors: { estado: ['No se puede activar: faltan los comportamientos y las preguntas.'] } }),
    )
    renderPage()

    await usuario.click(await screen.findByRole('button', { name: 'Activar Adaptabilidad' }))

    expect(await screen.findByText('No se puede activar: faltan los comportamientos y las preguntas.')).toBeInTheDocument()
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('si no se puede desactivar, el diálogo sigue abierto con el error', async () => {
    const usuario = userEvent.setup()
    vi.mocked(cambiarEstadoCompetencia).mockRejectedValue(httpError(500))
    renderPage()

    await usuario.click(await screen.findByRole('button', { name: 'Desactivar Liderazgo' }))
    await usuario.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Desactivar' }))

    expect(await screen.findByText('No se pudo desactivar la competencia. Inténtalo de nuevo.')).toBeInTheDocument()
    expect(screen.getByRole('alertdialog', { name: '¿Desactivar «Liderazgo»?' })).toBeInTheDocument()
  })
})

describe('Avisos de crear y editar', () => {
  it('tras crear la competencia muestra "Competencia creada correctamente."', async () => {
    const usuario = userEvent.setup()
    vi.mocked(crearCompetencia).mockResolvedValue(detalle())
    renderPage()
    await usuario.click(await screen.findByRole('button', { name: 'Nueva competencia' }))
    await screen.findByLabelText('Nombre *')

    const llenar = async (etiqueta: string, texto: string) => {
      await usuario.click(screen.getByLabelText(etiqueta))
      await usuario.paste(texto)
    }
    await llenar('Nombre *', 'Innovación')
    await usuario.selectOptions(screen.getByLabelText('Tipo *'), 'Cardinal')
    await llenar('Descripción *', 'Generar ideas.')
    for (const grado of ['A', 'B', 'C', 'D']) {
      await llenar(`Grado ${grado}`, `desc ${grado}`)
      for (const numero of [1, 2, 3, 4]) await llenar(`Grado ${grado} · Comportamiento ${numero}`, `c${numero}`)
    }
    for (const numero of [1, 2, 3, 4]) await llenar(`Pregunta ${numero}`, `p${numero}`)
    await usuario.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByRole('alertdialog', { name: 'Competencia creada correctamente.' })).toBeInTheDocument()
    expect(screen.queryByRole('dialog', { name: 'Nueva competencia' })).not.toBeInTheDocument()
  })

  it('tras editar las preguntas muestra "Preguntas actualizadas correctamente."', async () => {
    const usuario = userEvent.setup()
    renderPage()

    await usuario.click(await screen.findByRole('button', { name: 'Ver información adicional de Liderazgo' }))
    await usuario.click(await screen.findByRole('tab', { name: 'Preguntas' }))
    await usuario.click(screen.getByRole('button', { name: 'Editar preguntas de Liderazgo' }))
    await screen.findByLabelText('Pregunta 1')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByRole('alertdialog', { name: 'Preguntas actualizadas correctamente.' })).toBeInTheDocument()
    expect(actualizarPreguntas).toHaveBeenCalledTimes(1)
  })

  it('el usuario sin rol de administrador nunca ve los formularios ni los botones de gestión', async () => {
    vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 2, name: 'Luis', email: 'l@hrms.test', role: 'user' } } as ReturnType<typeof useCurrentUser>)
    renderPage()
    await screen.findByText('Liderazgo')

    expect(screen.queryByRole('button', { name: 'Nueva competencia' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Desactivar|^Activar/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})
