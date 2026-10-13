// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { competenciaAsignada, competenciaDisponible, detalle, funcion } from '../../../../test/puestosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { actualizarPuesto, listarCompetenciasDisponibles, obtenerPuesto } from '../api'
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

function renderEditar(entrada = '/puestos/1/editar') {
  return renderRoutes(
    <>
      <Route element={<RequireAdmin />}>
        <Route path="/puestos/:puestoId/editar" element={<PuestoFormPage />} />
      </Route>
      <Route path="/puestos" element={<p>Listado de puestos</p>} />
    </>,
    entrada,
  )
}

const disponibles = [
  competenciaDisponible({ competencia_id: 2, codigo: 'COMP-0002', nombre: 'Trabajo en equipo' }),
  competenciaDisponible({ competencia_id: 3, codigo: 'COMP-0003', nombre: 'Comunicación efectiva' }),
]

beforeEach(() => {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role: 'admin' } } as ReturnType<typeof useCurrentUser>)
  vi.mocked(listarCompetenciasDisponibles).mockReset().mockResolvedValue(disponibles)
  vi.mocked(obtenerPuesto).mockReset().mockResolvedValue(detalle({ version: 3 }))
  vi.mocked(actualizarPuesto).mockReset().mockResolvedValue(detalle({ version: 4 }))
})

