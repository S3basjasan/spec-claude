// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { detalle } from '../../../../test/fichasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { obtenerColaborador, obtenerOpcionesDeIngreso, registrarIngreso, type OpcionesDeIngreso } from '../api'
import { RequireAdmin } from '../components/RequireAdmin'
import { RegistrarIngresoPage } from './RegistrarIngresoPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerColaborador: vi.fn(),
  obtenerOpcionesDeIngreso: vi.fn(),
  registrarIngreso: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderPage() {
  return renderRoutes(
    <>
      <Route element={<RequireAdmin />}>
        <Route path="/colaboradores/:colaboradorId/ingreso" element={<RegistrarIngresoPage />} />
      </Route>
      <Route path="/colaboradores/:colaboradorId" element={<p>Detalle de ficha</p>} />
    </>,
    '/colaboradores/1/ingreso',
  )
}

const contratos = [
  { clave: 'indefinido', etiqueta: 'Indefinido' },
  { clave: 'plazo_fijo', etiqueta: 'Plazo fijo' },
]

function opciones(cambios: Partial<OpcionesDeIngreso> = {}): OpcionesDeIngreso {
  return {
    tipos_contrato: contratos,
    ingreso_programado: false,
    conflicto_ficha: null,
    posiciones: [
      { id: 12, puesto: 'Jefe de talento humano', unidad: 'Área de Talento', jefatura: null },
      { id: 13, puesto: 'Analista de datos', unidad: 'Finanzas', jefatura: 'Rosa Vera' },
    ],
    total: 2,
    ...cambios,
  }
}

beforeEach(() => {
  vi.mocked(obtenerColaborador).mockReset().mockResolvedValue(detalle())
  vi.mocked(obtenerOpcionesDeIngreso).mockReset().mockResolvedValue(opciones())
  vi.mocked(registrarIngreso).mockReset().mockResolvedValue({
    vinculo: { id: 5, fecha_ingreso: '2026-10-15', tipo_contrato: contratos[0], registrado_en: '2026-10-15T10:00:00Z', programado: false },
    asignacion: { id: 6, posicion_id: 12, fecha_inicio: '2026-10-15' },
    situacion: { estado: 'Activo' },
  })
  comoRol('admin')
})

async function elegirPosicion(user: ReturnType<typeof userEvent.setup>, nombre: RegExp) {
  await user.click(await screen.findByRole('combobox', { name: 'Posición' }))
  await user.click(await screen.findByRole('option', { name: nombre }))
}

