// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { entrada, meta } from '../../../../test/catalogoFormativoFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarHistorial } from '../api'
import { HistorialLista } from './HistorialLista'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarHistorial: vi.fn(),
}))

function renderLista() {
  return renderRoutes(<Route path="/" element={<HistorialLista competenciaId={1} />} />, '/')
}

beforeEach(() => {
  vi.mocked(listarHistorial).mockReset()
})

describe('HistorialLista', () => {
  it('describe asociaciones, desasociaciones, cambios de área y renombrados con usuario', async () => {
    vi.mocked(listarHistorial).mockResolvedValue({
      data: [
        entrada(),
        entrada({ tipo_cambio: 'Microcontenido', accion: 'Quitado', recurso: 'Guía rápida', valor_anterior: 'Guía rápida', valor_nuevo: null }),
        entrada({ tipo_cambio: 'AreaFormativa', accion: 'Cambiado', recurso: null, valor_anterior: 'Técnica', valor_nuevo: 'Liderazgo' }),
        entrada({ tipo_cambio: 'AreaFormativa', accion: 'Renombrado', recurso: null, valor_anterior: 'Vieja', valor_nuevo: 'Nueva' }),
      ],
      meta: meta({ total: 4 }),
    })
    renderLista()

    expect(await screen.findByText('Asoció el curso "Comunicación asertiva"')).toBeInTheDocument()
    expect(screen.getByText('Quitó el micro-contenido "Guía rápida"')).toBeInTheDocument()
    expect(screen.getByText('Cambió el área de "Técnica" a "Liderazgo"')).toBeInTheDocument()
    expect(screen.getByText('Renombró el área "Vieja" como "Nueva"')).toBeInTheDocument()
    expect(screen.getAllByText(/Marta Ríos/)).toHaveLength(4)
  })

  it('muestra un estado vacío', async () => {
    vi.mocked(listarHistorial).mockResolvedValue({ data: [], meta: meta({ total: 0 }) })
    renderLista()

    expect(await screen.findByText('Aún no hay cambios registrados.')).toBeInTheDocument()
  })

  it('pagina el historial', async () => {
    vi.mocked(listarHistorial).mockResolvedValue({ data: [entrada()], meta: meta({ total: 7, last_page: 2 }) })
    renderLista()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Siguiente' }))

    expect(listarHistorial).toHaveBeenLastCalledWith(1, 2)
  })

  it('si falla ofrece reintentar', async () => {
    vi.mocked(listarHistorial).mockRejectedValue(httpError(500))
    renderLista()

    expect(await screen.findByText('No se pudo cargar el historial')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
  })
})
