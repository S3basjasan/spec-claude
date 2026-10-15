// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { detalle, listado, opciones } from '../../../../test/unidadesFixtures'
import { useCurrentUser } from '../../../auth/login'
import { crearUnidad, listarUnidades, obtenerOpciones } from '../api'
import { UnidadesPage } from '../pages/UnidadesPage'
import { UnidadForm } from './UnidadForm'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarUnidades: vi.fn(),
  obtenerOpciones: vi.fn(),
  crearUnidad: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderForm(props: Partial<React.ComponentProps<typeof UnidadForm>> = {}) {
  const onGuardada = vi.fn()
  const onCancelar = vi.fn()
  const utils = renderRoutes(
    <Route path="/" element={<UnidadForm opciones={opciones()} onCancelar={onCancelar} onGuardada={onGuardada} {...props} />} />,
    '/',
  )
  return { onGuardada, onCancelar, ...utils }
}

beforeEach(() => {
  vi.mocked(crearUnidad).mockReset().mockResolvedValue(detalle({ id: 10, codigo: 'UO-010', nombre: 'Compras' }))
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opciones())
  vi.mocked(listarUnidades).mockReset().mockResolvedValue(listado())
  comoRol('admin')
})

describe('UnidadForm (crear)', () => {
  it('muestra el título "Nueva unidad" y solo los tres campos, sin campo de estado', () => {
    renderForm()

    expect(screen.getByRole('form', { name: 'Nueva unidad' })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Nombre' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Unidad padre' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Tipo de unidad' })).toBeInTheDocument()
    expect(screen.getAllByRole('textbox')).toHaveLength(1)
    expect(screen.getAllByRole('combobox')).toHaveLength(2)
    expect(screen.queryByLabelText(/estado/i)).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Guardar unidad' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeInTheDocument()
  })

  it('el foco queda en el campo Nombre al abrirse', () => {
    renderForm()

    expect(screen.getByRole('textbox', { name: 'Nombre' })).toHaveFocus()
  })

  it('el selector de unidad padre ofrece "Ninguna (unidad raíz)" y las unidades en orden jerárquico con sangría de texto', () => {
    renderForm()

    const opcionesPadre = within(screen.getByRole('combobox', { name: 'Unidad padre' })).getAllByRole('option')
    expect(opcionesPadre.map((opcion) => opcion.textContent?.trim())).toEqual([
      'Ninguna (unidad raíz)',
      'Gerencia general (UO-001)',
      'Talento humano (UO-002)',
      'Reclutamiento (UO-009)',
      'Tecnología (UO-003)',
      'Inteligencia de negocio (UO-004)',
      'Desarrollo (UO-005)',
      'Finanzas (UO-006)',
      'Ventas (UO-007)',
    ])
    // La sangría crece con el nivel: ninguna en la raíz y tres espacios por nivel.
    expect(opcionesPadre[1].textContent?.startsWith(' ')).toBe(false)
    expect(opcionesPadre[2].textContent?.startsWith(' '.repeat(3))).toBe(true)
    expect(opcionesPadre[3].textContent?.startsWith(' '.repeat(6))).toBe(true)
  })

  it('el selector de tipo ofrece los cuatro tipos del catálogo', () => {
    renderForm()

    const tipos = within(screen.getByRole('combobox', { name: 'Tipo de unidad' })).getAllByRole('option')
    expect(tipos.map((opcion) => opcion.textContent)).toEqual(['Elige un tipo', 'Gerencia', 'Departamento', 'Área', 'Sección'])
  })

  it('deshabilita "Ninguna (unidad raíz)" cuando ya existe otra raíz activa', () => {
    renderForm({ opciones: opciones({ hay_otra_raiz_activa: true }) })

    expect(screen.getByRole('option', { name: 'Ninguna (unidad raíz)' })).toBeDisabled()
  })

  it('habilita "Ninguna (unidad raíz)" cuando no hay otra raíz activa', () => {
    renderForm({ opciones: opciones({ hay_otra_raiz_activa: false, unidades: [] }) })

    expect(screen.getByRole('option', { name: 'Ninguna (unidad raíz)' })).toBeEnabled()
  })

  it('muestra el contador de caracteres del nombre', async () => {
    renderForm()
    const user = userEvent.setup()

    expect(screen.getByText('0/150')).toBeInTheDocument()
    await user.type(screen.getByRole('textbox', { name: 'Nombre' }), 'Compras')

    expect(screen.getByText('7/150')).toBeInTheDocument()
  })

  it('con el formulario vacío muestra el aviso general y los errores junto a cada campo, sin enviar nada', async () => {
    renderForm()
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    expect(await screen.findByText('Revisa los campos marcados antes de guardar.')).toBeInTheDocument()
    const nombre = screen.getByRole('textbox', { name: 'Nombre' })
    expect(nombre).toHaveAttribute('aria-invalid', 'true')
    expect(nombre).toHaveAccessibleDescription('El nombre es obligatorio.')
    expect(screen.getByRole('combobox', { name: 'Tipo de unidad' })).toHaveAccessibleDescription('Elige el tipo de unidad.')
    expect(screen.getByRole('combobox', { name: 'Unidad padre' })).toHaveAccessibleDescription('Elige la unidad padre: ya existe una unidad raíz activa.')
    expect(crearUnidad).not.toHaveBeenCalled()
  })

  it('sin unidad padre y con otra raíz activa pide elegirla sin enviar nada', async () => {
    renderForm({ opciones: opciones({ hay_otra_raiz_activa: true }) })
    const user = userEvent.setup()
    await user.type(screen.getByRole('textbox', { name: 'Nombre' }), 'Compras')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Tipo de unidad' }), '2')

    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    expect(await screen.findByText('Elige la unidad padre: ya existe una unidad raíz activa.')).toBeInTheDocument()
    expect(crearUnidad).not.toHaveBeenCalled()
  })

  it('envía el nombre recortado, el tipo y la unidad padre, y avisa de la unidad guardada', async () => {
    const { onGuardada } = renderForm()
    const user = userEvent.setup()
    await user.type(screen.getByRole('textbox', { name: 'Nombre' }), '  Compras  ')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Unidad padre' }), '1')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Tipo de unidad' }), '2')

    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    await waitFor(() => expect(onGuardada).toHaveBeenCalled())
    expect(crearUnidad).toHaveBeenCalledWith({ nombre: 'Compras', tipo_unidad_id: 2, unidad_padre_id: 1 })
    expect(onGuardada.mock.calls[0][1]).toBe(false)
  })

  it('puede crear la unidad raíz cuando no existe otra raíz activa', async () => {
    renderForm({ opciones: opciones({ hay_otra_raiz_activa: false, unidades: [] }) })
    const user = userEvent.setup()
    await user.type(screen.getByRole('textbox', { name: 'Nombre' }), 'Gerencia general')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Tipo de unidad' }), '1')

    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    await waitFor(() => expect(crearUnidad).toHaveBeenCalledWith({ nombre: 'Gerencia general', tipo_unidad_id: 1, unidad_padre_id: null }))
  })

  it('bloquea el botón mientras guarda y no envía dos veces', async () => {
    vi.mocked(crearUnidad).mockReturnValue(new Promise(() => {}))
    renderForm()
    const user = userEvent.setup()
    await user.type(screen.getByRole('textbox', { name: 'Nombre' }), 'Compras')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Unidad padre' }), '1')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Tipo de unidad' }), '2')

    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    const guardando = await screen.findByRole('button', { name: 'Guardando…' })
    expect(guardando).toBeDisabled()
    await user.click(guardando)
    expect(crearUnidad).toHaveBeenCalledTimes(1)
  })

  it('muestra los errores 422 junto a cada campo y conserva lo escrito', async () => {
    vi.mocked(crearUnidad).mockRejectedValue(
      httpError(422, {
        message: 'Los datos no son válidos.',
        errors: {
          nombre: ['El nombre es obligatorio.'],
          tipo_unidad_id: ['El tipo de unidad elegido no existe.'],
          unidad_padre_id: ['La unidad padre debe estar activa.'],
        },
      }),
    )
    renderForm()
    const user = userEvent.setup()
    await user.type(screen.getByRole('textbox', { name: 'Nombre' }), 'Compras')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Unidad padre' }), '1')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Tipo de unidad' }), '2')

    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    expect(await screen.findByText('La unidad padre debe estar activa.')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Nombre' })).toHaveAccessibleDescription('El nombre es obligatorio.')
    expect(screen.getByRole('combobox', { name: 'Tipo de unidad' })).toHaveAccessibleDescription('El tipo de unidad elegido no existe.')
    expect(screen.getByRole('combobox', { name: 'Unidad padre' })).toHaveAccessibleDescription('La unidad padre debe estar activa.')
    expect(screen.getByRole('textbox', { name: 'Nombre' })).toHaveValue('Compras')
    expect(screen.getByRole('combobox', { name: 'Unidad padre' })).toHaveValue('1')
    expect(screen.getByRole('button', { name: 'Guardar unidad' })).toBeEnabled()
  })

  it('avisa si no hay conexión o si el servidor falla, sin perder lo escrito', async () => {
    vi.mocked(crearUnidad).mockRejectedValueOnce(networkError()).mockRejectedValueOnce(httpError(500))
    renderForm()
    const user = userEvent.setup()
    await user.type(screen.getByRole('textbox', { name: 'Nombre' }), 'Compras')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Unidad padre' }), '1')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Tipo de unidad' }), '2')

    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))
    expect(await screen.findByText('No se pudo conectar con el servidor. Inténtalo de nuevo.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))
    expect(await screen.findByText('No se pudo guardar la unidad. Inténtalo de nuevo.')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Nombre' })).toHaveValue('Compras')
  })

  it('Cancelar cierra sin enviar nada', async () => {
    const { onCancelar } = renderForm()

    await userEvent.setup().click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(onCancelar).toHaveBeenCalled()
    expect(crearUnidad).not.toHaveBeenCalled()
  })
})

