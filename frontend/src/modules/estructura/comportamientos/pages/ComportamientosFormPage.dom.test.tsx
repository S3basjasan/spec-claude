// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { competenciaDisponible, detalle } from '../../../../test/comportamientosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { crearComportamientos, listarCompetenciasDisponibles } from '../api'
import { RequireAdmin } from '../components/RequireAdmin'
import { ComportamientosFormPage } from './ComportamientosFormPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  crearComportamientos: vi.fn(),
  actualizarComportamientos: vi.fn(),
  obtenerComportamientos: vi.fn(),
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
        <Route path="/comportamientos/nuevo" element={<ComportamientosFormPage />} />
      </Route>
      <Route path="/comportamientos" element={<p>Listado</p>} />
    </>,
    '/comportamientos/nuevo',
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
  for (const letra of ['A', 'B', 'C', 'D']) {
    await user.type(screen.getByLabelText(`Comportamiento 1 del grado ${letra}`), `Texto ${letra}`)
  }
}

beforeEach(() => {
  vi.mocked(crearComportamientos).mockReset()
  vi.mocked(listarCompetenciasDisponibles).mockReset().mockResolvedValue(disponibles)
  comoRol('admin')
})

describe('ComportamientosFormPage (crear)', () => {
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

  it('no deja pasar de 4 comportamientos por grado ni bajar de 1', async () => {
    renderForm()
    const user = userEvent.setup()
    await screen.findByRole('combobox', { name: 'Competencia' })
    const grupoA = within(screen.getByRole('group', { name: 'Grado A' }))

    for (let i = 0; i < 5; i++) {
      const agregar = grupoA.getByRole('button', { name: 'Agregar comportamiento' })
      if (!(agregar as HTMLButtonElement).disabled) await user.click(agregar)
    }
    expect(grupoA.getAllByRole('textbox')).toHaveLength(4)
    expect(grupoA.getByRole('button', { name: 'Agregar comportamiento' })).toBeDisabled()

    for (let i = 0; i < 4; i++) {
      const quitar = grupoA.getByRole('button', { name: 'Quitar comportamiento 1 del grado A' })
      if (!(quitar as HTMLButtonElement).disabled) await user.click(quitar)
    }
    expect(grupoA.getAllByRole('textbox')).toHaveLength(1)
  })

  it('muestra errores por campo y un aviso general sin llamar al servidor', async () => {
    renderForm()
    const user = userEvent.setup()
    await screen.findByRole('combobox', { name: 'Competencia' })

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Revisa los campos marcados antes de guardar.')).toBeInTheDocument()
    expect(screen.getByText('Selecciona una competencia.')).toBeInTheDocument()
    expect(screen.getAllByText('Ingresa el comportamiento.')).toHaveLength(4)
    expect(screen.getByLabelText('Comportamiento 1 del grado A')).toHaveAttribute('aria-invalid', 'true')
    expect(crearComportamientos).not.toHaveBeenCalled()
  })

  it('rechaza un texto repetido en el grado', async () => {
    renderForm()
    const user = userEvent.setup()
    await elegirCompetencia(user)
    await completar(user)
    await user.click(within(screen.getByRole('group', { name: 'Grado A' })).getByRole('button', { name: 'Agregar comportamiento' }))
    await user.type(screen.getByLabelText('Comportamiento 2 del grado A'), 'texto a')

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Este comportamiento ya está en el grado.')).toBeInTheDocument()
    expect(crearComportamientos).not.toHaveBeenCalled()
  })

  it('crea el registro con los textos recortados y vuelve al listado', async () => {
    vi.mocked(crearComportamientos).mockResolvedValue(detalle({ competencia_id: 3 }))
    renderForm()
    const user = userEvent.setup()

    await elegirCompetencia(user)
    await user.type(screen.getByLabelText('Comportamiento 1 del grado A'), '  Texto A ')
    for (const letra of ['B', 'C', 'D']) await user.type(screen.getByLabelText(`Comportamiento 1 del grado ${letra}`), `Texto ${letra}`)
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Listado')).toBeInTheDocument()
    expect(crearComportamientos).toHaveBeenCalledWith(3, { A: ['Texto A'], B: ['Texto B'], C: ['Texto C'], D: ['Texto D'] })
  })

  it('bloquea el botón mientras guarda y no envía dos veces', async () => {
    let resolver: (valor: ReturnType<typeof detalle>) => void = () => {}
    vi.mocked(crearComportamientos).mockReturnValue(new Promise((resolve) => (resolver = resolve)))
    renderForm()
    const user = userEvent.setup()
    await elegirCompetencia(user)
    await completar(user)

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    const boton = await screen.findByRole('button', { name: 'Guardando…' })
    expect(boton).toBeDisabled()
    resolver(detalle())
    await screen.findByText('Listado')
    expect(crearComportamientos).toHaveBeenCalledTimes(1)
  })

  it('mapea el 422 del servidor a cada campo y conserva los valores', async () => {
    vi.mocked(crearComportamientos).mockRejectedValue(
      httpError(422, { errors: { competencia_id: ['La competencia ya tiene un registro de comportamientos.'], 'comportamientos.B.0': ['Texto repetido.'] } }),
    )
    renderForm()
    const user = userEvent.setup()
    await elegirCompetencia(user)
    await completar(user)

    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('La competencia ya tiene un registro de comportamientos.')).toBeInTheDocument()
    expect(screen.getByText('Texto repetido.')).toBeInTheDocument()
    expect(screen.getByLabelText('Comportamiento 1 del grado A')).toHaveValue('Texto A')
  })

  it('muestra un aviso cuando falla la conexión', async () => {
    vi.mocked(crearComportamientos).mockRejectedValue(new Error('x'))
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
