// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { competenciaDisponible, detalle } from '../../../../test/preguntasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { crearPreguntas, listarCompetenciasDisponibles } from '../api'
import { RequireAdmin } from '../components/RequireAdmin'
import { PreguntasFormPage } from './PreguntasFormPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  crearPreguntas: vi.fn(),
  actualizarPreguntas: vi.fn(),
  obtenerPreguntas: vi.fn(),
  listarCompetenciasDisponibles: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderForm() {
  return renderRoutes(
    <>
      <Route element={<RequireAdmin />}>
        <Route path="/preguntas/nuevo" element={<PreguntasFormPage />} />
      </Route>
      <Route path="/preguntas" element={<p>Listado</p>} />
    </>,
    '/preguntas/nuevo',
  )
}

const disponibles = [
  competenciaDisponible(),
  competenciaDisponible({ competencia_id: 5, codigo: 'COMP-0005', nombre: 'Pensamiento analítico', tipo: 'Especifica', descripcion: 'Analizar datos.' }),
]

async function elegirCompetencia(user: ReturnType<typeof userEvent.setup>, nombre = 'Comunicación efectiva') {
  await user.click(await screen.findByRole('combobox', { name: 'Competencia' }))
  await user.click(screen.getByRole('option', { name: new RegExp(nombre) }))
}

async function completar(user: ReturnType<typeof userEvent.setup>) {
  for (const n of [1, 2, 3, 4]) {
    await user.type(screen.getByLabelText(`Pregunta ${n}`), `Texto ${n}`)
  }
}

beforeEach(() => {
  vi.mocked(crearPreguntas).mockReset()
  vi.mocked(listarCompetenciasDisponibles).mockReset().mockResolvedValue(disponibles)
  comoRol('admin')
})

