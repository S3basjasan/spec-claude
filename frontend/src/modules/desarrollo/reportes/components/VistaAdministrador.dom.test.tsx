// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { dashboard, filaDetalle, filaImpacto, filaSeguimiento, opcionesReportes, pagina } from '../../../../test/reportesFixtures'
import { descargarBlob } from '../../../../shared/api/pdf'
import { exportarReportes, listarDetalle, listarImpactos, listarSeguimiento, obtenerComentarios, obtenerDashboard, obtenerOpciones, registrarImpacto } from '../api'
import { VistaAdministrador } from './VistaAdministrador'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerOpciones: vi.fn(),
  obtenerDashboard: vi.fn(),
  listarDetalle: vi.fn(),
  obtenerComentarios: vi.fn(),
  listarSeguimiento: vi.fn(),
  listarImpactos: vi.fn(),
  registrarImpacto: vi.fn(),
  exportarReportes: vi.fn(),
}))
vi.mock('../../../../shared/api/pdf', () => ({ descargarBlob: vi.fn() }))

function renderVista(entrada = '/reportes') {
  return renderRoutes(<Route path="/reportes" element={<VistaAdministrador />} />, entrada)
}

beforeEach(() => {
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opcionesReportes())
  vi.mocked(obtenerDashboard).mockReset().mockResolvedValue(dashboard())
  vi.mocked(listarDetalle).mockReset().mockResolvedValue(pagina([filaDetalle()]))
  vi.mocked(obtenerComentarios).mockReset().mockResolvedValue({
    data: { promedio: 4.5, respuestas: 2, comentarios: [{ calificacion: 5, comentario: 'Excelente curso', fecha: '2026-03-15' }] },
    meta: { total: 1, page: 1, per_page: 10, last_page: 1 },
  })
  vi.mocked(listarSeguimiento).mockReset().mockResolvedValue(pagina([filaSeguimiento()]))
  vi.mocked(listarImpactos).mockReset().mockResolvedValue(pagina([filaImpacto()]))
  vi.mocked(registrarImpacto).mockReset()
  vi.mocked(exportarReportes).mockReset().mockResolvedValue({ blob: new Blob(['x']), nombreDeArchivo: 'reportes.xlsx' })
  vi.mocked(descargarBlob).mockReset()
})

