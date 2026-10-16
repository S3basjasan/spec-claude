// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { desarrollo, experiencia, formacion, movilidad } from '../../../../test/desarrolloFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarMovilidad, corregirFormacion, crearExperiencia, crearFormacion, obtenerDesarrollo } from '../api'
import { SeccionDesarrollo } from './SeccionDesarrollo'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerDesarrollo: vi.fn(),
  crearFormacion: vi.fn(),
  corregirFormacion: vi.fn(),
  crearExperiencia: vi.fn(),
  corregirExperiencia: vi.fn(),
  actualizarMovilidad: vi.fn(),
}))

function renderSeccion(colaboradorId: number | null = 1) {
  return renderRoutes(<Route path="/" element={<SeccionDesarrollo colaboradorId={colaboradorId} />} />, '/')
}

beforeEach(() => {
  vi.mocked(obtenerDesarrollo).mockReset().mockResolvedValue(desarrollo())
  vi.mocked(crearFormacion).mockReset().mockResolvedValue(formacion())
  vi.mocked(corregirFormacion).mockReset().mockResolvedValue(formacion({ version: 2 }))
  vi.mocked(crearExperiencia).mockReset().mockResolvedValue(experiencia())
  vi.mocked(actualizarMovilidad).mockReset().mockResolvedValue(movilidad({ estado: 'Disponible', version: 2 }))
})

async function completarFormacion(user: ReturnType<typeof userEvent.setup>) {
  await user.type(await screen.findByLabelText('Nivel'), 'Tercer nivel')
  await user.selectOptions(screen.getByLabelText('Estado'), 'Completada')
  await user.type(screen.getByLabelText('Título o programa'), 'Ingeniería')
  await user.type(screen.getByLabelText('Institución'), 'EPN')
}

