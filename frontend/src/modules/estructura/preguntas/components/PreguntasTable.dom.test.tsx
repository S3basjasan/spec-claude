// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { detalle, registro } from '../../../../test/preguntasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerPreguntas } from '../api'
import { PreguntasTable } from './PreguntasTable'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPreguntas: vi.fn(),
}))

const inactiva = registro({ competencia_id: 7, codigo: 'COMP-0007', nombre: 'Adaptabilidad', estado: 'Inactivo' })

function renderTabla(esAdmin: boolean) {
  return renderRoutes(<Route path="/" element={<PreguntasTable items={[registro(), inactiva]} esAdmin={esAdmin} />} />, '/')
}

beforeEach(() => {
  vi.mocked(obtenerPreguntas).mockReset().mockImplementation(async (id) =>
    id === 7 ? detalle({ competencia_id: 7, nombre: 'Adaptabilidad', estado: 'Inactivo' }) : detalle(),
  )
})

describe('PreguntasTable', () => {
  it('la fila de una competencia inactiva muestra el badge Inactivo y, al desplegarla, el aviso', async () => {
    renderTabla(false)
    const user = userEvent.setup()

    const fila = screen.getByRole('row', { name: /Adaptabilidad/ })
    expect(within(fila).getByText('Inactivo')).toBeInTheDocument()
    await user.click(within(fila).getByRole('button', { name: /Visualizar preguntas/ }))

    expect(await screen.findByText('La competencia asociada está inactiva')).toBeInTheDocument()
  })

  it('el botón alterna Visualizar/Ocultar y actualiza aria-expanded', async () => {
    renderTabla(false)
    const user = userEvent.setup()
    const boton = screen.getAllByRole('button', { name: /Visualizar preguntas/ })[0]

    expect(boton).toHaveAttribute('aria-expanded', 'false')
    await user.click(boton)
    expect(await screen.findByRole('region', { name: 'Preguntas de Liderazgo' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Ocultar preguntas/ })).toHaveAttribute('aria-expanded', 'true')

    await user.click(screen.getByRole('button', { name: /Ocultar preguntas/ }))
    expect(screen.queryByRole('region')).not.toBeInTheDocument()
  })

  it('solo el Administrador ve Editar y nadie ve Activar, Desactivar ni Eliminar', () => {
    const { unmount } = renderTabla(false)
    expect(screen.queryByRole('link', { name: /^Editar/ })).not.toBeInTheDocument()
    unmount()

    renderTabla(true)
    expect(screen.getAllByRole('link', { name: /^Editar/ })).toHaveLength(2)
    expect(screen.queryByRole('button', { name: /activar|desactivar|eliminar/i })).not.toBeInTheDocument()
  })
})