describe('VistaAdministrador — indicadores', () => {
  it('muestra las cinco tarjetas con su comparación, la fila de gestión y los tres gráficos', async () => {
    renderVista()

    expect(await screen.findByRole('article', { name: '% Plan cumplido' })).toHaveTextContent('62,5 %')
    expect(screen.getByRole('article', { name: 'Total horas' })).toHaveTextContent('842 h')
    expect(screen.getByRole('article', { name: 'Satisfacción promedio' })).toHaveTextContent('4,3')
    expect(screen.getByRole('article', { name: 'Brechas cerradas' })).toHaveTextContent('11')
    expect(screen.getByRole('article', { name: 'Brechas cerradas' })).toHaveTextContent('8 detectadas')
    expect(screen.getByRole('article', { name: 'ROI estimado' })).toHaveTextContent('142,0 %')
    expect(screen.getByRole('article', { name: 'Inversión por colaborador formado' })).toHaveTextContent('$310,50')
    expect(screen.getByRole('article', { name: '% Plan cumplido' })).toHaveTextContent('Período anterior: 50,0 %')
    expect(screen.getByRole('article', { name: 'Total horas' })).toHaveTextContent('Sin datos del período anterior.')
    expect(screen.getByRole('figure', { name: /Horas de formación por departamento/ })).toBeInTheDocument()
    expect(screen.getByRole('figure', { name: /Distribución por tipo de formación/ })).toBeInTheDocument()
    expect(screen.getByRole('figure', { name: /Evolución de brechas/ })).toBeInTheDocument()
  })

  it('consulta con el año actual por defecto', async () => {
    renderVista()
    await screen.findByRole('article', { name: '% Plan cumplido' })

    const anio = new Date().getFullYear()
    expect(vi.mocked(obtenerDashboard).mock.calls[0][0]).toMatchObject({ fecha_desde: `${anio}-01-01`, fecha_hasta: `${anio}-12-31` })
  })

  it('los filtros viajan al backend y quedan en la URL', async () => {
    const user = userEvent.setup()
    renderVista()
    await screen.findByRole('article', { name: '% Plan cumplido' })

    await user.selectOptions(screen.getByLabelText('Departamento'), '2')
    await user.selectOptions(screen.getByLabelText('Tipo de formación'), 'Microlearning')
    await user.selectOptions(screen.getByLabelText('Plan'), '4')

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('departamento_id=2'))
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('tipo=Microlearning')
    expect(screen.getByTestId('ubicacion')).toHaveTextContent('plan_id=4')
    await waitFor(() => expect(vi.mocked(obtenerDashboard).mock.calls.at(-1)?.[0]).toMatchObject({ departamento_id: 2, tipo: 'Microlearning', plan_id: 4 }))
    expect(screen.getByRole('button', { name: 'Limpiar filtros' })).toBeInTheDocument()
  })

  it('«Limpiar filtros» restablece el año actual', async () => {
    const user = userEvent.setup()
    renderVista('/reportes?departamento_id=2&tipo=Virtual')
    await screen.findByRole('article', { name: '% Plan cumplido' })

    await user.click(screen.getByRole('button', { name: 'Limpiar filtros' }))

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent(/^\/reportes$/))
  })

  it('un rango invertido muestra el aviso y no consulta', async () => {
    renderVista('/reportes?fecha_desde=2026-05-01&fecha_hasta=2026-04-01')

    expect(await screen.findByText('La fecha inicial no puede ser posterior a la final.')).toBeInTheDocument()
    expect(obtenerDashboard).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Exportar Excel' })).toBeDisabled()
  })

  it('muestra un esqueleto mientras carga y un error con «Reintentar»', async () => {
    vi.mocked(obtenerDashboard).mockRejectedValueOnce(httpError(500))
    const user = userEvent.setup()
    renderVista()

    expect(screen.getByRole('status', { name: 'Cargando reportes' })).toBeInTheDocument()
    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudieron cargar los datos')

    await user.click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByRole('article', { name: '% Plan cumplido' })).toBeInTheDocument()
  })

  it('avisa qué fuentes no están disponibles y muestra «No disponible» en lo que depende de ellas', async () => {
    const d = dashboard({ fuentes_no_disponibles: ['microlearning'] })
    d.indicadores.total_horas = { ...d.indicadores.total_horas, disponible: false, valor: null }
    vi.mocked(obtenerDashboard).mockResolvedValue(d)
    renderVista()

    expect(await screen.findByText(/Algunos datos no están disponibles: Micro-learning/)).toBeInTheDocument()
    expect(screen.getByRole('article', { name: 'Total horas' })).toHaveTextContent('No disponible')
    expect(screen.getByRole('article', { name: '% Plan cumplido' })).toHaveTextContent('62,5 %')
  })
})

describe('VistaAdministrador — detalle y comentarios', () => {
  it('lista las actividades y abre los comentarios sin autor', async () => {
    const user = userEvent.setup()
    renderVista('/reportes?vista=detalle')

    const tabla = await screen.findByRole('table', { name: 'Detalle de actividades formativas del período' })
    expect(within(tabla).getByText('Liderazgo situacional')).toBeInTheDocument()
    expect(tabla).toHaveTextContent('8 / 10')
    expect(tabla).toHaveTextContent('$800,00')

    await user.click(screen.getByRole('button', { name: 'Ver comentarios de satisfacción de Liderazgo situacional' }))
    const dialogo = await screen.findByRole('dialog', { name: /Comentarios de satisfacción/ })
    expect(await within(dialogo).findByText('Excelente curso')).toBeInTheDocument()
    expect(dialogo).toHaveTextContent('Los comentarios son anónimos')
    expect(dialogo).toHaveTextContent('4,5')
    expect(within(dialogo).queryByText(/colaborador/i)).not.toBeInTheDocument()
  })

  it('sin actividades muestra el estado vacío', async () => {
    vi.mocked(listarDetalle).mockResolvedValue(pagina([]))
    renderVista('/reportes?vista=detalle')

    expect(await screen.findByText('Ninguna actividad coincide')).toBeInTheDocument()
  })

  it('la búsqueda se guarda en la URL', async () => {
    const user = userEvent.setup()
    renderVista('/reportes?vista=detalle')
    await screen.findByRole('table', { name: 'Detalle de actividades formativas del período' })

    await user.type(screen.getByRole('searchbox', { name: 'Buscar por nombre o código' }), 'Excel')

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('q=Excel'))
  })

  it('cambiar de pestaña actualiza la URL', async () => {
    const user = userEvent.setup()
    renderVista()
    await screen.findByRole('article', { name: '% Plan cumplido' })

    await user.click(screen.getByRole('tab', { name: 'Detalle' }))

    await waitFor(() => expect(screen.getByTestId('ubicacion')).toHaveTextContent('vista=detalle'))
  })
})

