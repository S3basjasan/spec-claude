// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { opcionesRutas, rutaDetalle } from '../../../../test/rutasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarRuta, buscarColaboradores, crearRuta, inscribir, obtenerOpcionesRutas } from '../api'
import { FormularioInscripcion } from './FormularioInscripcion'
import { FormularioRutaModal } from './FormularioRutaModal'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  actualizarRuta: vi.fn(),
  buscarColaboradores: vi.fn(),
  crearRuta: vi.fn(),
  inscribir: vi.fn(),
  obtenerOpcionesRutas: vi.fn(),
}))

beforeEach(() => {
  vi.mocked(obtenerOpcionesRutas).mockReset().mockResolvedValue(opcionesRutas())
  vi.mocked(crearRuta).mockReset().mockResolvedValue(rutaDetalle())
  vi.mocked(actualizarRuta).mockReset().mockResolvedValue(rutaDetalle({ version: 3 }))
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue([
    { id: 10, codigo: 'COL-0010', nombre: 'Ana Núñez' },
    { id: 11, codigo: 'COL-0011', nombre: 'Luis Núñez' },
  ])
  vi.mocked(inscribir).mockReset()
})

describe('FormularioRutaModal', () => {
  function renderModal(ruta = null as ReturnType<typeof rutaDetalle> | null) {
    const onCerrar = vi.fn()
    const onGuardado = vi.fn()
    renderRoutes(<Route path="/" element={<FormularioRutaModal ruta={ruta} onCerrar={onCerrar} onGuardado={onGuardado} />} />, '/')
    return { onCerrar, onGuardado }
  }

  it('«Nueva ruta» exige el nombre y no envía nada si falta', async () => {
    renderModal()

    await userEvent.click(await screen.findByRole('button', { name: 'Crear ruta' }))

    expect(await screen.findByText('El nombre es obligatorio.')).toBeInTheDocument()
    expect(crearRuta).not.toHaveBeenCalled()
  })

  it('crea la ruta con los datos del formulario y avisa', async () => {
    const { onGuardado } = renderModal()

    await userEvent.type(await screen.findByLabelText('Nombre de la ruta'), '  Carrera de datos ')
    await userEvent.selectOptions(screen.getByLabelText('Departamento'), 'Tecnología')
    await userEvent.type(screen.getByLabelText('Descripción'), 'Camino técnico')
    await userEvent.click(screen.getByRole('button', { name: 'Crear ruta' }))

    await vi.waitFor(() => expect(crearRuta).toHaveBeenCalledWith({ nombre: 'Carrera de datos', tipo: 'Especialista', descripcion: 'Camino técnico', departamento_id: 1 }))
    await vi.waitFor(() => expect(onGuardado).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }), true))
  })

  it('una ruta Interáreas deshabilita el departamento y lo envía vacío', async () => {
    renderModal()

    await userEvent.type(await screen.findByLabelText('Nombre de la ruta'), 'Ruta cruzada')
    await userEvent.selectOptions(screen.getByLabelText('Departamento'), 'Tecnología')
    await userEvent.selectOptions(screen.getByLabelText('Tipo de ruta'), 'Interareas')

    expect(screen.getByLabelText('Departamento')).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: 'Crear ruta' }))
    await vi.waitFor(() => expect(crearRuta).toHaveBeenCalledWith(expect.objectContaining({ tipo: 'Interareas', departamento_id: null })))
  })

  it('muestra el error del servidor por campo (nombre duplicado) y conserva lo escrito', async () => {
    vi.mocked(crearRuta).mockRejectedValue(httpError(422, { message: 'Revisa los datos de la ruta.', errors: { nombre: ['Ya existe una ruta con ese nombre.'] } }))
    renderModal()

    await userEvent.type(await screen.findByLabelText('Nombre de la ruta'), 'Ruta repetida')
    await userEvent.click(screen.getByRole('button', { name: 'Crear ruta' }))

    expect(await screen.findByText('Ya existe una ruta con ese nombre.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre de la ruta')).toHaveValue('Ruta repetida')
  })

  it('bloquea el envío mientras está pendiente (sin doble envío)', async () => {
    vi.mocked(crearRuta).mockReturnValue(new Promise(() => {}))
    renderModal()

    await userEvent.type(await screen.findByLabelText('Nombre de la ruta'), 'Ruta')
    await userEvent.click(screen.getByRole('button', { name: 'Crear ruta' }))

    expect(screen.getByRole('button', { name: 'Crear ruta' })).toBeDisabled()
    expect(crearRuta).toHaveBeenCalledTimes(1)
  })

  it('al editar parte de los datos actuales y envía la versión leída con los niveles actuales', async () => {
    const { onGuardado } = renderModal(rutaDetalle())

    const nombre = await screen.findByLabelText('Nombre de la ruta')
    expect(nombre).toHaveValue('Carrera de especialista en datos')
    await userEvent.clear(nombre)
    await userEvent.type(nombre, 'Carrera renovada')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await vi.waitFor(() => expect(actualizarRuta).toHaveBeenCalled())
    const [id, cuerpo] = vi.mocked(actualizarRuta).mock.calls[0]
    expect(id).toBe(1)
    expect(cuerpo.version).toBe(2)
    expect(cuerpo.nombre).toBe('Carrera renovada')
    expect(cuerpo.niveles.map((n) => n.id)).toEqual([101, 102, 103])
    await vi.waitFor(() => expect(onGuardado).toHaveBeenCalledWith(expect.anything(), false))
  })

  it('si otro administrador cambió la ruta (409) lo avisa y ofrece «Recargar»', async () => {
    vi.mocked(actualizarRuta).mockRejectedValue(httpError(409, { message: 'Otro administrador modificó la ruta.' }))
    renderModal(rutaDetalle())

    await userEvent.click(await screen.findByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Otro administrador modificó la ruta mientras la editabas')
    expect(screen.getByRole('button', { name: 'Recargar' })).toBeInTheDocument()
  })

  it('si no cargan las opciones ofrece «Reintentar»', async () => {
    vi.mocked(obtenerOpcionesRutas).mockRejectedValue(new Error('caída'))
    renderModal()

    expect(await screen.findByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
  })
})

