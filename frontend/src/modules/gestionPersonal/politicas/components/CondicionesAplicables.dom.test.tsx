// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { aplicable } from '../../../../test/politicasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerPoliticaAplicable } from '../api'
import { CondicionesAplicables } from './CondicionesAplicables'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPoliticaAplicable: vi.fn(),
}))

function renderCondiciones(props: Parameters<typeof CondicionesAplicables>[0]) {
  return renderRoutes(<Route path="/" element={<CondicionesAplicables {...props} />} />, '/')
}

beforeEach(() => {
  vi.mocked(obtenerPoliticaAplicable).mockReset().mockResolvedValue(aplicable())
})

describe('CondicionesAplicables', () => {
  it('muestra la versión y los requisitos de una política vigente', async () => {
    renderCondiciones({ tipo: 'permisos' })

    const region = await screen.findByRole('region', { name: 'Condiciones aplicables: Permisos' })
    expect(within(region).getByText(/Versión 2/)).toBeInTheDocument()
    expect(within(region).getByText('Exige justificación')).toBeInTheDocument()
    expect(within(region).getByText('2 días calendario')).toBeInTheDocument()
    expect(within(region).getByText('Reglas fijas')).toBeInTheDocument()
  })

  it('«Política sin configurar» no se presenta como ausencia de requisitos', async () => {
    vi.mocked(obtenerPoliticaAplicable).mockResolvedValue(aplicable({ situacion: 'SinConfigurar', numero: null, parametros: null, inicio_vigencia: null }))
    renderCondiciones({ tipo: 'permisos' })

    expect(await screen.findByText('Política sin configurar')).toBeInTheDocument()
    expect(screen.getByText(/no se pueden presentar solicitudes/i)).toBeInTheDocument()
    expect(screen.queryByText(/sin requisitos|sin restricciones/i)).not.toBeInTheDocument()
  })

  it('una política que no admite nuevas solicitudes lo explica y conserva las existentes', async () => {
    vi.mocked(obtenerPoliticaAplicable).mockResolvedValue(
      aplicable({ situacion: 'NoAdmiteSolicitudes', parametros: { disponible: false, justificacion_obligatoria: true, documento_obligatorio: false, anticipacion_dias: 2 } }),
    )
    renderCondiciones({ tipo: 'permisos' })

    expect(await screen.findByText('No admite nuevas solicitudes')).toBeInTheDocument()
    expect(screen.getByText(/las ya presentadas no se cancelan/i)).toBeInTheDocument()
  })

  it('una falla temporal no se muestra como ausencia de requisitos y permite reintentar', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerPoliticaAplicable).mockRejectedValue(httpError(503, { code: 'POLITICA_NO_DISPONIBLE' }))
    renderCondiciones({ tipo: 'permisos' })

    const alerta = await screen.findByRole('alert', {}, { timeout: 4000 })
    expect(within(alerta).getByText('No se pudieron consultar las condiciones')).toBeInTheDocument()
    expect(within(alerta).getByText(/esto no significa que no haya requisitos/i)).toBeInTheDocument()
    expect(screen.queryByText('Política sin configurar')).not.toBeInTheDocument()

    vi.mocked(obtenerPoliticaAplicable).mockResolvedValue(aplicable())
    await user.click(within(alerta).getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByRole('region', { name: /condiciones aplicables/i })).toBeInTheDocument()
  })

  it('con condiciones conservadas de una solicitud las muestra sin consultar la política vigente', async () => {
    renderCondiciones({ tipo: 'permisos', condicionesConservadas: { numero: 1, parametros: { disponible: true, justificacion_obligatoria: false, documento_obligatorio: true, anticipacion_dias: 7 } } })

    expect(await screen.findByText('Condiciones conservadas con esta solicitud')).toBeInTheDocument()
    expect(screen.getByText('7 días calendario')).toBeInTheDocument()
    expect(screen.getByText('Versión 1')).toBeInTheDocument()
    await waitFor(() => expect(obtenerPoliticaAplicable).not.toHaveBeenCalled())
  })

  it('no ofrece enlaces ni controles del panel administrativo', async () => {
    renderCondiciones({ tipo: 'permisos' })
    await screen.findByRole('region', { name: /condiciones aplicables/i })

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('Offboarding muestra las tareas en orden con la exigencia de evidencia', async () => {
    vi.mocked(obtenerPoliticaAplicable).mockResolvedValue(
      aplicable({
        tipo: 'offboarding',
        parametros: { tareas: [{ orden: 1, nombre: 'Devolver equipos', descripcion: 'Laptop', exige_evidencia: true }, { orden: 2, nombre: 'Cerrar accesos', descripcion: null, exige_evidencia: false }] },
      }),
    )
    renderCondiciones({ tipo: 'offboarding' })

    const region = await screen.findByRole('region', { name: 'Condiciones aplicables: Offboarding' })
    const items = within(region).getAllByRole('listitem').filter((li) => li.textContent?.match(/Devolver|Cerrar/))
    expect(items.map((li) => li.textContent)).toEqual([expect.stringContaining('Devolver equipos'), expect.stringContaining('Cerrar accesos')])
    expect(within(region).getByText('(exige evidencia)')).toBeInTheDocument()
    expect(within(region).queryByRole('button')).not.toBeInTheDocument()
  })
})