describe('PuestoFormPage (editar)', () => {
  it('muestra un esqueleto mientras carga el puesto', () => {
    vi.mocked(obtenerPuesto).mockReturnValue(new Promise(() => {}))
    renderEditar()

    expect(screen.getByRole('status', { name: 'Cargando formulario…' })).toBeInTheDocument()
  })

  it('muestra el código en solo lectura y los valores actuales en las cuatro secciones', async () => {
    renderEditar()

    expect(await screen.findByRole('heading', { name: 'Editar puesto', level: 1 })).toBeInTheDocument()
    const codigo = screen.getByLabelText('Código')
    expect(codigo).toHaveValue('PU-001')
    expect(codigo).toHaveAttribute('readonly')
    expect(screen.getByText('El código no se puede cambiar.')).toBeInTheDocument()

    expect(screen.getByLabelText('Nombre')).toHaveValue('Analista de datos')
    expect(screen.getByLabelText('Misión')).toHaveValue('Transformar los datos de la organización en información confiable.')
    expect(screen.getByRole('textbox', { name: 'Descripción de la función 2' })).toHaveValue('Documentar indicadores.')
    expect(screen.getByRole('combobox', { name: 'Tipo de la función 2' })).toHaveValue('Complementaria')
    expect(screen.getByRole('textbox', { name: 'Descripción del requisito 3' })).toHaveValue('Curso de SQL.')
    expect(screen.getByRole('combobox', { name: 'Tipo del requisito 3' })).toHaveValue('Capacitacion')
    expect(screen.getByRole('combobox', { name: 'Competencia 1' })).toHaveValue('Trabajo en equipo')
    expect(screen.getByRole('combobox', { name: 'Grado requerido' })).toHaveValue('B')
  })

  it('con una competencia inactiva muestra el aviso, el grado bloqueado y solo permite quitarla', async () => {
    vi.mocked(obtenerPuesto).mockResolvedValue(
      detalle({
        competencias: [
          competenciaAsignada(),
          competenciaAsignada({ competencia_id: 7, codigo: 'COMP-0007', nombre: 'Adaptabilidad', estado: 'Inactivo', grado_requerido: 'C' }),
        ],
      }),
    )
    renderEditar()

    expect(await screen.findByText('Adaptabilidad')).toBeInTheDocument()
    expect(screen.getByText('Inactiva')).toBeInTheDocument()
    expect(screen.getByLabelText('Grado C')).toBeInTheDocument()
    expect(screen.getByText(/La competencia está inactiva: solo puedes dejarla como está o quitarla./)).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Competencia 2' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Quitar competencia 2' })).toBeInTheDocument()
  })

  it('avisa cuando el puesto está inactivo, pero permite editarlo', async () => {
    vi.mocked(obtenerPuesto).mockResolvedValue(detalle({ estado: 'Inactivo' }))
    renderEditar()

    expect(await screen.findByText('Este puesto está inactivo: no puede usarse para crear nuevas posiciones.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Guardar puesto' })).toBeEnabled()
  })

  it('envía la versión con la que se abrió el formulario y vuelve al listado', async () => {
    renderEditar()
    const user = userEvent.setup()
    const nombre = await screen.findByLabelText('Nombre')

    await user.clear(nombre)
    await user.type(nombre, 'Analista senior')
    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    await waitFor(() => expect(actualizarPuesto).toHaveBeenCalledTimes(1))
    const [id, datos, version] = vi.mocked(actualizarPuesto).mock.calls[0]
    expect(id).toBe(1)
    expect(version).toBe(3)
    expect(datos.nombre).toBe('Analista senior')
    expect(datos.funciones.map((f) => f.descripcion)).toEqual(['Recopilar y consolidar datos.', 'Documentar indicadores.'])
    expect(datos.competencias).toEqual([{ competencia_id: 2, grado_requerido: 'B' }])
    expect(await screen.findByText('Listado de puestos')).toBeInTheDocument()
  })

  it('envía las funciones en el orden nuevo después de reordenarlas', async () => {
    renderEditar()
    const user = userEvent.setup()
    await screen.findByLabelText('Nombre')

    await user.click(screen.getByRole('button', { name: 'Subir función 2' }))
    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    await waitFor(() => expect(actualizarPuesto).toHaveBeenCalledTimes(1))
    expect(vi.mocked(actualizarPuesto).mock.calls[0][1].funciones.map((f) => f.descripcion)).toEqual([
      'Documentar indicadores.',
      'Recopilar y consolidar datos.',
    ])
  })

  it('ordena las funciones por su orden aunque el servidor las entregue desordenadas', async () => {
    vi.mocked(obtenerPuesto).mockResolvedValue(
      detalle({ funciones: [funcion({ orden: 2, descripcion: 'Segunda' }), funcion({ orden: 1, descripcion: 'Primera' })] }),
    )
    renderEditar()

    expect(await screen.findByRole('textbox', { name: 'Descripción de la función 1' })).toHaveValue('Primera')
    expect(screen.getByRole('textbox', { name: 'Descripción de la función 2' })).toHaveValue('Segunda')
  })

  it('guardar sin cambios vuelve al listado sin error', async () => {
    renderEditar()
    const user = userEvent.setup()
    await screen.findByLabelText('Nombre')

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    expect(await screen.findByText('Listado de puestos')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('bloquea el botón y evita envíos duplicados mientras guarda', async () => {
    vi.mocked(actualizarPuesto).mockReturnValue(new Promise(() => {}))
    renderEditar()
    const user = userEvent.setup()
    await screen.findByLabelText('Nombre')

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    const boton = await screen.findByRole('button', { name: 'Guardando…' })
    expect(boton).toBeDisabled()
    await user.click(boton)
    expect(actualizarPuesto).toHaveBeenCalledTimes(1)
  })

  it('ante una edición simultánea muestra el conflicto y permite recargar la versión actual', async () => {
    vi.mocked(actualizarPuesto).mockRejectedValueOnce(httpError(409, { message: 'El puesto cambió desde que lo abriste. Recarga para ver la versión actual.' }))
    renderEditar()
    const user = userEvent.setup()
    await screen.findByLabelText('Nombre')
    const lecturas = vi.mocked(obtenerPuesto).mock.calls.length

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    expect(await screen.findByText('El puesto cambió desde que lo abriste. Recarga para ver la versión actual.')).toBeInTheDocument()
    vi.mocked(obtenerPuesto).mockResolvedValue(detalle({ version: 4, nombre: 'Nombre actual' }))
    await user.click(screen.getByRole('button', { name: 'Recargar puesto' }))

    await waitFor(() => expect(vi.mocked(obtenerPuesto).mock.calls.length).toBeGreaterThan(lecturas))
    await waitFor(() => expect(screen.getByLabelText('Nombre')).toHaveValue('Nombre actual'))
    expect(screen.queryByText('El puesto cambió desde que lo abriste. Recarga para ver la versión actual.')).not.toBeInTheDocument()
  })

  it('si el puesto ya no existe al abrirlo lo indica', async () => {
    vi.mocked(obtenerPuesto).mockRejectedValue(httpError(404))
    renderEditar()

    expect(await screen.findByRole('heading', { name: 'El puesto ya no existe' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al listado' })).toHaveAttribute('href', '/puestos')
  })

  it('si el puesto desaparece al guardar lo indica sin perder el formulario', async () => {
    vi.mocked(actualizarPuesto).mockRejectedValueOnce(httpError(404))
    renderEditar()
    const user = userEvent.setup()
    await screen.findByLabelText('Nombre')

    await user.click(screen.getByRole('button', { name: 'Guardar puesto' }))

    expect(await screen.findByText('El puesto ya no existe')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveValue('Analista de datos')
  })

  it('muestra un error de carga con Reintentar', async () => {
    vi.mocked(obtenerPuesto).mockRejectedValueOnce(httpError(500)).mockResolvedValue(detalle())
    renderEditar()

    expect(await screen.findByText('No se pudo cargar el puesto')).toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('heading', { name: 'Editar puesto', level: 1 })).toBeInTheDocument()
  })
})
