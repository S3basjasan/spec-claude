// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { detalle } from '../../../../test/competenciasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { crearCompetencia } from '../api'
import { CompetenciaFormPage } from './CompetenciaFormPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  crearCompetencia: vi.fn(),
  actualizarCompetencia: vi.fn(),
  obtenerCompetencia: vi.fn(),
}))

function renderForm() {
  return renderRoutes(
    <>
      <Route path="/competencias/nueva" element={<CompetenciaFormPage />} />
      <Route path="/competencias" element={<p>Listado</p>} />
    </>,
    '/competencias/nueva',
  )
}

async function completar(user: ReturnType<typeof userEvent.setup>, nombre = 'Innovación') {
  await user.type(screen.getByLabelText('Nombre'), nombre)
  await user.type(screen.getByLabelText('Descripción', { exact: true }), 'Generar ideas nuevas.')
  await user.selectOptions(screen.getByLabelText('Tipo'), 'Cardinal')
  for (const letra of ['A', 'B', 'C', 'D']) {
    await user.type(screen.getByLabelText(`Grado ${letra}`), `Texto del grado ${letra}`)
  }
}

beforeEach(() => {
  vi.mocked(crearCompetencia).mockReset()
})

describe('CompetenciaFormPage (crear)', () => {
  it('muestra el formulario con los cuatro grados y sin código', () => {
    renderForm()

    expect(screen.getByRole('heading', { name: 'Nueva competencia' })).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'Grados de desarrollo' })).toBeInTheDocument()
    expect(screen.queryByLabelText('Código')).not.toBeInTheDocument()
    expect(screen.getAllByRole('textbox').length).toBeGreaterThanOrEqual(6)
  })

  it('muestra errores por campo y un aviso general sin llamar al servidor', async () => {
    renderForm()

    await userEvent.setup().click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('Revisa los campos marcados antes de guardar.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa el nombre de la competencia.')).toBeInTheDocument()
    expect(screen.getByText('Selecciona el tipo de competencia.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa la descripción del grado C.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveAttribute('aria-invalid', 'true')
    expect(crearCompetencia).not.toHaveBeenCalled()
  })

  it('crea la competencia con los datos recortados y vuelve al listado con aviso', async () => {
    vi.mocked(crearCompetencia).mockResolvedValue(detalle())
    renderForm()
    const user = userEvent.setup()

    await completar(user, '  Innovación ')
    await user.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('Listado')).toBeInTheDocument()
    expect(vi.mocked(crearCompetencia).mock.calls[0][0]).toEqual({
      nombre: 'Innovación',
      descripcion: 'Generar ideas nuevas.',
      tipo: 'Cardinal',
      grados: { A: 'Texto del grado A', B: 'Texto del grado B', C: 'Texto del grado C', D: 'Texto del grado D' },
    })
  })

  it('mapea el 422 del servidor (nombre duplicado y grado) a cada campo y conserva los valores', async () => {
    vi.mocked(crearCompetencia).mockRejectedValue(
      httpError(422, { errors: { nombre: ['Ya existe una competencia con ese nombre.'], 'grados.B': ['Ingresa la descripción del grado B.'] } }),
    )
    renderForm()
    const user = userEvent.setup()

    await completar(user)
    await user.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('Ya existe una competencia con ese nombre.')).toBeInTheDocument()
    expect(screen.getAllByText('Ingresa la descripción del grado B.')).not.toHaveLength(0)
    expect(screen.getByLabelText('Nombre')).toHaveValue('Innovación')
    expect(screen.getByLabelText('Grado A')).toHaveValue('Texto del grado A')
  })

  it('bloquea el botón mientras guarda y no envía dos veces', async () => {
    vi.mocked(crearCompetencia).mockReturnValue(new Promise(() => {}))
    renderForm()
    const user = userEvent.setup()

    await completar(user)
    await user.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    await waitFor(() => expect(screen.getByRole('button', { name: 'Guardando…' })).toBeDisabled())
    expect(crearCompetencia).toHaveBeenCalledOnce()
  })

  it('ante un error de red muestra un aviso y conserva lo escrito', async () => {
    vi.mocked(crearCompetencia).mockRejectedValue(networkError())
    renderForm()
    const user = userEvent.setup()

    await completar(user)
    await user.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('No se pudo conectar con el servidor. Inténtalo de nuevo.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Innovación')
  })

  it('muestra el contador de caracteres y el máximo de 50 en el nombre', async () => {
    renderForm()

    await userEvent.setup().type(screen.getByLabelText('Nombre'), 'Liderazgo')

    expect(screen.getByText('9/50')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveAttribute('maxlength', '50')
  })
})