describe('RegistrarIngresoPage', () => {
  it('el Usuario no accede a la pantalla', async () => {
    comoRol('user')
    renderPage()

    expect(await screen.findByText('No tienes permiso para ver esta información')).toBeInTheDocument()
  })

  it('muestra fecha, tipos de contrato y posiciones, con puesto, unidad y jefatura al elegir una', async () => {
    const user = userEvent.setup()
    renderPage()

    expect(await screen.findByLabelText('Fecha de ingreso')).toHaveValue(new Date().toLocaleDateString('en-CA'))
    const select = await screen.findByLabelText('Tipo de contrato')
    await waitFor(() => expect(select.querySelectorAll('option')).toHaveLength(3))

    await elegirPosicion(user, /Analista de datos/)

    const derivados = await screen.findByLabelText('Datos de la posición')
    expect(derivados).toHaveTextContent('Analista de datos')
    expect(derivados).toHaveTextContent('Finanzas')
    expect(derivados).toHaveTextContent('Rosa Vera')
  })

  it('muestra errores por campo y no envía si faltan datos', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Confirmar ingreso' }))

    expect(await screen.findAllByText('Este campo es obligatorio.')).toHaveLength(2)
    expect(registrarIngreso).not.toHaveBeenCalled()
  })

  it('confirma el ingreso con una clave de operación y vuelve al detalle', async () => {
    const user = userEvent.setup()
    renderPage()
    await user.selectOptions(await screen.findByLabelText('Tipo de contrato'), 'indefinido')
    await elegirPosicion(user, /Jefe de talento humano/)

    await user.click(screen.getByRole('button', { name: 'Confirmar ingreso' }))

    await waitFor(() => expect(registrarIngreso).toHaveBeenCalledTimes(1))
    const [colaboradorId, datos, clave] = vi.mocked(registrarIngreso).mock.calls[0]
    expect(colaboradorId).toBe(1)
    expect(datos).toMatchObject({ tipo_contrato: 'indefinido', posicion_id: 12 })
    expect(clave).toMatch(/^[0-9a-f-]{36}$/)
    expect(await screen.findByText('Detalle de ficha')).toBeInTheDocument()
  })

  it('bloquea el envío mientras confirma y no aplica cambios optimistas', async () => {
    const user = userEvent.setup()
    vi.mocked(registrarIngreso).mockReturnValue(new Promise(() => {}))
    renderPage()
    await user.selectOptions(await screen.findByLabelText('Tipo de contrato'), 'indefinido')
    await elegirPosicion(user, /Jefe de talento humano/)

    await user.click(screen.getByRole('button', { name: 'Confirmar ingreso' }))

    expect(await screen.findByRole('button', { name: 'Confirmando…' })).toBeDisabled()
    expect(registrarIngreso).toHaveBeenCalledTimes(1)
    expect(screen.queryByText('Detalle de ficha')).not.toBeInTheDocument()
  })

  it('ante una posición no disponible explica el motivo y conserva el formulario', async () => {
    const user = userEvent.setup()
    vi.mocked(registrarIngreso).mockRejectedValue(
      httpError(422, {
        message: 'La posición está ocupada desde el 2026-01-01 (sin fecha de fin) durante el periodo solicitado.',
        code: 'POSICION_NO_DISPONIBLE',
        errors: { posicion_id: ['La posición está ocupada desde el 2026-01-01.'] },
      }),
    )
    renderPage()
    await user.selectOptions(await screen.findByLabelText('Tipo de contrato'), 'plazo_fijo')
    await elegirPosicion(user, /Jefe de talento humano/)

    await user.click(screen.getByRole('button', { name: 'Confirmar ingreso' }))

    expect((await screen.findAllByText(/La posición está ocupada desde el 2026-01-01/)).length).toBeGreaterThan(0)
    expect(screen.getByLabelText('Tipo de contrato')).toHaveValue('plazo_fijo')
  })

  it('ante un vínculo superpuesto muestra el motivo', async () => {
    const user = userEvent.setup()
    vi.mocked(registrarIngreso).mockRejectedValue(
      httpError(422, {
        message: 'La ficha ya tiene un vínculo laboral desde el 2026-01-01 (sin fecha de salida) que se superpone con esa fecha.',
        code: 'VINCULO_SUPERPUESTO',
        errors: { fecha_ingreso: ['La ficha ya tiene un vínculo laboral.'] },
      }),
    )
    renderPage()
    await user.selectOptions(await screen.findByLabelText('Tipo de contrato'), 'indefinido')
    await elegirPosicion(user, /Jefe de talento humano/)

    await user.click(screen.getByRole('button', { name: 'Confirmar ingreso' }))

    expect((await screen.findAllByText(/La ficha ya tiene un vínculo laboral/)).length).toBeGreaterThan(0)
  })

  it('si no puede verificarse la posición lo indica sin registrar nada', async () => {
    const user = userEvent.setup()
    vi.mocked(registrarIngreso).mockRejectedValue(httpError(503, { message: 'x', code: 'ESTRUCTURA_NO_VERIFICABLE' }))
    renderPage()
    await user.selectOptions(await screen.findByLabelText('Tipo de contrato'), 'indefinido')
    await elegirPosicion(user, /Jefe de talento humano/)

    await user.click(screen.getByRole('button', { name: 'Confirmar ingreso' }))

    expect(await screen.findByText(/No se pudo verificar la posición/)).toBeInTheDocument()
    expect(screen.queryByText('Detalle de ficha')).not.toBeInTheDocument()
  })

  it('una clave reutilizada con otros datos avisa y regenera la clave', async () => {
    const user = userEvent.setup()
    vi.mocked(registrarIngreso).mockRejectedValueOnce(httpError(409, { message: 'x', code: 'CLAVE_DE_OPERACION_REUTILIZADA' }))
    renderPage()
    await user.selectOptions(await screen.findByLabelText('Tipo de contrato'), 'indefinido')
    await elegirPosicion(user, /Jefe de talento humano/)

    await user.click(screen.getByRole('button', { name: 'Confirmar ingreso' }))
    expect(await screen.findByText(/No se pudo confirmar el intento anterior/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Confirmar ingreso' }))

    await waitFor(() => expect(registrarIngreso).toHaveBeenCalledTimes(2))
    const claves = vi.mocked(registrarIngreso).mock.calls.map((llamada) => llamada[2])
    expect(claves[0]).not.toBe(claves[1])
  })

  it('conserva los datos cuando no hay conexión', async () => {
    const user = userEvent.setup()
    vi.mocked(registrarIngreso).mockRejectedValue(networkError())
    renderPage()
    await user.selectOptions(await screen.findByLabelText('Tipo de contrato'), 'indefinido')
    await elegirPosicion(user, /Jefe de talento humano/)

    await user.click(screen.getByRole('button', { name: 'Confirmar ingreso' }))

    expect(await screen.findByText(/No se pudo conectar con el servidor/)).toBeInTheDocument()
    expect(screen.getByLabelText('Tipo de contrato')).toHaveValue('indefinido')
  })

  it('al cambiar la fecha vuelve a cargar las opciones y descarta con aviso la selección incompatible', async () => {
    const user = userEvent.setup()
    renderPage()
    await user.selectOptions(await screen.findByLabelText('Tipo de contrato'), 'indefinido')
    await elegirPosicion(user, /Jefe de talento humano/)
    vi.mocked(obtenerOpcionesDeIngreso).mockResolvedValue(
      opciones({ posiciones: [{ id: 13, puesto: 'Analista de datos', unidad: 'Finanzas', jefatura: null }], total: 1 }),
    )

    const fecha = screen.getByLabelText('Fecha de ingreso')
    await user.clear(fecha)
    await user.type(fecha, '2026-11-20')

    expect(await screen.findByText(/ya no está disponible para la nueva fecha/)).toBeInTheDocument()
    expect(vi.mocked(obtenerOpcionesDeIngreso).mock.calls.at(-1)?.[1]).toBe('2026-11-20')
  })

  it('con fecha futura muestra el aviso de ingreso programado', async () => {
    vi.mocked(obtenerOpcionesDeIngreso).mockResolvedValue(opciones({ ingreso_programado: true }))
    renderPage()

    expect(await screen.findByText(/La fecha es futura/)).toBeInTheDocument()
  })

  it('sin posiciones para la fecha muestra el estado vacío', async () => {
    vi.mocked(obtenerOpcionesDeIngreso).mockResolvedValue(opciones({ posiciones: [], total: 0 }))
    renderPage()

    expect(await screen.findByText('No hay posiciones disponibles para esa fecha')).toBeInTheDocument()
  })

  it('con Estructura caída muestra el error con Reintentar', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerOpcionesDeIngreso).mockRejectedValueOnce(httpError(503, { code: 'ESTRUCTURA_NO_VERIFICABLE' })).mockResolvedValue(opciones())
    renderPage()

    expect(await screen.findByText('No se pudo verificar la disponibilidad de posiciones')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('combobox', { name: 'Posición' })).toBeInTheDocument()
  })

  it('Cancelar vuelve al detalle sin crear nada', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Cancelar' }))

    expect(await screen.findByText('Detalle de ficha')).toBeInTheDocument()
    expect(registrarIngreso).not.toHaveBeenCalled()
  })

  it('para una ficha Desvinculada el título es Registrar reingreso', async () => {
    vi.mocked(obtenerColaborador).mockResolvedValue(
      detalle({ laboral: { disponibilidad: 'disponible', estado: 'Desvinculado', puesto: null, unidad: null } }),
    )
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Registrar reingreso', level: 1 })).toBeInTheDocument()
  })
})
