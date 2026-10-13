// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { competenciaDisponible, detalle } from '../../../../test/puestosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { crearPuesto, listarCompetenciasDisponibles } from '../api'
import { RequireAdmin } from '../components/RequireAdmin'
import { PuestoFormPage } from './PuestoFormPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarCompetenciasDisponibles: vi.fn(),
  crearPuesto: vi.fn(),
  obtenerPuesto: vi.fn(),
  actualizarPuesto: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderForm(entrada = '/puestos/nuevo') {
  return renderRoutes(
    <>
      <Route element={<RequireAdmin />}>
        <Route path="/puestos/nuevo" element={<PuestoFormPage />} />
        <Route path="/puestos/:puestoId/editar" element={<PuestoFormPage />} />
      </Route>
      <Route path="/puestos" element={<p>Listado de puestos</p>} />
    </>,
    entrada,
  )
}

const disponibles = [
  competenciaDisponible({ competencia_id: 3, codigo: 'COMP-0003', nombre: 'Comunicación efectiva' }),
  competenciaDisponible({ competencia_id: 5, codigo: 'COMP-0005', nombre: 'Pensamiento analítico', tipo: 'Especifica' }),
]

beforeEach(() => {
  vi.mocked(listarCompetenciasDisponibles).mockReset().mockResolvedValue(disponibles)
  vi.mocked(crearPuesto).mockReset().mockResolvedValue(detalle({ id: 7, codigo: 'PU-007' }))
  comoRol('admin')
})

async function completarFormularioValido(user: ReturnType<typeof userEvent.setup>) {
  await user.type(await screen.findByLabelText('Nombre'), 'Analista de datos')
  await user.type(screen.getByLabelText('Descripción'), 'Convierte datos en reportes.')
  await user.type(screen.getByLabelText('Misión'), 'Apoyar las decisiones.')
  await user.type(screen.getByRole('textbox', { name: 'Descripción de la función 1' }), 'Recopilar datos.')
  await user.type(screen.getByRole('textbox', { name: 'Descripción del requisito 1' }), 'Título afín.')
  await user.type(screen.getByRole('textbox', { name: 'Descripción del requisito 2' }), '2 años.')
  await user.click(screen.getByRole('combobox', { name: 'Competencia 1' }))
  await user.click(await screen.findByRole('option', { name: /Comunicación efectiva/ }))
  await user.selectOptions(screen.getByRole('combobox', { name: 'Grado requerido' }), 'B')
}

