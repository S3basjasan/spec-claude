// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle, tarea } from '../../../../test/offboardingFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { completarTarea, consultarRespuestas, ejecutarAccion, listarRespaldos, obtenerSalida, type DetalleDeSalida } from '../api'
import { ExpedientePage } from './ExpedientePage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerSalida: vi.fn(),
  ejecutarAccion: vi.fn(),
  completarTarea: vi.fn(),
  listarRespaldos: vi.fn(),
  consultarRespuestas: vi.fn(),
}))

function renderPage(id = 5) {
  return renderRoutes(<Route path="/offboarding/:id" element={<ExpedientePage />} />, `/offboarding/${id}`)
}

function mostrar(datos: DetalleDeSalida) {
  vi.mocked(obtenerSalida).mockReset().mockResolvedValue(datos)
}

const efectiva = (cambios: Partial<DetalleDeSalida> = {}) =>
  detalle({
    estado: 'Efectiva',
    version: 4,
    aplicacion: { aplicada_en: '2026-11-10T05:00:00Z', por_sistema: true, intentos: 1, incidencia: null },
    acciones: { cancelar: false, aplicar: false, completar_tareas: true, cerrar: false, no_realizada: true },
    cierre: { estado: 'Pendiente', por: null, en: null, bloqueos: ['TAREAS_PENDIENTES', 'ENTREVISTA_SIN_RESOLVER'] },
    ...cambios,
  })

beforeEach(() => {
  vi.mocked(ejecutarAccion).mockReset().mockResolvedValue(detalle({ version: 2 }))
  vi.mocked(completarTarea).mockReset().mockResolvedValue(detalle({ version: 2 }))
  vi.mocked(listarRespaldos).mockReset().mockResolvedValue([{ id: 9, titulo: 'Acta de devolución' }])
  vi.mocked(consultarRespuestas).mockReset()
  mostrar(detalle())
})