describe('PreguntasFormPage (crear)', () => {
  it('el selector solo ofrece las competencias disponibles y permite buscar', async () => {
    renderForm()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('combobox', { name: 'Competencia' }))
    expect(screen.getAllByRole('option')).toHaveLength(2)

    await user.type(screen.getByRole('combobox'), 'pensam')
    expect(screen.getAllByRole('option')).toHaveLength(1)
  })

  it('al elegir la competencia muestra nombre, descripción y tipo en solo lectura', async () => {
    renderForm()

    await elegirCompetencia(userEvent.setup(), 'Pensamiento analítico')

    const resumen = within(screen.getByLabelText('Competencia', { selector: 'dl' }))
    expect(resumen.getByText('Pensamiento analítico')).toBeInTheDocument()
    expect(resumen.getByText('Analizar datos.')).toBeInTheDocument()
    expect(resumen.getByText('Específica')).toBeInTheDocument()
  })

  it('muestra cuatro campos numerados fijos, con contador y sin agregar ni quitar', async () => {
    renderForm()
    await screen.findByRole('combobox', { name: 'Competencia' })

    expect(screen.getByRole('group', { name: 'Preguntas' })).toBeInTheDocument()
    for (const n of [1, 2, 3, 4]) expect(screen.getByLabelText(`Pregunta ${n}`)).toBeInTheDocument()
    expect(screen.queryByLabelText('Pregunta 5')).not.toBeInTheDocument()
    expect(screen.getAllByText('0/500')).toHaveLength(4)
    expect(screen.queryByRole('button', { name: /agregar|quitar/i })).not.toBeInTheDocument()

    await userEvent.setup().type(screen.getByLabelText('Pregunta 2'), 'abc')
    expect(screen.getByText('3/500')).toBeInTheDocument()
  })

  it('muestra errores por campo y un aviso general sin llamar al servidor', async () => {
    renderForm()
    const user = userEvent.setup()
    await screen.findByRole('combobox', { name: 'Competencia' })

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Revisa los campos marcados antes de guardar.')).toBeInTheDocument()
    expect(screen.getByText('Selecciona una competencia.')).toBeInTheDocument()
    expect(screen.getAllByText('Ingresa la pregunta.')).toHaveLength(4)
    expect(screen.getByLabelText('Pregunta 1')).toHaveAttribute('aria-invalid', 'true')
    expect(crearPreguntas).not.toHaveBeenCalled()
  })

  it('rechaza una pregunta repetida en el registro', async () => {
    renderForm()
    const user = userEvent.setup()
    await elegirCompetencia(user)
    await completar(user)
    await user.clear(screen.getByLabelText('Pregunta 2'))
    await user.type(screen.getByLabelText('Pregunta 2'), 'texto 1')

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Esta pregunta ya está en el registro.')).toBeInTheDocument()
    expect(crearPreguntas).not.toHaveBeenCalled()
  })

  it('crea el registro con los textos recortados y vuelve al listado', async () => {
    vi.mocked(crearPreguntas).mockResolvedValue(detalle({ competencia_id: 3 }))
    renderForm()
    const user = userEvent.setup()

    await elegirCompetencia(user)
    await user.type(screen.getByLabelText('Pregunta 1'), '  Texto 1 ')
    for (const n of [2, 3, 4]) await user.type(screen.getByLabelText(`Pregunta ${n}`), `Texto ${n}`)
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Listado')).toBeInTheDocument()
    expect(crearPreguntas).toHaveBeenCalledWith(3, ['Texto 1', 'Texto 2', 'Texto 3', 'Texto 4'])
  })

  it('bloquea el botón mientras guarda y no envía dos veces', async () => {
    let resolver: (valor: ReturnType<typeof detalle>) => void = () => {}
    vi.mocked(crearPreguntas).mockReturnValue(new Promise((resolve) => (resolver = resolve)))
    renderForm()
    const user = userEvent.setup()
    await elegirCompetencia(user)
    await completar(user)

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByRole('button', { name: 'Guardando…' })).toBeDisabled()
    resolver(detalle())
    await screen.findByText('Listado')
    expect(crearPreguntas).toHaveBeenCalledTimes(1)
  })

  it('mapea el 422 del servidor a cada campo y conserva los valores', async () => {
    vi.mocked(crearPreguntas).mockRejectedValue(httpError(422, { errors: { 'preguntas.1': ['Texto repetido.'] } }))
    renderForm()
    const user = userEvent.setup()
    await elegirCompetencia(user)
    await completar(user)

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Texto repetido.')).toBeInTheDocument()
    expect(screen.getByLabelText('Pregunta 1')).toHaveValue('Texto 1')
  })

  it('con 422 por competencia no disponible lo muestra en el selector, conserva las preguntas y vuelve a pedir las disponibles', async () => {
    vi.mocked(crearPreguntas).mockRejectedValue(
      httpError(422, { errors: { competencia_id: ['La competencia no existe o no está activa.'] } }),
    )
    renderForm()
    const user = userEvent.setup()
    await elegirCompetencia(user)
    await completar(user)
    expect(listarCompetenciasDisponibles).toHaveBeenCalledTimes(1)

    vi.mocked(listarCompetenciasDisponibles).mockResolvedValue([disponibles[1]])
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('La competencia no existe o no está activa.')).toBeInTheDocument()
    expect(screen.getByLabelText('Pregunta 1')).toHaveValue('Texto 1')
    await waitFor(() => expect(listarCompetenciasDisponibles).toHaveBeenCalledTimes(2))
    await user.click(screen.getByRole('combobox', { name: 'Competencia' }))
    expect(screen.getAllByRole('option')).toHaveLength(1)
  })

  it('muestra un aviso cuando falla la conexión', async () => {
    vi.mocked(crearPreguntas).mockRejectedValue(new Error('x'))
    renderForm()
    const user = userEvent.setup()
    await elegirCompetencia(user)
    await completar(user)

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('No se pudo conectar con el servidor. Inténtalo de nuevo.')).toBeInTheDocument()
  })

  it('sin competencias disponibles lo indica y no muestra el formulario', async () => {
    vi.mocked(listarCompetenciasDisponibles).mockResolvedValue([])
    renderForm()

    expect(await screen.findByText('No hay competencias disponibles')).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })

  it('el Usuario no accede: ve el aviso de permisos', async () => {
    comoRol('user')
    renderForm()

    await waitFor(() => expect(screen.getByText('No tienes permisos para esta acción')).toBeInTheDocument())
    expect(listarCompetenciasDisponibles).not.toHaveBeenCalled()
  })
})
