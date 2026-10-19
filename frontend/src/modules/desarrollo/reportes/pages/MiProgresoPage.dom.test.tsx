// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { descargarBlob } from '../../../../shared/api/pdf'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { miProgreso } from '../../../../test/reportesFixtures'
import { descargarCertificado, obtenerMiProgreso, responderEncuesta, responderSeguimiento } from '../api'
import { MiProgresoPage } from './MiProgresoPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerMiProgreso: vi.fn(),
  responderEncuesta: vi.fn(),
  responderSeguimiento: vi.fn(),
  descargarCertificado: vi.fn(),
}))
vi.mock('../../../../shared/api/pdf', () => ({ descargarBlob: vi.fn() }))

function renderPage() {
  return renderRoutes(<Route path="/reportes" element={<MiProgresoPage />} />, '/reportes')
}

beforeEach(() => {
  vi.mocked(obtenerMiProgreso).mockReset().mockResolvedValue(miProgreso())
  vi.mocked(responderEncuesta).mockReset().mockResolvedValue({ actividad_id: 5, calificacion: 4, comentario: null, fecha: '2026-10-17' })
  vi.mocked(responderSeguimiento).mockReset().mockResolvedValue(undefined)
  vi.mocked(descargarCertificado).mockReset().mockResolvedValue({ blob: new Blob(['%PDF']), nombreDeArchivo: 'certificado-excel.pdf' })
  vi.mocked(descargarBlob).mockReset()
})

