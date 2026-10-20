// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { accionDisponible, detalle, opciones, resumen } from '../../../../test/actividadesFixtures'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { crearActividad, listarAccionesDisponibles, listarActividades, listarCalificacionesPorActividad, listarCalificacionesPorParticipante, obtenerActividad, obtenerDetalleParticipante, obtenerOpciones } from '../api'
import { rangoDelAnio } from '../hooks/useActividadesParams'
import { VistaAdministrador } from './VistaAdministrador'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarActividades: vi.fn(),
  obtenerOpciones: vi.fn(),
  obtenerActividad: vi.fn(),
  listarAccionesDisponibles: vi.fn(),
  crearActividad: vi.fn(),
  listarCalificacionesPorActividad: vi.fn(),
  listarCalificacionesPorParticipante: vi.fn(),
  obtenerDetalleParticipante: vi.fn(),
}))

const meta = (total: number, page = 1) => ({ page, per_page: 10, total, last_page: Math.max(1, Math.ceil(total / 10)) })

function renderVista(entrada = '/actividades') {
  return renderRoutes(<Route path="/actividades" element={<VistaAdministrador />} />, entrada)
}

beforeEach(() => {
  vi.mocked(listarActividades)
    .mockReset()
    .mockResolvedValue({
      data: [
        resumen(),
        resumen({ id: 2, codigo: 'ACT-0002', nombre: 'Excel avanzado', tipo: 'Virtual', lugar_enlace: 'https://meet.ejemplo.edu.ec/excel', estado: 'Finalizada', asistencia_pendiente: true, departamento: null }),
      ],
      meta: meta(2),
    })
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opciones())
  vi.mocked(obtenerActividad).mockReset().mockResolvedValue(detalle())
  vi.mocked(listarAccionesDisponibles).mockReset().mockResolvedValue({ data: [accionDisponible()], meta: { page: 1, per_page: 50, total: 1, last_page: 1 } })
  vi.mocked(crearActividad).mockReset().mockResolvedValue(detalle({ id: 9, codigo: 'ACT-0009' }))
  vi.mocked(listarCalificacionesPorActividad).mockReset().mockResolvedValue({ data: [], meta: meta(0) })
  vi.mocked(listarCalificacionesPorParticipante).mockReset().mockResolvedValue({ data: [], meta: meta(0) })
  vi.mocked(obtenerDetalleParticipante).mockReset()
})