describe('SeccionDesarrollo', () => {
  it('muestra el estado de carga', () => {
    vi.mocked(obtenerDesarrollo).mockReturnValue(new Promise(() => {}))
    renderSeccion()

    expect(screen.getByRole('status', { name: 'Cargando información de desarrollo…' })).toBeInTheDocument()
  })

  it('compone los tres bloques vacíos en orden', async () => {
    renderSeccion()

    expect(await screen.findByText('Formación académica no registrada')).toBeInTheDocument()
    expect(screen.getByText('Experiencia anterior no registrada')).toBeInTheDocument()
    expect(screen.getByText('No informada')).toBeInTheDocument()
    const titulos = screen.getAllByRole('heading', { level: 2 }).map((h) => h.textContent)
    expect(titulos).toEqual(['Formación académica', 'Experiencia anterior', 'Disponibilidad para movilidad'])
  })

  it('un fallo muestra error con Reintentar y reintenta la consulta', async () => {
    vi.mocked(obtenerDesarrollo).mockRejectedValueOnce(httpError(403))
    renderSeccion()

    expect(await screen.findByText('No se pudo cargar la información de desarrollo')).toBeInTheDocument()
    vi.mocked(obtenerDesarrollo).mockResolvedValue(desarrollo())
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Formación académica no registrada')).toBeInTheDocument()
  })

  it('guarda una formación válida sin fechas y cierra el diálogo', async () => {
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Agregar formación' }))
    await completarFormacion(user)
    await user.click(screen.getByRole('button', { name: 'Guardar formación' }))

    await waitFor(() => expect(crearFormacion).toHaveBeenCalledTimes(1))
    expect(vi.mocked(crearFormacion).mock.calls[0][1]).toMatchObject({ nivel: 'Tercer nivel', fecha_inicio: null, fecha_finalizacion: null })
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(screen.getByText('Cambios guardados.')).toBeInTheDocument()
  })

  it('muestra el error por campo si la finalización es anterior al inicio y no envía nada', async () => {
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Agregar formación' }))
    await completarFormacion(user)
    await user.type(screen.getByLabelText('Fecha de inicio'), '2020-05-01')
    await user.type(screen.getByLabelText('Fecha de finalización'), '2020-04-01')
    await user.click(screen.getByRole('button', { name: 'Guardar formación' }))

    expect(await screen.findByText('La fecha de finalización no puede ser anterior a la de inicio.')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('Revisa los campos marcados')
    expect(crearFormacion).not.toHaveBeenCalled()
  })

  it('un doble clic envía una sola petición', async () => {
    let resolver: (valor: ReturnType<typeof formacion>) => void = () => {}
    vi.mocked(crearFormacion).mockReturnValue(new Promise((r) => (resolver = r)))
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Agregar formación' }))
    await completarFormacion(user)
    await user.dblClick(screen.getByRole('button', { name: 'Guardar formación' }))

    await waitFor(() => expect(crearFormacion).toHaveBeenCalledTimes(1))
    resolver(formacion())
  })

  it('un 409 conserva lo escrito y «Actualizar datos» vuelve a consultar con la versión nueva', async () => {
    vi.mocked(obtenerDesarrollo).mockResolvedValue(desarrollo({ formaciones: [formacion({ id: 5, titulo_programa: 'Maestría' })] }))
    vi.mocked(corregirFormacion).mockRejectedValueOnce(httpError(409, { message: 'cambió', code: 'REGISTRO_MODIFICADO' }))
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Editar formación Maestría' }))
    const institucion = screen.getByLabelText('Institución')
    await user.clear(institucion)
    await user.type(institucion, 'Nueva institución')
    await user.click(screen.getByRole('button', { name: 'Guardar formación' }))

    expect(await screen.findByText('La información cambió')).toBeInTheDocument()
    expect(screen.getByLabelText('Institución')).toHaveValue('Nueva institución')

    vi.mocked(obtenerDesarrollo).mockResolvedValue(desarrollo({ formaciones: [formacion({ id: 5, titulo_programa: 'Maestría', version: 3 })] }))
    await user.click(screen.getByRole('button', { name: 'Actualizar datos' }))
    await waitFor(() => expect(screen.queryByText('La información cambió')).not.toBeInTheDocument())
    expect(screen.getByLabelText('Institución')).toHaveValue('Nueva institución')

    await user.click(screen.getByRole('button', { name: 'Guardar formación' }))
    await waitFor(() => expect(corregirFormacion).toHaveBeenCalledTimes(2))
    expect(vi.mocked(corregirFormacion).mock.calls[1][3]).toBe(3)
  })

  it('mapea un 422 del servidor al campo correspondiente', async () => {
    vi.mocked(crearFormacion).mockRejectedValueOnce(httpError(422, { message: 'x', code: 'VALIDACION', errors: { nivel: ['Este campo es obligatorio.'] } }))
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Agregar formación' }))
    await completarFormacion(user)
    await user.click(screen.getByRole('button', { name: 'Guardar formación' }))

    expect(await screen.findByText('Este campo es obligatorio.')).toBeInTheDocument()
  })

  it('el diálogo de movilidad guarda el estado elegido y muestra la nota', async () => {
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Actualizar disponibilidad' }))
    const dialogo = screen.getByRole('dialog')

    expect(within(dialogo).getByText('Estar disponible no significa que exista un traslado aprobado.')).toBeInTheDocument()
    await user.click(within(dialogo).getByLabelText('Disponible'))
    await user.type(within(dialogo).getByLabelText('Observaciones'), 'Otras ciudades')
    await user.click(within(dialogo).getByRole('button', { name: 'Guardar disponibilidad' }))

    await waitFor(() => expect(actualizarMovilidad).toHaveBeenCalledTimes(1))
    expect(vi.mocked(actualizarMovilidad).mock.calls[0].slice(1)).toEqual([{ estado: 'Disponible', observaciones: 'Otras ciudades' }, 1])
  })

  it('guarda una experiencia con fin vacío como null', async () => {
    const user = userEvent.setup()
    renderSeccion()
    await user.click(await screen.findByRole('button', { name: 'Agregar experiencia' }))
    await user.type(await screen.findByLabelText('Organización'), 'ACME')
    await user.type(screen.getByLabelText('Cargo'), 'Analista')
    await user.type(screen.getByLabelText('Fecha de inicio'), '2019-01-07')
    await user.click(screen.getByRole('button', { name: 'Guardar experiencia' }))

    await waitFor(() => expect(crearExperiencia).toHaveBeenCalledTimes(1))
    expect(vi.mocked(crearExperiencia).mock.calls[0][1]).toMatchObject({ fecha_finalizacion: null, funciones: null })
  })

  it('en la vista propia o sin permiso no hay botones de edición ni de eliminar', async () => {
    vi.mocked(obtenerDesarrollo).mockResolvedValue(
      desarrollo({ acciones: { editar: false }, formaciones: [formacion()], experiencias: [experiencia()] }),
    )
    renderSeccion(null)

    expect(await screen.findByText('Ingeniería de Sistemas')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
    expect(obtenerDesarrollo).toHaveBeenCalledWith(null)
  })
})