describe('PuestoFormPage (crear)', () => {
  it('muestra las cuatro secciones y la indicación de que el código se asigna al guardar', async () => {
    renderForm()

    expect(await screen.findByRole('heading', { name: 'Nuevo puesto', level: 1 })).toBeInTheDocument()
    for (const seccion of ['Datos generales', 'Funciones', 'Requisitos', 'Competencias']) {
      expect(screen.getByRole('group', { name: seccion })).toBeInTheDocument()
    }
    expect(screen.getByLabelText('Código')).toHaveValue('Se asigna al guardar')
    expect(screen.getByLabelText('Código')).toHaveAttribute('readonly')
    expect(screen.getByRole('button', { name: 'Guardar puesto' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Cancelar' })).toHaveAttribute('href', '/puestos')
  })

  it('muestra contadores de caracteres en nombre, descripción y misión', async () => {
    renderForm()
    const user = userEvent.setup()

    await user.type(await screen.findByLabelText('Nombre'), 'Analista')
    await user.type(screen.getByLabelText('Descripción'), 'Hola')

    expect(screen.getByText('8/50')).toBeInTheDocument()
    expect(screen.getByText('4/200')).toBeInTheDocument()
    expect(screen.getByText('0/200')).toBeInTheDocument()
  })

  it('con el formulario incompleto muestra el aviso general y los errores junto a cada campo, sin enviar nada', async () => {
    renderForm()
    const user = userEvent.setup()
    await screen.findByLabelText('Nombre')

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    expect(await screen.findByText('Revisa los campos marcados antes de guardar.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa el nombre del puesto.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa la descripción del puesto.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa la misión del puesto.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa la descripción de la función.')).toBeInTheDocument()
    expect(screen.getByText('Elige una competencia.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveAttribute('aria-invalid', 'true')
    expect(crearPuesto).not.toHaveBeenCalled()
  })

  it('exige una función esencial y muestra el error de la sección', async () => {
    renderForm()
    const user = userEvent.setup()
    await completarFormularioValido(user)

    await user.selectOptions(screen.getByRole('combobox', { name: 'Tipo de la función 1' }), 'Complementaria')
    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    expect(await screen.findByText('Incluye al menos una función esencial.')).toBeInTheDocument()
    expect(crearPuesto).not.toHaveBeenCalled()
  })

  it('conserva los valores escritos cuando hay errores y al corregir uno', async () => {
    renderForm()
    const user = userEvent.setup()
    await user.type(await screen.findByLabelText('Nombre'), 'Analista de datos')
    await user.type(screen.getByLabelText('Misión'), 'Apoyar las decisiones.')

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))
    expect(await screen.findByText('Ingresa la descripción del puesto.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Analista de datos')

    await user.type(screen.getByLabelText('Descripción'), 'Convierte datos.')
    expect(screen.getByLabelText('Nombre')).toHaveValue('Analista de datos')
    expect(screen.getByLabelText('Misión')).toHaveValue('Apoyar las decisiones.')
  })

  it('con datos válidos envía el puesto, en el orden escrito, y vuelve al listado', async () => {
    renderForm()
    const user = userEvent.setup()
    await completarFormularioValido(user)

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    await waitFor(() => expect(crearPuesto).toHaveBeenCalledTimes(1))
    expect(vi.mocked(crearPuesto).mock.calls[0][0]).toEqual({
      nombre: 'Analista de datos',
      descripcion: 'Convierte datos en reportes.',
      mision: 'Apoyar las decisiones.',
      funciones: [{ descripcion: 'Recopilar datos.', tipo: 'Esencial' }],
      requisitos: [
        { tipo: 'Formacion', descripcion: 'Título afín.' },
        { tipo: 'Experiencia', descripcion: '2 años.' },
      ],
      competencias: [{ competencia_id: 3, grado_requerido: 'B' }],
    })
    expect(await screen.findByText('Listado de puestos')).toBeInTheDocument()
    expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/puestos$/)
  })

  it('bloquea el botón y evita envíos duplicados mientras guarda', async () => {
    vi.mocked(crearPuesto).mockReturnValue(new Promise(() => {}))
    renderForm()
    const user = userEvent.setup()
    await completarFormularioValido(user)

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    const boton = await screen.findByRole('button', { name: 'Guardando…' })
    expect(boton).toBeDisabled()
    await user.click(boton)
    expect(crearPuesto).toHaveBeenCalledTimes(1)
  })

  it('muestra junto a cada campo los errores 422 del servidor y conserva lo escrito', async () => {
    vi.mocked(crearPuesto).mockRejectedValue(
      httpError(422, {
        message: 'Ya existe un puesto con ese nombre.',
        errors: {
          nombre: ['Ya existe un puesto con ese nombre.'],
          'funciones.0.descripcion': ['Esta función está repetida.'],
          'competencias.0.grado_requerido': ['Ese grado no existe para la competencia.'],
        },
      }),
    )
    renderForm()
    const user = userEvent.setup()
    await completarFormularioValido(user)

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    expect(await screen.findByText('Ya existe un puesto con ese nombre.')).toBeInTheDocument()
    expect(screen.getByText('Esta función está repetida.')).toBeInTheDocument()
    expect(screen.getByText('Ese grado no existe para la competencia.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Analista de datos')
    expect(screen.getByRole('textbox', { name: 'Descripción de la función 1' })).toHaveValue('Recopilar datos.')
  })

  it('si la competencia ya no está disponible lo indica en su fila y vuelve a pedir las disponibles', async () => {
    vi.mocked(crearPuesto).mockRejectedValue(
      httpError(422, { errors: { 'competencias.0.competencia_id': ['La competencia no existe o no está activa.'] } }),
    )
    renderForm()
    const user = userEvent.setup()
    await completarFormularioValido(user)
    const llamadasIniciales = vi.mocked(listarCompetenciasDisponibles).mock.calls.length

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    expect(await screen.findByText('La competencia no existe o no está activa.')).toBeInTheDocument()
    await waitFor(() => expect(vi.mocked(listarCompetenciasDisponibles).mock.calls.length).toBeGreaterThan(llamadasIniciales))
  })

  it('ante un error de red o inesperado muestra un aviso general y conserva el formulario', async () => {
    vi.mocked(crearPuesto).mockRejectedValueOnce(networkError()).mockRejectedValueOnce(httpError(500))
    renderForm()
    const user = userEvent.setup()
    await completarFormularioValido(user)

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))
    expect(await screen.findByText('No se pudo conectar con el servidor. Inténtalo de nuevo.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))
    expect(await screen.findByText('No se pudo guardar el puesto. Inténtalo de nuevo.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Analista de datos')
  })

  it('sin competencias disponibles lo indica y no muestra el formulario', async () => {
    vi.mocked(listarCompetenciasDisponibles).mockResolvedValue([])
    renderForm()

    expect(await screen.findByRole('heading', { name: 'No hay competencias disponibles' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar puesto' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al listado' })).toHaveAttribute('href', '/puestos')
  })

  it('muestra un esqueleto mientras carga las competencias y un error con Reintentar si falla', async () => {
    vi.mocked(listarCompetenciasDisponibles).mockRejectedValueOnce(httpError(500)).mockResolvedValue(disponibles)
    renderForm()

    expect(await screen.findByText('No se pudo cargar el puesto')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('heading', { name: 'Nuevo puesto', level: 1 })).toBeInTheDocument()
  })

  it('al Usuario le niega el acceso al formulario', async () => {
    comoRol('user')
    renderForm()

    expect(await screen.findByRole('heading', { name: 'No tienes permisos para esta acción' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar puesto' })).not.toBeInTheDocument()
  })

  it('solo ofrece en el selector las competencias activas aún no elegidas', async () => {
    renderForm()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('combobox', { name: 'Competencia 1' }))
    const lista = within(await screen.findByRole('listbox', { name: 'Competencia 1' }))

    expect(lista.getAllByRole('option')).toHaveLength(2)
    expect(lista.getByRole('option', { name: /Pensamiento analítico/ })).toBeInTheDocument()
  })
})