describe('MiProgresoPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(obtenerMiProgreso).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando reportes' })).toBeInTheDocument()
  })

  it('muestra las cuatro tarjetas de resumen', async () => {
    renderPage()

    expect(await screen.findByRole('article', { name: 'Formaciones completadas' })).toHaveTextContent('7')
    expect(screen.getByRole('heading', { name: 'Mi progreso' })).toBeInTheDocument()
    expect(screen.getByRole('article', { name: 'Competencias adquiridas' })).toHaveTextContent('3')
    expect(screen.getByRole('article', { name: 'Horas acumuladas' })).toHaveTextContent('96,5 h')
    expect(screen.getByRole('article', { name: 'Certificados' })).toHaveTextContent('2')
  })

  it('compara primera y más reciente evaluación; con una sola muestra solo el nivel actual', async () => {
    renderPage()

    const grafico = await screen.findByRole('img', { name: 'Gráfico de barras con la evolución de 2 competencias' })
    expect(grafico).toHaveTextContent('Liderazgo')
    expect(grafico).toHaveTextContent('D → B · requerido B')
    expect(grafico).toHaveTextContent('Nivel actual C · requerido A')
    const tabla = screen.getByRole('table', { name: 'Evolución de mis competencias', hidden: true })
    expect(within(tabla).getByRole('row', { name: /Comunicación/, hidden: true })).toHaveTextContent('Solo tienes una evaluación de esta competencia')
  })

  it('lista los certificados y descarga el PDF del propio colaborador', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Descargar certificado: Excel básico' }))

    await waitFor(() => expect(descargarCertificado).toHaveBeenCalledWith('/api/microcontenidos/31/certificado'))
    await waitFor(() => expect(descargarBlob).toHaveBeenCalledWith(expect.any(Blob), 'certificado-excel.pdf'))
  })

  it('un error al descargar el certificado muestra un mensaje', async () => {
    vi.mocked(descargarCertificado).mockRejectedValue(httpError(403))
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Descargar certificado: Excel básico' }))

    expect(await screen.findByText('No se pudo descargar el certificado. Inténtalo de nuevo.')).toBeInTheDocument()
  })

  it('muestra «Mi plan de desarrollo» con las próximas acciones', async () => {
    renderPage()

    const plan = await screen.findByRole('region', { name: 'Mi plan de desarrollo' })
    expect(plan).toHaveTextContent('Taller de comunicación')
    expect(plan).toHaveTextContent('Cierra brecha en: Comunicación')
    expect(plan).toHaveTextContent('4 nov 2026 – 5 nov 2026')
  })

  it('«Pendientes por responder» lista la encuesta y el seguimiento con su fecha límite', async () => {
    renderPage()

    const pendientes = await screen.findByRole('region', { name: 'Pendientes por responder' })
    expect(pendientes).toHaveTextContent('Encuesta de satisfacción')
    expect(pendientes).toHaveTextContent('Gestión del tiempo')
    expect(pendientes).toHaveTextContent('Responde hasta el 9 nov 2026')
    expect(pendientes).toHaveTextContent('Seguimiento a 30 días')
    expect(pendientes).toHaveTextContent('Responde hasta el 24 oct 2026')
  })

  it('responder la encuesta pendiente la envía y recarga el progreso', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Responder Encuesta de satisfacción de Gestión del tiempo' }))
    const dialogo = await screen.findByRole('dialog', { name: /Encuesta de satisfacción: Gestión del tiempo/ })
    await user.click(within(dialogo).getByRole('radio', { name: '4 de 5' }))
    await user.click(within(dialogo).getByRole('button', { name: 'Enviar' }))
    await user.click(await screen.findByRole('button', { name: 'Sí, enviar' }))

    await waitFor(() => expect(responderEncuesta).toHaveBeenCalledTimes(1))
    expect(await screen.findByText('Gracias: tu respuesta quedó registrada.')).toBeInTheDocument()
    await waitFor(() => expect(obtenerMiProgreso).toHaveBeenCalledTimes(2))
  })

  it('responder el seguimiento pendiente envía el hito correcto', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Responder Seguimiento a 30 días de Liderazgo situacional' }))
    const dialogo = await screen.findByRole('dialog', { name: /Seguimiento a 30 días: Liderazgo situacional/ })
    const [aplicacion, apoyo] = within(dialogo).getAllByRole('group')
    await user.click(within(aplicacion).getByRole('radio', { name: '4 de 5' }))
    await user.click(within(apoyo).getByRole('radio', { name: '3 de 5' }))
    await user.click(within(dialogo).getByRole('button', { name: 'Enviar' }))
    await user.click(await screen.findByRole('button', { name: 'Sí, enviar' }))

    await waitFor(() => expect(responderSeguimiento).toHaveBeenCalledTimes(1))
    expect(vi.mocked(responderSeguimiento).mock.calls[0][0]).toEqual({ actividad_id: 4, hito: 30, aplicacion: 4, apoyo: 3, comentario: undefined })
  })

  it('sin pendientes lo informa', async () => {
    vi.mocked(obtenerMiProgreso).mockResolvedValue(miProgreso({ pendientes: { encuestas: [], seguimientos: [] } }))
    renderPage()

    expect(await screen.findByText('No tienes encuestas ni seguimientos pendientes.')).toBeInTheDocument()
  })

  it('un colaborador sin datos ve un mensaje informativo, no un error', async () => {
    vi.mocked(obtenerMiProgreso).mockResolvedValue(
      miProgreso({
        resumen: { formaciones_completadas: 0, competencias_adquiridas: 0, horas_acumuladas: 0, certificados: 0 },
        evolucion_competencias: [],
        certificados: [],
        proximas_acciones: [],
        pendientes: { encuestas: [], seguimientos: [] },
      }),
    )
    renderPage()

    expect(await screen.findByText('Aún no hay datos para mostrar')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('muestra un error con «Reintentar» si falla la carga', async () => {
    vi.mocked(obtenerMiProgreso).mockRejectedValueOnce(httpError(500))
    const user = userEvent.setup()
    renderPage()

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudieron cargar los datos')
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByRole('article', { name: 'Formaciones completadas' })).toBeInTheDocument()
  })

  it('avisa si una fuente no está disponible y muestra el resto', async () => {
    vi.mocked(obtenerMiProgreso).mockResolvedValue(miProgreso({ fuentes_no_disponibles: ['dnf'] }))
    renderPage()

    expect(await screen.findByText(/Algunos datos no están disponibles: Detección de necesidades formativas/)).toBeInTheDocument()
    expect(screen.getByRole('article', { name: 'Formaciones completadas' })).toBeInTheDocument()
  })
})
