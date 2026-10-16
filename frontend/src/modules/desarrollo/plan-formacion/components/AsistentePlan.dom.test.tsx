// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { accion, opcionesPlan, paginaColaboradores, plan } from '../../../../test/planFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarPlan, buscarColaboradores, crearPlan, listarCursos, obtenerOpciones } from '../api'
import { datosVacios } from '../asistente'
import { AsistentePlan } from './AsistentePlan'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  crearPlan: vi.fn(),
  actualizarPlan: vi.fn(),
  obtenerOpciones: vi.fn(),
  buscarColaboradores: vi.fn(),
  listarCursos: vi.fn(),
}))

function renderAsistente(props: Partial<Parameters<typeof AsistentePlan>[0]> = {}) {
  const onTerminar = vi.fn()
  renderRoutes(<Route path="/" element={<AsistentePlan inicial={datosVacios()} nombresExtra={{}} onTerminar={onTerminar} {...props} />} />, '/')
  return { onTerminar }
}

async function llenarPaso1(nombre = 'Plan técnico', anio = '2026') {
  const usuario = userEvent.setup()
  await usuario.type(screen.getByRole('textbox', { name: 'Nombre del plan' }), nombre)
  const campoAnio = screen.getByRole('textbox', { name: 'Año' })
  await usuario.clear(campoAnio)
  await usuario.type(campoAnio, anio)
  return usuario
}

async function agregarAccion(usuario: ReturnType<typeof userEvent.setup>, nombre: string, extra?: (u: typeof usuario) => Promise<void>) {
  await usuario.click(screen.getByRole('button', { name: 'Agregar acción' }))
  const dialogo = await screen.findByRole('dialog')
  await usuario.type(within(dialogo).getByRole('textbox', { name: 'Nombre de la acción' }), nombre)
  await usuario.type(within(dialogo).getByRole('combobox', { name: 'Competencias objetivo' }), 'Anál')
  await usuario.click(await within(dialogo).findByRole('option', { name: /Análisis de datos/ }))
  if (extra) await extra(usuario)
  await usuario.click(within(dialogo).getByRole('button', { name: 'Guardar acción' }))
  await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
}

beforeEach(() => {
  vi.mocked(crearPlan).mockReset().mockResolvedValue(plan())
  vi.mocked(actualizarPlan).mockReset().mockResolvedValue(plan())
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opcionesPlan())
  vi.mocked(buscarColaboradores).mockReset().mockResolvedValue(paginaColaboradores())
  vi.mocked(listarCursos)
    .mockReset()
    .mockResolvedValue([{ id: 3, nombre: 'SQL para analistas', tipo_sugerido: 'ELearning', formador: 'Ing. Lucía Vera', duracion_horas: 24, competencia_ids: [1, 99] }])
})

