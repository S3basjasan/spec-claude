// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { accion, compromiso, hito, opcionesGenerales, pdi, pdiAcordado, pdiParaElColaborador } from '../../../../test/pdiFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import {
  cambiarResponsable,
  cerrarPdi,
  confirmarAcuerdo,
  devolverABorrador,
  listarAccionesVinculables,
  listarHistorial,
  obtenerOpciones,
  obtenerPdi,
  obtenerPermisos,
  registrarAvanceDeAccion,
  registrarAvanceDeHito,
  vincularAccion,
  type ClavePermiso,
} from '../api'
import { DetalleDePdiPage } from './DetalleDePdiPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPdi: vi.fn(),
  obtenerPermisos: vi.fn(),
  obtenerOpciones: vi.fn(),
  confirmarAcuerdo: vi.fn(),
  devolverABorrador: vi.fn(),
  cambiarResponsable: vi.fn(),
  cerrarPdi: vi.fn(),
  registrarAvanceDeAccion: vi.fn(),
  registrarAvanceDeHito: vi.fn(),
  vincularAccion: vi.fn(),
  listarAccionesVinculables: vi.fn(),
  listarHistorial: vi.fn(),
}))

const ADMIN: ClavePermiso[] = ['desarrollo.pdi.gestionar', 'desarrollo.pdi.acordar', 'desarrollo.pdi.seguimiento', 'desarrollo.pdi.vincular', 'desarrollo.pdi.cerrar', 'desarrollo.pdi.gantt', 'desarrollo.pdi.ver']
const COLABORADOR: ClavePermiso[] = ['desarrollo.pdi.ver_propio', 'desarrollo.pdi.aceptar_propio']

function renderDetalle(entrada = '/pdi/1') {
  return renderRoutes(
    <>
      <Route path="/pdi/:id" element={<DetalleDePdiPage />} />
      <Route path="/pdi/:id/editar" element={<p>Editar plan</p>} />
      <Route path="/pdi" element={<p>Lista</p>} />
    </>,
    entrada,
  )
}

beforeEach(() => {
  vi.mocked(obtenerPermisos).mockReset().mockResolvedValue(ADMIN)
  vi.mocked(obtenerPdi).mockReset().mockResolvedValue(pdi())
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opcionesGenerales())
  vi.mocked(confirmarAcuerdo).mockReset().mockResolvedValue(pdi())
  vi.mocked(devolverABorrador).mockReset().mockResolvedValue(pdi())
  vi.mocked(cambiarResponsable).mockReset().mockResolvedValue(pdiAcordado())
  vi.mocked(cerrarPdi).mockReset()
  vi.mocked(registrarAvanceDeAccion).mockReset().mockResolvedValue(pdiAcordado())
  vi.mocked(registrarAvanceDeHito).mockReset().mockResolvedValue(pdiAcordado())
  vi.mocked(vincularAccion).mockReset().mockResolvedValue(pdiAcordado())
  vi.mocked(listarAccionesVinculables).mockReset().mockResolvedValue([])
  vi.mocked(listarHistorial).mockReset().mockResolvedValue({ data: [], meta: { page: 1, per_page: 15, total: 0, last_page: 1 } })
})