describe('VistaAdministrador — lista de actividades', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(listarActividades).mockReturnValue(new Promise(() => {}))
    renderVista()

    expect(screen.getByRole('status', { name: 'Actividades formativas' })).toBeInTheDocument()
  })

  it('muestra la tabla con código, nombre, tipo, fechas, lugar, formador, departamento, capacidad, inscritos y estado', async () => {
    renderVista()

    const tabla = await screen.findByRole('table', { name: 'Actividades formativas' })
    expect(within(tabla).getAllByRole('columnheader').map((c) => c.textContent)).toEqual(['Código', 'Nombre', 'Tipo', 'Fechas', 'Lugar / Enlace', 'Formador', 'Departamento', 'Capacidad', 'Inscritos', 'Estado', 'Acciones'])
    const fila = within(tabla).getByText('Liderazgo situacional').closest('tr') as HTMLElement
    expect(fila).toHaveTextContent('ACT-0001')
    expect(fila).toHaveTextContent('Presencial')
    expect(fila).toHaveTextContent('2–13 mar 2026')
    expect(fila).toHaveTextContent('Sala de capacitación, piso 3')
    expect(fila).toHaveTextContent('Ps. Marco Salazar')
    expect(fila).toHaveTextContent('Talento Humano')
    expect(fila).toHaveTextContent('En curso')
  })

  it('el enlace de una actividad virtual se abre en otra pestaña', async () => {
    renderVista()

    const enlace = await screen.findByRole('link', { name: 'meet.ejemplo.edu.ec/excel' })
    expect(enlace).toHaveAttribute('href', 'https://meet.ejemplo.edu.ec/excel')
    expect(enlace).toHaveAttribute('target', '_blank')
    expect(enlace).toHaveAttribute('rel', expect.stringContaining('noopener'))
  })

  it('el badge «Asistencia pendiente» solo aparece en las actividades que lo tienen', async () => {
    renderVista()

    const finalizada = (await screen.findByText('Excel avanzado')).closest('tr') as HTMLElement
    const enCurso = screen.getByText('Liderazgo situacional').closest('tr') as HTMLElement
    expect(within(finalizada).getByText('Asistencia pendiente')).toBeInTheDocument()
    expect(within(finalizada).getByText('Finalizada')).toBeInTheDocument()
    expect(within(enCurso).queryByText('Asistencia pendiente')).not.toBeInTheDocument()
  })

  it('no ofrece botones de eliminar ni de cancelar la actividad', async () => {
    renderVista()
    await screen.findByRole('table', { name: 'Actividades formativas' })

    expect(screen.queryByRole('button', { name: /Eliminar|Cancelar/ })).not.toBeInTheDocument()
    expect(screen.queryByText(/Ver como colaborador/)).not.toBeInTheDocument()
  })

  it('enlaza al registro de asistencia de cada actividad', async () => {
    renderVista()

    expect(await screen.findByRole('link', { name: 'Registrar asistencia de Liderazgo situacional' })).toHaveAttribute('href', '/actividades/1/asistencia')
  })

  it('por defecto pide las actividades del año actual', async () => {
    renderVista()
    await screen.findByRole('table', { name: 'Actividades formativas' })

    const { desde, hasta } = rangoDelAnio()
    expect(listarActividades).toHaveBeenCalledWith(expect.objectContaining({ fecha_desde: desde, fecha_hasta: hasta, page: 1, per_page: 10 }))
    expect(screen.getByLabelText('Desde')).toHaveValue(desde)
  })

  it('la búsqueda y los filtros se guardan en la URL y se envían al servidor', async () => {
    renderVista()
    const user = userEvent.setup()
    await screen.findByRole('table', { name: 'Actividades formativas' })

    await user.type(screen.getByRole('searchbox', { name: 'Buscar' }), 'excel')
    await user.selectOptions(screen.getByLabelText('Tipo'), 'Virtual')
    await user.selectOptions(screen.getByLabelText('Estado'), 'Finalizada')
    await user.selectOptions(screen.getByLabelText('Departamento'), 'Tecnología')
    await user.selectOptions(screen.getByLabelText('Competencia'), 'Liderazgo')
    await user.selectOptions(screen.getByLabelText('Plan'), 'PLAN-0004 · Plan de desarrollo 2026')

    const ubicacion = screen.getByTestId('ubicacion')
    for (const parte of ['q=excel', 'tipo=Virtual', 'estado=Finalizada', 'departamento=1', 'competencia=1', 'plan=4']) expect(ubicacion).toHaveTextContent(parte)
    await waitFor(() =>
      expect(listarActividades).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'excel', tipo: 'Virtual', estado: 'Finalizada', departamento_id: 1, competencia_id: 1, plan_id: 4 })),
    )
  })

  it('restaura los filtros desde la URL y «Limpiar filtros» vuelve al año actual', async () => {
    renderVista('/actividades?tipo=OJT&estado=Programada&desde=2025-01-01&hasta=2025-12-31')
    const user = userEvent.setup()

    expect(await screen.findByLabelText('Tipo')).toHaveValue('OJT')
    expect(screen.getByLabelText('Estado')).toHaveValue('Programada')
    expect(screen.getByLabelText('Desde')).toHaveValue('2025-01-01')

    await user.click(screen.getByRole('button', { name: 'Limpiar filtros' }))

    expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/actividades$/)
    expect(screen.getByLabelText('Tipo')).toHaveValue('')
    expect(screen.getByLabelText('Desde')).toHaveValue(rangoDelAnio().desde)
    expect(screen.getByRole('button', { name: 'Limpiar filtros' })).toBeDisabled()
  })

  it('con filtros sin resultados muestra «Ninguna actividad coincide con los filtros»', async () => {
    vi.mocked(listarActividades).mockResolvedValue({ data: [], meta: meta(0) })
    renderVista('/actividades?q=nada')

    expect(await screen.findByText('Ninguna actividad coincide con los filtros.')).toBeInTheDocument()
  })

  it('sin actividades ni filtros muestra el estado vacío', async () => {
    vi.mocked(listarActividades).mockResolvedValue({ data: [], meta: meta(0) })
    renderVista()

    expect(await screen.findByText('Aún no hay actividades')).toBeInTheDocument()
  })

  it('si falla la carga ofrece reintentar', async () => {
    vi.mocked(listarActividades).mockRejectedValueOnce(httpError(500)).mockResolvedValue({ data: [resumen()], meta: meta(1) })
    renderVista()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Liderazgo situacional')).toBeInTheDocument()
  })

  it('pagina y guarda la página en la URL', async () => {
    vi.mocked(listarActividades).mockResolvedValue({ data: [resumen()], meta: meta(25) })
    renderVista()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Siguiente' }))

    expect(screen.getByTestId('ubicacion')).toHaveTextContent('page=2')
    await waitFor(() => expect(listarActividades).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2 })))
  })

  it('«+ Nueva Actividad» abre el formulario y al crear informa el código', async () => {
    renderVista()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: '+ Nueva Actividad' }))
    const dialogo = await screen.findByRole('dialog', { name: 'Nueva actividad formativa' })
    await user.click(await within(dialogo).findByRole('combobox', { name: 'Acción del plan' }))
    await user.click(await screen.findByRole('option', { name: /Excel avanzado/ }))
    await user.type(within(dialogo).getByLabelText('Lugar'), 'Sala 3')
    await user.click(within(dialogo).getByRole('button', { name: 'Crear actividad' }))

    expect(await screen.findByText('Se creó la actividad ACT-0009.')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('«Editar» abre el formulario de esa actividad con la acción fija', async () => {
    renderVista()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Editar Liderazgo situacional' }))

    const dialogo = await screen.findByRole('dialog', { name: 'Editar actividad formativa' })
    expect(await within(dialogo).findByLabelText('Lugar')).toHaveValue('Sala de capacitación, piso 3')
    expect(obtenerActividad).toHaveBeenCalledWith(1)
  })
})