describe('ExpedientePage', () => {
  it('muestra el expediente, el checklist con la entrevista como tarea y la política conservada (CA-02)', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Salida de Ana Núñez' })).toBeInTheDocument()
    expect(screen.getByText(/Política de offboarding, versión 2 \(conservada\)/)).toBeInTheDocument()
    const tabla = screen.getByRole('table', { name: 'Tareas del checklist' })
    expect(within(tabla).getByText('Devolver equipos')).toBeInTheDocument()
    expect(within(tabla).getByText('Entrevista de salida')).toBeInTheDocument()
    expect(within(tabla).getByText(/Se resuelve con la entrevista de salida/)).toBeInTheDocument()
    expect(within(tabla).getAllByText('Pendiente')).toHaveLength(3)
    expect(screen.getByText('La salida se aplicará el 2026-11-10. Hasta entonces el vínculo sigue vigente.')).toBeInTheDocument()
  })

  it('la tarea con evidencia exige un respaldo y envía versión, respaldos y clave (CA-04)', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Completar la tarea «Devolver equipos»' }))
    await user.click(await screen.findByRole('button', { name: 'Registrar cumplimiento' }))
    expect(await screen.findByText('Esta tarea exige al menos un respaldo.')).toBeInTheDocument()
    expect(completarTarea).not.toHaveBeenCalled()

    await user.click(await screen.findByRole('checkbox', { name: 'Acta de devolución' }))
    await user.click(screen.getByRole('button', { name: 'Registrar cumplimiento' }))

    expect(completarTarea).toHaveBeenCalledWith(5, 1, { version: 1, documento_ids: [9] }, expect.stringMatching(/^[0-9a-f-]{36}$/))
  })

  it('una tarea sin evidencia obligatoria se completa sin respaldo y no hay botón para la tarea de entrevista', async () => {
    const user = userEvent.setup()
    renderPage()

    expect(screen.queryByRole('button', { name: /Completar la tarea «Entrevista de salida»/ })).not.toBeInTheDocument()
    await user.click(await screen.findByRole('button', { name: 'Completar la tarea «Cerrar accesos»' }))
    await user.click(await screen.findByRole('button', { name: 'Registrar cumplimiento' }))

    expect(completarTarea).toHaveBeenCalledWith(5, 2, { version: 1, documento_ids: [] }, expect.any(String))
  })

  it('las tareas pendientes siguen disponibles después de la salida efectiva y el cierre es independiente (CA-05, CA-08)', async () => {
    mostrar(efectiva())
    renderPage()

    expect(await screen.findByText(/La salida es efectiva desde el 2026-11-10 \(aplicada automáticamente\)/)).toBeInTheDocument()
    expect(screen.getByText('Las tareas pendientes siguen disponibles después de la fecha efectiva.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Completar la tarea «Devolver equipos»' })).toBeInTheDocument()
    expect(screen.getByText(/son independientes/)).toBeInTheDocument()
  })

  it('el cierre muestra qué falta y no se puede confirmar hasta que todo esté resuelto (CA-08)', async () => {
    mostrar(efectiva())
    renderPage()

    expect(await screen.findByText('Hay tareas requeridas sin completar.')).toBeInTheDocument()
    expect(screen.getByText(/debe completarse o registrarse como no realizada/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Confirmar cierre' })).toBeDisabled()
  })

  it('confirma el cierre con la versión vista cuando no hay bloqueos', async () => {
    const user = userEvent.setup()
    mostrar(efectiva({ acciones: { cancelar: false, aplicar: false, completar_tareas: true, cerrar: true, no_realizada: false }, cierre: { estado: 'Pendiente', por: null, en: null, bloqueos: [] } }))
    renderPage()

    expect(await screen.findByText('Todo está resuelto: puedes confirmar el cierre.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Confirmar cierre' }))
    await user.click(await screen.findByRole('button', { name: 'Confirmar el cierre' }))

    expect(ejecutarAccion).toHaveBeenCalledWith(5, 'cierre', { version: 4 }, expect.any(String))
  })

  it('un cierre ya confirmado se informa y no ofrece confirmar de nuevo', async () => {
    mostrar(efectiva({ cierre: { estado: 'Cerrado', por: { id: 9, nombre: 'Rosa Vera' }, en: '2026-11-12T15:00:00Z', bloqueos: [] }, acciones: { cancelar: false, aplicar: false, completar_tareas: false, cerrar: false, no_realizada: false } }))
    renderPage()

    expect(await screen.findByText(/Cierre confirmado por Rosa Vera/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Confirmar cierre' })).not.toBeInTheDocument()
  })

  it('una incidencia de aplicación no se muestra como aplicada y ofrece reintentar, no forzar (RN incidencia)', async () => {
    const user = userEvent.setup()
    mostrar(
      detalle({
        estado: 'IncidenciaDeAplicacion',
        aplicacion: { aplicada_en: null, por_sistema: false, intentos: 1, incidencia: { codigo: 'VINCULO_NO_VIGENTE', detalle: 'x' } },
        acciones: { cancelar: true, aplicar: true, completar_tareas: true, cerrar: false, no_realizada: true },
      }),
    )
    vi.mocked(ejecutarAccion).mockRejectedValue(httpError(409, { code: 'INCIDENCIA_DE_APLICACION', incidencia: { codigo: 'VINCULO_NO_VIGENTE', detalle: 'x' } }))
    renderPage()

    expect(await screen.findByText(/No se pudo aplicar la salida: una condición actual lo impide/)).toBeInTheDocument()
    expect(screen.getByText('El colaborador ya no tiene un vínculo laboral vigente.')).toBeInTheDocument()
    expect(screen.queryByText(/es efectiva desde/)).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Reintentar la aplicación' }))
    await user.click(await screen.findByRole('button', { name: 'Aplicar la salida' }))

    expect(ejecutarAccion).toHaveBeenCalledWith(5, 'aplicacion', { version: 1 }, expect.any(String))
    expect(await screen.findByText('La condición que impedía aplicar la salida sigue sin cumplirse. No se hizo ningún cambio.')).toBeInTheDocument()
  })

  it('la cuenta se presenta pendiente de verificar, nunca como deshabilitada (RN cuenta)', async () => {
    mostrar(efectiva({ cuenta_estado: 'Pendiente' }))
    renderPage()

    expect(await screen.findByText('Tratamiento de la cuenta pendiente de verificar')).toBeInTheDocument()
    expect(screen.queryByText(/deshabilitada/i)).toBeInTheDocument() // solo en la nota que aclara que NO se afirma
    expect(screen.getByText(/no significa que la cuenta ya esté deshabilitada/)).toBeInTheDocument()
  })

  it('cancelar exige una razón y envía la versión (HU-01)', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Cancelar salida' }))
    await user.click(await screen.findByRole('button', { name: 'Cancelar la salida' }))
    expect(await screen.findByText('Indica la razón de la cancelación.')).toBeInTheDocument()
    expect(ejecutarAccion).not.toHaveBeenCalled()

    await user.type(screen.getByLabelText('Razón de la cancelación'), 'Se retiró la renuncia')
    await user.click(screen.getByRole('button', { name: 'Cancelar la salida' }))

    expect(ejecutarAccion).toHaveBeenCalledWith(5, 'cancelacion', { version: 1, razon: 'Se retiró la renuncia' }, expect.any(String))
  })

  it('registrar la entrevista como no realizada envía la versión de la entrevista y el motivo', async () => {
    const user = userEvent.setup()
    mostrar(detalle({ version: 6, entrevista: { estado: 'Pendiente', version: 3, presentada_en: null, motivo_no_realizada: null, no_realizada_en: null } }))
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Registrar como no realizada' }))
    await user.selectOptions(await screen.findByLabelText('Motivo'), 'SinAcceso')
    await user.click(screen.getAllByRole('button', { name: 'Registrar como no realizada' }).at(-1) as HTMLElement)

    expect(ejecutarAccion).toHaveBeenCalledWith(5, 'entrevista/no-realizada', { version: 3, motivo: 'SinAcceso' }, expect.any(String))
  })

  it('un conflicto de versión se explica, conserva el diálogo cerrado y ofrece actualizar (RN concurrencia)', async () => {
    const user = userEvent.setup()
    vi.mocked(ejecutarAccion).mockRejectedValue(httpError(409, { code: 'EXPEDIENTE_MODIFICADO' }))
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Cancelar salida' }))
    await user.type(await screen.findByLabelText('Razón de la cancelación'), 'x')
    await user.click(screen.getByRole('button', { name: 'Cancelar la salida' }))

    expect(await screen.findByText('El expediente cambió')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Actualizar datos' })).toBeInTheDocument()
    expect(ejecutarAccion).toHaveBeenCalledTimes(1)
  })

  it('las respuestas originales se piden solo al abrirlas y sin el permiso específico se explica la negativa', async () => {
    const user = userEvent.setup()
    mostrar(detalle({ entrevista: { estado: 'Completada', version: 3, presentada_en: '2026-10-30T10:00:00Z', motivo_no_realizada: null, no_realizada_en: null } }))
    vi.mocked(consultarRespuestas).mockRejectedValue(httpError(403, { code: 'SIN_PERMISO_DE_ENTREVISTAS' }))
    renderPage()

    await screen.findByRole('heading', { name: 'Salida de Ana Núñez' })
    expect(consultarRespuestas).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Ver respuestas originales' }))

    expect(await screen.findByText('No tienes el permiso específico para consultar las respuestas de las entrevistas de salida.')).toBeInTheDocument()
    expect(consultarRespuestas).toHaveBeenCalledTimes(1)
  })

  it('con permiso muestra las respuestas tal como se dieron y distingue lo no respondido', async () => {
    const user = userEvent.setup()
    mostrar(detalle({ entrevista: { estado: 'Completada', version: 3, presentada_en: '2026-10-30T10:00:00Z', motivo_no_realizada: null, no_realizada_en: null } }))
    vi.mocked(consultarRespuestas).mockResolvedValue({
      estado: 'Completada',
      disponible: true,
      cuestionario_version: 1,
      cuestionario: {
        version: 1,
        escala: { '2': 'Insatisfecho' },
        dimensiones: { 'DIM-DESARROLLO': { nombre: 'Desarrollo profesional', pregunta: 'p' }, 'DIM-GENERAL': { nombre: 'Experiencia general', pregunta: 'p' } },
        categorias: {},
        abiertas: { 'ABI-01': '¿Cuáles fueron los principales factores?' },
      },
      respuestas: { valoraciones: { 'DIM-DESARROLLO': { dimension: 'Desarrollo profesional', valor: 2 } }, categorias: [{ id: 'CAT-COMPENSACION', nombre: 'Compensación' }], abiertas: { 'ABI-01': 'Busqué crecer.' } },
    })
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Ver respuestas originales' }))

    expect(await screen.findByText('2 · Insatisfecho')).toBeInTheDocument()
    expect(screen.getByText('Sin responder')).toBeInTheDocument()
    expect(screen.getByText('Compensación')).toBeInTheDocument()
    expect(screen.getByText('Busqué crecer.')).toBeInTheDocument()
    expect(screen.getByText(/declaraciones del colaborador, no causas comprobadas/)).toBeInTheDocument()
  })

  it('un expediente inexistente o ajeno se muestra como no disponible', async () => {
    vi.mocked(obtenerSalida).mockReset().mockRejectedValue(httpError(404, { code: 'EXPEDIENTE_NO_ENCONTRADO' }))
    renderPage(99)

    expect(await screen.findByText('Expediente no disponible')).toBeInTheDocument()
  })

  it('muestra el historial con el actor y distingue al sistema', async () => {
    mostrar(efectiva({ historial: [{ evento: 'Aplicada', actor: { tipo: 'sistema', id: null, nombre: 'Sistema' }, detalle: null, en: '2026-11-10T05:00:00Z' }] }))
    renderPage()

    const historial = await screen.findByRole('region', { name: 'Historial' })
    expect(within(historial).getByText('Salida aplicada')).toBeInTheDocument()
    expect(within(historial).getByText('Sistema')).toBeInTheDocument()
  })
})

describe('tareas completadas', () => {
  it('muestra quién y cuándo, y marca un respaldo que ya no está disponible', async () => {
    mostrar(
      detalle({
        tareas: [
          tarea({ estado: 'Completada', completada_por: { id: 9, nombre: 'Rosa Vera' }, completada_en: '2026-10-27T10:00:00Z', respaldos: [{ id: 9, disponible: false }] }),
          tarea({ id: 3, orden: 2, tipo: 'Entrevista', nombre: 'Entrevista de salida', exige_evidencia: false, descripcion: null }),
        ],
      }),
    )
    renderPage()

    expect(await screen.findByText('Respaldo no disponible')).toBeInTheDocument()
    expect(screen.getByText(/Rosa Vera ·/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Completar la tarea «Devolver equipos»/ })).not.toBeInTheDocument()
  })
})