describe('Detalle del PDI (Administrador)', () => {
  it('un Borrador completo ofrece Editar y Confirmar acuerdo; confirmar envía la versión', async () => {
    const usuario = userEvent.setup()
    renderDetalle()

    expect(await screen.findByRole('heading', { level: 1, name: 'Diego Paredes' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Editar' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Cerrar' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Devolver a Borrador' })).not.toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Confirmar acuerdo' }))

    await waitFor(() => expect(confirmarAcuerdo).toHaveBeenCalledWith(1, 1))
    expect(await screen.findByText('Acuerdo confirmado. El colaborador ya puede ver el plan.')).toBeInTheDocument()
  })

  it('un Borrador incompleto lista lo que falta y no permite confirmar el acuerdo', async () => {
    vi.mocked(obtenerPdi).mockResolvedValue(pdi({ completitud: ['sin_compromisos'], compromisos: [] }))
    renderDetalle()

    expect(await screen.findByText('Agrega al menos un compromiso.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Confirmar acuerdo' })).toBeDisabled()
  })

  it('un Acordado ofrece devolver, cerrar y cambiar responsable, y no editar ni confirmar', async () => {
    vi.mocked(obtenerPdi).mockResolvedValue(pdiAcordado())
    renderDetalle()

    await screen.findByRole('heading', { level: 1, name: 'Diego Paredes' })
    expect(screen.getByRole('button', { name: 'Devolver a Borrador' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Cerrar' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Cambiar responsable' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Editar' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Confirmar acuerdo' })).not.toBeInTheDocument()
  })

  it('devolver a Borrador exige el motivo y lo envía con la versión', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerPdi).mockResolvedValue(pdiAcordado())
    renderDetalle()

    await usuario.click(await screen.findByRole('button', { name: 'Devolver a Borrador' }))
    const dialogo = await screen.findByRole('dialog')
    await usuario.click(within(dialogo).getByRole('button', { name: 'Devolver a Borrador' }))
    expect(await within(dialogo).findByText('Indica el motivo.')).toBeInTheDocument()
    expect(devolverABorrador).not.toHaveBeenCalled()

    await usuario.type(within(dialogo).getByLabelText('Motivo'), 'Cambió el alcance.')
    await usuario.click(within(dialogo).getByRole('button', { name: 'Devolver a Borrador' }))

    await waitFor(() => expect(devolverABorrador).toHaveBeenCalledWith(1, 'Cambió el alcance.', 3))
  })

  it('cerrar pide el resultado de cada compromiso, lo envía y ofrece generar una nueva adecuación en Carrera', async () => {
    const usuario = userEvent.setup()
    const carrera = pdiAcordado({ tipo: 'Carrera', puesto_objetivo: { id: 2, nombre: 'Analista de datos' }, compromisos: [compromiso({ acciones: [accion({ estado: 'Cumplida' })] })] })
    vi.mocked(obtenerPdi).mockResolvedValue(carrera)
    vi.mocked(cerrarPdi).mockResolvedValue({
      plan: { ...carrera, estado: 'Cerrado', resultado: 'BrechaCerrada', resultado_etiqueta: 'Brecha cerrada', cerrado_en: '2026-12-31T10:00:00+00:00', acciones_permitidas: { ...carrera.acciones_permitidas, cerrar: false } },
      resultado: 'BrechaCerrada',
      resultado_etiqueta: 'Brecha cerrada',
      ofrecer_adecuacion: { colaborador_id: 10, puesto_objetivo_id: 2 },
    })
    renderDetalle()

    await usuario.click(await screen.findByRole('button', { name: 'Cerrar' }))
    const dialogo = await screen.findByRole('dialog')
    await usuario.click(within(dialogo).getByRole('button', { name: 'Cerrar plan' }))
    expect(await within(dialogo).findByText('Indica el resultado de cada compromiso.', { selector: 'p, span, div' })).toBeInTheDocument()
    expect(cerrarPdi).not.toHaveBeenCalled()

    await usuario.selectOptions(within(dialogo).getByLabelText(/Resultado del compromiso 1/), 'Cerrada')
    await usuario.type(within(dialogo).getByLabelText('Conclusión (opcional)'), 'Se alcanzó el nivel B.')
    await usuario.click(within(dialogo).getByRole('button', { name: 'Cerrar plan' }))

    await waitFor(() => expect(cerrarPdi).toHaveBeenCalledWith(1, [{ compromiso_id: 10, resultado: 'Cerrada' }], 'Se alcanzó el nivel B.', 3))
  })

  it('el modal de cierre avisa de las acciones abiertas y bloquea el cierre', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerPdi).mockResolvedValue(pdiAcordado())
    renderDetalle()

    await usuario.click(await screen.findByRole('button', { name: 'Cerrar' }))
    const dialogo = await screen.findByRole('dialog')

    expect(within(dialogo).getByText('Quedan 1 acciones por cumplir o cancelar antes de cerrar.')).toBeInTheDocument()
    expect(within(dialogo).getByRole('button', { name: 'Cerrar plan' })).toBeDisabled()
  })

  it('un plan Cerrado no tiene botones de gestión, muestra el resultado y enlaza a la nueva adecuación en Talento', async () => {
    vi.mocked(obtenerPdi).mockResolvedValue(
      pdiAcordado({
        estado: 'Cerrado',
        tipo: 'Talento',
        puesto_objetivo: { id: 3, nombre: 'Analista senior' },
        resultado: 'Parcial',
        resultado_etiqueta: 'Parcial',
        conclusion: 'Faltó experiencia.',
        cerrado_en: '2026-12-31T10:00:00+00:00',
        acciones_permitidas: { editar: false, acordar: false, devolver: false, avance: false, vincular: false, cerrar: false, cambiar_responsable: false, aceptar: false, devolver_colaborador: false },
      }),
    )
    renderDetalle()

    const panel = await screen.findByRole('region', { name: 'Resultado del cierre' })
    expect(within(panel).getByText('Faltó experiencia.')).toBeInTheDocument()
    expect(within(panel).getByRole('link', { name: 'Generar nueva adecuación' })).toHaveAttribute('href', '/adecuacion?colaborador=10&puesto=3')
    for (const nombre of ['Editar', 'Confirmar acuerdo', 'Devolver a Borrador', 'Cerrar', 'Cambiar responsable']) {
      expect(screen.queryByRole('button', { name: nombre })).not.toBeInTheDocument()
    }
  })

  it('en Mejora no ofrece generar una nueva adecuación', async () => {
    vi.mocked(obtenerPdi).mockResolvedValue(pdiAcordado({ estado: 'Cerrado', resultado: 'Parcial', cerrado_en: '2026-12-31T10:00:00+00:00' }))
    renderDetalle()

    await screen.findByRole('region', { name: 'Resultado del cierre' })
    expect(screen.queryByRole('link', { name: 'Generar nueva adecuación' })).not.toBeInTheDocument()
  })

  it('cambiar el responsable envía el nuevo responsable y la versión', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerPdi).mockResolvedValue(pdiAcordado())
    renderDetalle()

    await usuario.click(await screen.findByRole('button', { name: 'Cambiar responsable' }))
    const dialogo = await screen.findByRole('dialog')
    await usuario.selectOptions(within(dialogo).getByLabelText('Nuevo responsable'), '7')
    await usuario.click(within(dialogo).getByRole('button', { name: 'Cambiar' }))

    await waitFor(() => expect(cambiarResponsable).toHaveBeenCalledWith(1, 7, 3))
  })

  it('en la pestaña Compromisos el estado de una acción sin vínculo se registra a mano y el de una vinculada es de solo lectura', async () => {
    const usuario = userEvent.setup()
    const plan = pdiAcordado({
        compromisos: [
          compromiso({
            acciones: [
              accion({ id: 100 }),
              accion({
                id: 101,
                descripcion: 'Taller vinculado',
                estado: 'Cumplida',
                estado_derivado: true,
                vinculo: { plan_accion_id: 55, plan_codigo: 'PF-0001', plan_nombre: 'Plan 2026', accion_nombre: 'Taller de liderazgo', actividad_id: 91, requiere_revision: true },
              }),
            ],
          }),
        ],
      })
    vi.mocked(obtenerPdi).mockResolvedValue(plan)
    vi.mocked(registrarAvanceDeAccion).mockResolvedValue(plan)
    renderDetalle('/pdi/1?pestana=compromisos')

    const selectores = await screen.findAllByLabelText('Estado de la acción')
    expect(selectores).toHaveLength(1)
    await usuario.selectOptions(selectores[0], 'Cumplida')
    await waitFor(() => expect(registrarAvanceDeAccion).toHaveBeenCalledWith(100, 'Cumplida'))

    expect(screen.getByText('Requiere revisión')).toBeInTheDocument()
    expect(screen.getByText(/Taller de liderazgo/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver actividad' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Desvincular' })).toBeInTheDocument()
  })

  it('vincular a Plan de Formación solo se ofrece en Curso, Mentoring y Codesarrollo y envía la acción elegida', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerPdi).mockResolvedValue(
      pdiAcordado({ compromisos: [compromiso({ acciones: [accion({ id: 100, tipo: 'Curso' }), accion({ id: 102, tipo: 'Coaching', descripcion: 'Sesiones' })] })] }),
    )
    vi.mocked(listarAccionesVinculables).mockResolvedValue([
      { plan_accion_id: 55, plan_id: 3, plan_codigo: 'PF-0001', plan_nombre: 'Plan 2026', plan_estado: 'Ejecutando', nombre: 'Taller de liderazgo', tipo: 'Presencial', fecha_inicio: '2026-08-01', fecha_fin: '2026-08-10', formador: null },
    ])
    renderDetalle('/pdi/1?pestana=compromisos')

    const botones = await screen.findAllByRole('button', { name: 'Vincular a Plan de Formación' })
    expect(botones).toHaveLength(1)
    await usuario.click(botones[0])
    const dialogo = await screen.findByRole('dialog')
    await usuario.click(await within(dialogo).findByRole('button', { name: /Vincular: Taller de liderazgo/ }))

    await waitFor(() => expect(vincularAccion).toHaveBeenCalledWith(100, 55, null))
  })

  it('en la pestaña Hitos se marca un hito como Realizado con observaciones', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerPdi).mockResolvedValue(pdiAcordado({ hitos: [hito({ vencido: true })] }))
    renderDetalle('/pdi/1?pestana=hitos')

    expect(await screen.findByText('Vencido')).toBeInTheDocument()
    await usuario.selectOptions(screen.getByLabelText('Estado del hito'), 'Realizado')
    await usuario.type(screen.getByLabelText('Observaciones'), 'Hecho')
    await usuario.click(screen.getByRole('button', { name: 'Guardar hito' }))

    await waitFor(() => expect(registrarAvanceDeHito).toHaveBeenCalledWith(50, 'Realizado', 'Hecho'))
  })

  it('en un Borrador los hitos y las acciones no ofrecen registrar avance', async () => {
    renderDetalle('/pdi/1?pestana=hitos')

    await screen.findByText('Primera revisión de avance', { exact: false })
    expect(screen.queryByRole('button', { name: 'Guardar hito' })).not.toBeInTheDocument()
  })

  it('la pestaña Historial lista los eventos con usuario, valores y motivo', async () => {
    vi.mocked(listarHistorial).mockResolvedValue({
      data: [{ id: 1, fecha: '2026-06-10 09:00:00', usuario: 'Marta Ríos', tipo: 'Devolucion', tipo_etiqueta: 'Devolución', referencia_id: null, valor_anterior: 'Acordado', valor_nuevo: 'Borrador', motivo: 'Cambió el alcance.' }],
      meta: { page: 1, per_page: 15, total: 1, last_page: 1 },
    })
    renderDetalle('/pdi/1?pestana=historial')

    const tabla = await screen.findByRole('table')
    expect(within(tabla).getByText('Marta Ríos')).toBeInTheDocument()
    expect(within(tabla).getByText('Cambió el alcance.')).toBeInTheDocument()
    expect(within(tabla).getByText('Devolución')).toBeInTheDocument()
  })

  it('un conflicto de versión (409) avisa que hay que recargar', async () => {
    const usuario = userEvent.setup()
    vi.mocked(confirmarAcuerdo).mockRejectedValue({ isAxiosError: true, response: { status: 409, data: { message: 'cambió', code: 'version_desfasada' } } })
    renderDetalle()

    await usuario.click(await screen.findByRole('button', { name: 'Confirmar acuerdo' }))

    expect(await screen.findByText(/El plan cambió mientras lo revisabas/)).toBeInTheDocument()
  })

  it('señala al responsable que ya no es administrador', async () => {
    vi.mocked(obtenerPdi).mockResolvedValue(pdiAcordado({ responsable: { id: 55, nombre: 'Luis Barragán', sin_permiso: true } }))
    renderDetalle()

    expect(await screen.findByText('El responsable ya no es administrador: reasigna el plan.')).toBeInTheDocument()
  })

  it('un plan inexistente muestra «No se encontró el plan»', async () => {
    vi.mocked(obtenerPdi).mockRejectedValue({ isAxiosError: true, response: { status: 404, data: {} } })
    renderDetalle()

    expect(await screen.findByText('No se encontró el plan.')).toBeInTheDocument()
  })
})

describe('Detalle del PDI (Colaborador)', () => {
  it('con solo ver_propio muestra su vista de solo lectura sin pestañas de gestión', async () => {
    vi.mocked(obtenerPermisos).mockResolvedValue(COLABORADOR)
    vi.mocked(obtenerPdi).mockResolvedValue(pdiParaElColaborador())
    renderDetalle()

    expect(await screen.findByRole('button', { name: 'Aceptar plan' })).toBeDisabled()
    expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Editar' })).not.toBeInTheDocument()
  })

  it('sin ningún permiso del PDI muestra «No tienes acceso»', async () => {
    vi.mocked(obtenerPermisos).mockResolvedValue([])
    renderDetalle()

    expect(await screen.findByText('No tienes acceso')).toBeInTheDocument()
  })
})