describe('VistaAdministrador — calificaciones', () => {
  it('la pestaña Calificaciones se guarda en la URL y muestra «Por actividad» con promedio sin valor', async () => {
    vi.mocked(listarCalificacionesPorActividad).mockResolvedValue({
      data: [
        { id: 1, codigo: 'ACT-0001', nombre: 'Con notas', tipo: 'Presencial', fecha_inicio: '2026-03-02', fecha_fin: '2026-03-13', calificados: 3, promedio: 4.5 },
        { id: 2, codigo: 'ACT-0002', nombre: 'Sin notas', tipo: 'Virtual', fecha_inicio: '2026-02-02', fecha_fin: '2026-02-03', calificados: 0, promedio: null },
      ],
      meta: meta(2),
    })
    renderVista()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('tab', { name: 'Calificaciones' }))

    expect(screen.getByTestId('ubicacion')).toHaveTextContent('vista=calificaciones')
    const tabla = await screen.findByRole('table', { name: 'Calificaciones por actividad' })
    expect(within((within(tabla).getByText('Con notas').closest('tr')) as HTMLElement).getByText('4,5')).toBeInTheDocument()
    expect(within((within(tabla).getByText('Sin notas').closest('tr')) as HTMLElement).getByText('—')).toBeInTheDocument()
  })

  it('«Por participante» lista a los calificados y al elegir uno muestra el detalle por actividad', async () => {
    vi.mocked(listarCalificacionesPorParticipante).mockResolvedValue({ data: [{ colaborador_id: 10, nombre: 'Ana Pérez', actividades_calificadas: 2, promedio: 4.5 }], meta: meta(1) })
    vi.mocked(obtenerDetalleParticipante).mockResolvedValue({
      colaborador_id: 10,
      nombre: 'Ana Pérez',
      actividades_calificadas: 2,
      promedio: 4.5,
      actividades: [{ id: 1, codigo: 'ACT-0001', nombre: 'Liderazgo situacional', tipo: 'Presencial', fecha_inicio: '2026-03-02', asistencia: 'Presente', calificacion: 4 }],
    })
    renderVista('/actividades?vista=calificaciones&grupo=participante')
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Ver detalle de Ana Pérez' }))

    expect(screen.getByTestId('ubicacion')).toHaveTextContent('participante=10')
    const detalleTabla = await screen.findByRole('table', { name: 'Detalle de Ana Pérez' })
    expect(detalleTabla).toHaveTextContent('Liderazgo situacional')
    expect(detalleTabla).toHaveTextContent('Presente')
    expect(obtenerDetalleParticipante).toHaveBeenCalledWith(10, expect.any(Object))
  })

  it('los filtros de calificaciones se guardan en la URL y se envían', async () => {
    renderVista('/actividades?vista=calificaciones')
    const user = userEvent.setup()
    await screen.findByLabelText('Desde')

    await user.type(screen.getByLabelText('Desde'), '2026-01-01')
    await user.selectOptions(screen.getByLabelText('Tipo'), 'OJT')
    await user.selectOptions(screen.getByLabelText('Departamento'), 'Tecnología')

    expect(screen.getByTestId('ubicacion')).toHaveTextContent(/desde=2026-01-01.*tipo=OJT|tipo=OJT.*desde=2026-01-01/)
    await waitFor(() => expect(listarCalificacionesPorActividad).toHaveBeenLastCalledWith(expect.objectContaining({ fecha_desde: '2026-01-01', tipo: 'OJT', departamento_id: 1 })))
  })

  it('sin calificaciones muestra el mensaje informativo', async () => {
    renderVista('/actividades?vista=calificaciones')

    expect(await screen.findByText('Aún no hay calificaciones registradas para este filtro.')).toBeInTheDocument()
  })

  it('volver a la pestaña Actividades descarta los filtros de calificaciones', async () => {
    renderVista('/actividades?vista=calificaciones&tipo=OJT')
    const user = userEvent.setup()

    await user.click(await screen.findByRole('tab', { name: 'Actividades' }))

    expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/actividades$/)
  })
})