describe('Nueva unidad en la página', () => {
  function renderPage() {
    return renderRoutes(<Route path="/unidades" element={<UnidadesPage />} />, '/unidades')
  }

  it('el Usuario no ve el botón ni el formulario', async () => {
    comoRol('user')
    renderPage()

    await screen.findByRole('table', { name: 'Unidades organizacionales' })
    expect(screen.queryByRole('button', { name: 'Nueva unidad' })).not.toBeInTheDocument()
    expect(screen.queryByRole('form', { name: 'Nueva unidad' })).not.toBeInTheDocument()
    expect(obtenerOpciones).not.toHaveBeenCalled()
  })

  it('el Administrador abre el formulario sobre el listado y lo cierra con Cancelar devolviendo el foco', async () => {
    renderPage()
    const user = userEvent.setup()
    await screen.findByRole('table', { name: 'Unidades organizacionales' })

    await user.click(screen.getByRole('button', { name: 'Nueva unidad' }))

    const formulario = await screen.findByRole('form', { name: 'Nueva unidad' })
    expect(obtenerOpciones).toHaveBeenCalledWith(undefined)
    // El formulario va sobre la barra de filtros y el árbol.
    expect(formulario.compareDocumentPosition(screen.getByRole('table'))).toBe(Node.DOCUMENT_POSITION_FOLLOWING)

    await user.click(screen.getByRole('button', { name: 'Cancelar' }))
    expect(screen.queryByRole('form', { name: 'Nueva unidad' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Nueva unidad' })).toHaveFocus()
  })

  it('al guardar cierra el formulario, recarga el árbol y muestra "Unidad creada."', async () => {
    renderPage()
    const user = userEvent.setup()
    await screen.findByRole('table', { name: 'Unidades organizacionales' })
    await user.click(screen.getByRole('button', { name: 'Nueva unidad' }))
    await user.type(await screen.findByRole('textbox', { name: 'Nombre' }), 'Compras')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Unidad padre' }), '1')
    await user.selectOptions(screen.getByRole('combobox', { name: 'Tipo de unidad' }), '2')
    const lecturas = vi.mocked(listarUnidades).mock.calls.length

    await user.click(screen.getByRole('button', { name: 'Guardar unidad' }))

    expect(await screen.findByText('Unidad creada.')).toBeInTheDocument()
    expect(screen.queryByRole('form', { name: 'Nueva unidad' })).not.toBeInTheDocument()
    await waitFor(() => expect(vi.mocked(listarUnidades).mock.calls.length).toBeGreaterThan(lecturas))
  })
})