describe('AsistentePlan', () => {
  it('exige nombre y año en el paso 1 y no avanza', async () => {
    renderAsistente()
    const usuario = userEvent.setup()
    await usuario.clear(screen.getByRole('textbox', { name: 'Año' }))

    await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))

    expect(await screen.findByText('El nombre es obligatorio.')).toBeInTheDocument()
    expect(screen.getByText('El año es obligatorio.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Datos generales' })).toHaveAttribute('aria-current', 'step')
  })

  it('crea el plan en Borrador recorriendo los 3 pasos con una acción solo con nombre y competencia', async () => {
    const { onTerminar } = renderAsistente()
    const usuario = await llenarPaso1('Plan técnico', '2026')
    await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))

    expect(screen.getByText('Aún no hay acciones. Agrega al menos una para continuar con los participantes.')).toBeInTheDocument()
    await agregarAccion(usuario, 'Taller de SQL')
    expect(screen.getByText('Taller de SQL')).toBeInTheDocument()
    expect(screen.getByText('Incompleta')).toBeInTheDocument()

    await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))
    await usuario.click(await screen.findByRole('button', { name: 'Agregar participante: Ana Pérez' }))
    expect(screen.getByText('Capacidad usada: 1/sin definir')).toBeInTheDocument()
    await usuario.selectOptions(screen.getByRole('combobox', { name: 'Aprobador del plan' }), 'Marta Ríos')
    await usuario.click(screen.getByRole('button', { name: 'Crear plan en Borrador' }))

    await waitFor(() => expect(onTerminar).toHaveBeenCalledTimes(1))
    expect(vi.mocked(crearPlan).mock.calls[0][0]).toEqual({
      nombre: 'Plan técnico',
      anio: 2026,
      descripcion: null,
      aprobador_id: 5,
      acciones: [
        {
          nombre: 'Taller de SQL',
          curso_id: null,
          tipo: null,
          fecha_inicio: null,
          fecha_fin: null,
          duracion_horas: null,
          formador: null,
          capacidad_maxima: null,
          costo: null,
          competencia_ids: [1],
          colaborador_ids: [10],
        },
      ],
    })
  })

  it('guarda como Borrador desde el paso 1 y las siguientes veces actualiza el mismo plan', async () => {
    renderAsistente()
    const usuario = await llenarPaso1()

    await usuario.click(screen.getByRole('button', { name: 'Guardar borrador' }))
    expect(await screen.findByText('Plan guardado en Borrador.')).toBeInTheDocument()
    expect(crearPlan).toHaveBeenCalledTimes(1)

    await usuario.click(screen.getByRole('button', { name: 'Guardar borrador' }))
    await waitFor(() => expect(actualizarPlan).toHaveBeenCalledTimes(1))
    expect(vi.mocked(actualizarPlan).mock.calls[0][0]).toBe(1)
  })

  it('el curso del catálogo propone tipo, formador, horas y solo las competencias activas', async () => {
    renderAsistente()
    const usuario = await llenarPaso1()
    await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))
    await usuario.click(screen.getByRole('button', { name: 'Agregar acción' }))
    const dialogo = await screen.findByRole('dialog')

    await usuario.type(within(dialogo).getByRole('combobox', { name: /Partir de un curso/ }), 'SQL')
    await usuario.click(await within(dialogo).findByRole('option', { name: /SQL para analistas/ }))

    expect(within(dialogo).getByRole('textbox', { name: 'Nombre de la acción' })).toHaveValue('SQL para analistas')
    expect(within(dialogo).getByRole('combobox', { name: 'Tipo' })).toHaveValue('ELearning')
    expect(within(dialogo).getByRole('textbox', { name: 'Formador' })).toHaveValue('Ing. Lucía Vera')
    expect(within(dialogo).getByRole('textbox', { name: 'Duración en horas' })).toHaveValue('24')
    expect(within(dialogo).getByRole('list', { name: 'Competencias objetivo' })).toHaveTextContent('Análisis de datos')
    expect(within(dialogo).queryByText(/#99/)).not.toBeInTheDocument()

    // Todo lo propuesto se puede modificar.
    await usuario.selectOptions(within(dialogo).getByRole('combobox', { name: 'Tipo' }), 'Mentoring')
    expect(within(dialogo).getByRole('combobox', { name: 'Tipo' })).toHaveValue('Mentoring')
  })

  it('valida fechas y capacidad de la acción sin cerrar el formulario', async () => {
    renderAsistente()
    const usuario = await llenarPaso1()
    await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))
    await usuario.click(screen.getByRole('button', { name: 'Agregar acción' }))
    const dialogo = await screen.findByRole('dialog')
    await usuario.type(within(dialogo).getByRole('textbox', { name: 'Nombre de la acción' }), 'Taller')
    await usuario.type(within(dialogo).getByRole('combobox', { name: 'Competencias objetivo' }), 'Líd')
    await usuario.click(await within(dialogo).findByRole('option', { name: /Liderazgo/ }))
    await usuario.type(within(dialogo).getByLabelText('Fecha de inicio'), '2025-12-30')
    await usuario.type(within(dialogo).getByRole('textbox', { name: 'Capacidad máxima' }), '0')

    await usuario.click(within(dialogo).getByRole('button', { name: 'Guardar acción' }))

    expect(await within(dialogo).findByText('Las fechas deben estar dentro de 2026.')).toBeInTheDocument()
    expect(within(dialogo).getByText('La capacidad debe ser un entero mayor que cero.')).toBeInTheDocument()
  })

  it('pide confirmación antes de quitar una acción', async () => {
    renderAsistente({ inicial: { ...datosVacios(), nombre: 'Plan', anio: '2026' } })
    const usuario = userEvent.setup()
    await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))
    await agregarAccion(usuario, 'Taller de SQL')

    await usuario.click(screen.getByRole('button', { name: 'Quitar Taller de SQL' }))
    const confirmacion = await screen.findByRole('alertdialog')
    await usuario.click(within(confirmacion).getByRole('button', { name: 'Cancelar' }))
    expect(screen.getByText('Taller de SQL')).toBeInTheDocument()

    await usuario.click(screen.getByRole('button', { name: 'Quitar Taller de SQL' }))
    await usuario.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Quitar acción' }))
    await waitFor(() => expect(screen.queryByText('Taller de SQL')).not.toBeInTheDocument())
  })

  it('bloquea agregar participantes al llegar a la capacidad y avisa de los cruces sin impedirlos', async () => {
    const inicial = {
      ...datosVacios(),
      nombre: 'Plan',
      anio: '2026',
      acciones: [
        { clave: 'a1', datos: { ...accion(), curso_id: null, competencia_ids: [1], capacidad_maxima: 1, fecha_inicio: '2026-03-01', fecha_fin: '2026-03-05' }, participantes: [{ id: 10, nombre: 'Ana Pérez' }] },
        { clave: 'a2', datos: { ...accion(), nombre: 'Otra acción', curso_id: null, competencia_ids: [1], capacidad_maxima: 5, fecha_inicio: '2026-03-04', fecha_fin: '2026-03-08' }, participantes: [{ id: 10, nombre: 'Ana Pérez' }] },
      ],
    }
    renderAsistente({ inicial, planId: 1 })
    const usuario = userEvent.setup()
    await usuario.click(screen.getByRole('button', { name: 'Participantes y aprobador' }))

    const primera = await screen.findByRole('article', { name: 'Análisis con SQL' })
    expect(within(primera).getByText(/Capacidad usada: 1\/1/)).toHaveTextContent('Capacidad completa')
    expect(within(primera).getByRole('searchbox', { name: 'Buscar colaborador' })).toBeDisabled()
    expect(within(primera).queryByRole('button', { name: /Agregar participante/ })).not.toBeInTheDocument()
    expect(within(primera).getByText(/Se cruza con «Otra acción»/)).toBeInTheDocument()
    expect(within(await screen.findByRole('article', { name: 'Otra acción' })).getByText(/Se cruza con «Análisis con SQL»/)).toBeInTheDocument()
  })

  it('muestra el error del servidor por campo y vuelve al paso 1', async () => {
    vi.mocked(crearPlan).mockRejectedValue(httpError(422, { message: 'Ya existe un plan con ese nombre en el año.', code: 'nombre_duplicado', errors: { nombre: ['Ya existe un plan con ese nombre en el año.'] } }))
    renderAsistente()
    const usuario = await llenarPaso1()

    await usuario.click(screen.getByRole('button', { name: 'Guardar borrador' }))

    expect(await screen.findByText('Ya existe un plan con ese nombre en el año.')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Nombre del plan' })).toHaveValue('Plan técnico')
  })
})