describe('FormularioInscripcion', () => {
  function renderForm(ruta = rutaDetalle()) {
    const onInscrito = vi.fn()
    renderRoutes(<Route path="/" element={<FormularioInscripcion ruta={ruta} onInscrito={onInscrito} />} />, '/')
    return { onInscrito }
  }

  it('solo ofrece los colaboradores con vínculo vigente que devuelve el servidor', async () => {
    renderForm()

    expect(await screen.findByRole('option', { name: 'Ana Núñez' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Luis Núñez' })).toBeInTheDocument()
    expect(buscarColaboradores).toHaveBeenCalledWith('')
  })

  it('exige elegir un colaborador', async () => {
    renderForm()
    await screen.findByRole('option', { name: 'Ana Núñez' })

    await userEvent.click(screen.getByRole('button', { name: 'Inscribir en la ruta' }))

    expect(await screen.findByText('Elige el colaborador que quieres inscribir.')).toBeInTheDocument()
    expect(inscribir).not.toHaveBeenCalled()
  })

  it('rechaza un nivel objetivo igual o anterior al actual', async () => {
    renderForm()
    await screen.findByRole('option', { name: 'Ana Núñez' })

    await userEvent.selectOptions(screen.getByLabelText('Colaborador'), 'Ana Núñez')
    await userEvent.selectOptions(screen.getByLabelText('Nivel actual (opcional)'), 'Nivel 2 · Analista de datos')
    await userEvent.selectOptions(screen.getByLabelText('Nivel objetivo (opcional)'), 'Nivel 1 · Asistente de datos')
    await userEvent.click(screen.getByRole('button', { name: 'Inscribir en la ruta' }))

    expect(await screen.findByText('El nivel objetivo debe ser posterior al nivel actual.')).toBeInTheDocument()
    expect(inscribir).not.toHaveBeenCalled()
  })

  it('inscribe con nivel actual y objetivo y avisa', async () => {
    vi.mocked(inscribir).mockResolvedValue({} as Awaited<ReturnType<typeof inscribir>>)
    const { onInscrito } = renderForm()
    await screen.findByRole('option', { name: 'Ana Núñez' })

    await userEvent.selectOptions(screen.getByLabelText('Colaborador'), 'Ana Núñez')
    await userEvent.selectOptions(screen.getByLabelText('Nivel actual (opcional)'), 'Nivel 1 · Asistente de datos')
    await userEvent.selectOptions(screen.getByLabelText('Nivel objetivo (opcional)'), 'Nivel 3 · Analista senior')
    await userEvent.click(screen.getByRole('button', { name: 'Inscribir en la ruta' }))

    await vi.waitFor(() => expect(inscribir).toHaveBeenCalledWith({ ruta_id: 1, colaborador_id: 10, nivel_actual_id: 101, nivel_objetivo_id: 103 }))
    await vi.waitFor(() => expect(onInscrito).toHaveBeenCalledWith('Ana Núñez fue inscrito en la ruta.'))
  })

  it('en una ruta que no está Vigente deshabilita el formulario y lo explica', async () => {
    renderForm(rutaDetalle({ estado: 'Borrador' }))

    expect(await screen.findByText('Solo las rutas Vigentes admiten nuevas inscripciones.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Inscribir en la ruta' })).toBeDisabled()
  })

  it('muestra el error del servidor (ya inscrito) sin perder lo elegido', async () => {
    vi.mocked(inscribir).mockRejectedValue(httpError(422, { message: 'El colaborador ya tiene una inscripción activa en esta ruta.', errors: { colaborador_id: ['El colaborador ya tiene una inscripción activa en esta ruta.'] } }))
    renderForm()
    await screen.findByRole('option', { name: 'Ana Núñez' })

    await userEvent.selectOptions(screen.getByLabelText('Colaborador'), 'Ana Núñez')
    await userEvent.click(screen.getByRole('button', { name: 'Inscribir en la ruta' }))

    expect(await screen.findByText('El colaborador ya tiene una inscripción activa en esta ruta.')).toBeInTheDocument()
    expect(screen.getByLabelText('Colaborador')).toHaveValue('10')
  })
})