describe('VistaAdministrador — impacto 30-60-90', () => {
  it('muestra tasa, aplicación y apoyo por hito y el badge de alerta de reingreso', async () => {
    renderVista('/reportes?vista=seguimiento')

    const tabla = await screen.findByRole('table', { name: 'Seguimiento a 30, 60 y 90 días por actividad' })
    expect(tabla).toHaveTextContent('Aplicación 4,5')
    expect(tabla).toHaveTextContent('Apoyo 3,8')
    expect(tabla).toHaveTextContent('Sin respuestas')
    expect(within(tabla).getByText('Alerta de reingreso')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('Hay 1 actividad con alerta de reingreso.')
  })

  it('sin alerta no muestra el badge ni la franja', async () => {
    vi.mocked(listarSeguimiento).mockResolvedValue(pagina([filaSeguimiento({ alerta_reingreso: false })]))
    renderVista('/reportes?vista=seguimiento')

    await screen.findByRole('table', { name: 'Seguimiento a 30, 60 y 90 días por actividad' })
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.getByText('Sin alerta')).toBeInTheDocument()
  })
})

describe('VistaAdministrador — evaluación de impacto', () => {
  it('lista las acciones con «Sin evaluación» / «Con evaluación» y filtra', async () => {
    const user = userEvent.setup()
    vi.mocked(listarImpactos).mockResolvedValue(pagina([filaImpacto(), filaImpacto({ plan_accion_id: 22, accion_nombre: 'Con impacto', evaluada: true, roi: 150 })]))
    renderVista('/reportes?vista=impactos')

    const tabla = await screen.findByRole('table', { name: 'Acciones finalizadas y su evaluación de impacto' })
    expect(tabla).toHaveTextContent('Sin evaluación')
    expect(tabla).toHaveTextContent('Con evaluación')
    expect(tabla).toHaveTextContent('150,0 %')
    expect(within(tabla).getByRole('button', { name: 'Registrar impacto de Análisis con SQL' })).toBeInTheDocument()
    expect(within(tabla).getByRole('button', { name: 'Editar impacto de Con impacto' })).toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText('Evaluación'), 'con')
    await waitFor(() => expect(vi.mocked(listarImpactos).mock.calls.at(-1)?.[0]).toMatchObject({ evaluado: 'con' }))
  })

  it('registra el impacto y muestra el ROI calculado', async () => {
    vi.mocked(registrarImpacto).mockResolvedValue({
      plan_accion_id: 21, indicador: 'Tiempo', unidad: 'días', valor_antes: 10, valor_despues: 7, beneficio_estimado: 2400, justificacion: 'Menos reprocesos',
      roi: 200, creado_por: 'Admin', creado_en: '2026-10-17T10:00:00Z', modificado_por: 'Admin', modificado_en: '2026-10-17T10:00:00Z',
    })
    const user = userEvent.setup()
    renderVista('/reportes?vista=impactos')

    await user.click(await screen.findByRole('button', { name: 'Registrar impacto de Análisis con SQL' }))
    const dialogo = await screen.findByRole('dialog', { name: /Evaluación de impacto: Análisis con SQL/ })
    await user.type(within(dialogo).getByLabelText('Indicador de negocio'), 'Tiempo')
    await user.type(within(dialogo).getByLabelText('Unidad'), 'días')
    await user.type(within(dialogo).getByLabelText('Valor antes'), '10')
    await user.type(within(dialogo).getByLabelText('Valor después'), '7')
    await user.type(within(dialogo).getByLabelText('Beneficio estimado (USD)'), '2400')
    await user.type(within(dialogo).getByLabelText('Justificación'), 'Menos reprocesos')

    expect(within(dialogo).getByText('ROI calculado de la acción').parentElement).toHaveTextContent('200,0 %')
    await user.click(within(dialogo).getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(registrarImpacto).toHaveBeenCalledTimes(1))
    expect(vi.mocked(registrarImpacto).mock.calls[0]).toEqual([21, { indicador: 'Tiempo', unidad: 'días', valor_antes: 10, valor_despues: 7, beneficio_estimado: 2400, justificacion: 'Menos reprocesos' }])
    expect(await screen.findByText('Se guardó la evaluación de impacto.')).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('valida los campos y muestra los errores por campo sin llamar al backend', async () => {
    const user = userEvent.setup()
    renderVista('/reportes?vista=impactos')

    await user.click(await screen.findByRole('button', { name: 'Registrar impacto de Análisis con SQL' }))
    const dialogo = await screen.findByRole('dialog')
    await user.type(within(dialogo).getByLabelText('Beneficio estimado (USD)'), '-5')
    await user.click(within(dialogo).getByRole('button', { name: 'Guardar' }))

    expect(await within(dialogo).findByText('El indicador de negocio es obligatorio.')).toBeInTheDocument()
    expect(within(dialogo).getByText('El beneficio estimado no puede ser negativo.')).toBeInTheDocument()
    expect(within(dialogo).getByText('La justificación es obligatoria.')).toBeInTheDocument()
    expect(registrarImpacto).not.toHaveBeenCalled()
  })

  it('mapea un 422 del servidor a los campos', async () => {
    vi.mocked(registrarImpacto).mockRejectedValue(httpError(422, { message: 'x', errors: { indicador: ['El indicador de negocio es obligatorio.'] } }))
    const user = userEvent.setup()
    vi.mocked(listarImpactos).mockResolvedValue(pagina([filaImpacto({ evaluada: true, roi: 10, impacto: { plan_accion_id: 21, indicador: 'A', unidad: 'días', valor_antes: 1, valor_despues: 2, beneficio_estimado: 100, justificacion: 'J', roi: 10, creado_por: 'Ana', creado_en: '2026-10-01T10:00:00Z', modificado_por: 'Luis', modificado_en: '2026-10-02T10:00:00Z' } })]))
    renderVista('/reportes?vista=impactos')

    await user.click(await screen.findByRole('button', { name: 'Editar impacto de Análisis con SQL' }))
    const dialogo = await screen.findByRole('dialog')
    expect(within(dialogo).getByLabelText('Indicador de negocio')).toHaveValue('A')
    expect(dialogo).toHaveTextContent('Creada por Ana')
    await user.click(within(dialogo).getByRole('button', { name: 'Guardar' }))

    expect(await within(dialogo).findByText('El indicador de negocio es obligatorio.')).toBeInTheDocument()
  })

  it('con costo cero el ROI no aplica', async () => {
    vi.mocked(listarImpactos).mockResolvedValue(pagina([filaImpacto({ costo: 0 })]))
    const user = userEvent.setup()
    renderVista('/reportes?vista=impactos')

    await user.click(await screen.findByRole('button', { name: 'Registrar impacto de Análisis con SQL' }))
    const dialogo = await screen.findByRole('dialog')
    await user.type(within(dialogo).getByLabelText('Beneficio estimado (USD)'), '1000')

    expect(within(dialogo).getByText('ROI calculado de la acción').parentElement).toHaveTextContent('No aplica')
  })
})

describe('VistaAdministrador — exportación', () => {
  it('exporta Excel con los filtros aplicados y descarga el archivo', async () => {
    const user = userEvent.setup()
    renderVista('/reportes?departamento_id=2')
    await screen.findByRole('article', { name: '% Plan cumplido' })

    await user.click(screen.getByRole('button', { name: 'Exportar Excel' }))

    await waitFor(() => expect(exportarReportes).toHaveBeenCalledTimes(1))
    expect(vi.mocked(exportarReportes).mock.calls[0][0]).toBe('xlsx')
    expect(vi.mocked(exportarReportes).mock.calls[0][1]).toMatchObject({ departamento_id: 2 })
    await waitFor(() => expect(descargarBlob).toHaveBeenCalledWith(expect.any(Blob), 'reportes.xlsx'))
    expect(await screen.findByText('Se descargó reportes.xlsx.')).toBeInTheDocument()
  })

  it('exporta PDF y muestra un mensaje seguro si falla', async () => {
    vi.mocked(exportarReportes).mockRejectedValue(httpError(503, { message: 'x', code: 'exportacion_no_disponible' }))
    const user = userEvent.setup()
    renderVista()
    await screen.findByRole('article', { name: '% Plan cumplido' })

    await user.click(screen.getByRole('button', { name: 'Exportar PDF' }))

    expect(await screen.findByText('No fue posible generar el archivo. Inténtalo de nuevo más tarde.')).toBeInTheDocument()
    expect(vi.mocked(exportarReportes).mock.calls[0][0]).toBe('pdf')
    expect(descargarBlob).not.toHaveBeenCalled()
  })
})
