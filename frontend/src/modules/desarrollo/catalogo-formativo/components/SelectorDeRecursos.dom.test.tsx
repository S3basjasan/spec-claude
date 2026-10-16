// @vitest-environment jsdom
import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { recurso } from '../../../../test/catalogoFormativoFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarRecursosDisponibles, type TipoRecurso } from '../api'
import { SelectorDeRecursos } from './SelectorDeRecursos'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarRecursosDisponibles: vi.fn(),
}))

const recursos = [
  recurso({ id: 1, nombre: 'Comunicación asertiva', competencias_count: 2 }),
  recurso({ id: 2, nombre: 'Escucha activa', competencias_count: 0 }),
  recurso({ id: 3, nombre: 'Gestión del tiempo', competencias_count: 1 }),
]

function renderSelector(props: { tipo?: TipoRecurso; excluidos?: number[]; onAsociar?: () => void; onVolver?: () => void } = {}) {
  return renderRoutes(
    <Route
      path="/"
      element={
        <SelectorDeRecursos
          tipo={props.tipo ?? 'curso'}
          competenciaId={7}
          excluidos={props.excluidos ?? []}
          onAsociar={props.onAsociar ?? vi.fn()}
          onVolver={props.onVolver ?? vi.fn()}
        />
      }
    />,
    '/',
  )
}

beforeEach(() => {
  vi.mocked(listarRecursosDisponibles).mockReset().mockResolvedValue(recursos)
})

describe('SelectorDeRecursos', () => {
  it('lista los recursos disponibles con el número de competencias que los usan', async () => {
    renderSelector()

    expect(await screen.findByLabelText(/Comunicación asertiva/)).toBeInTheDocument()
    expect(screen.getByText('Usado en 2 competencias')).toBeInTheDocument()
    expect(screen.getByText('Aún no se usa en otras competencias')).toBeInTheDocument()
    expect(listarRecursosDisponibles).toHaveBeenCalledWith('curso', 7, '')
  })

  it('no vuelve a ofrecer los ya elegidos en el panel', async () => {
    renderSelector({ excluidos: [1] })

    expect(await screen.findByLabelText(/Escucha activa/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/Comunicación asertiva/)).not.toBeInTheDocument()
  })

  it('permite elegir varios y asociarlos', async () => {
    const onAsociar = vi.fn()
    renderSelector({ onAsociar })
    const user = userEvent.setup()

    expect(await screen.findByRole('button', { name: 'Asociar' })).toBeDisabled()
    await user.click(await screen.findByLabelText(/Comunicación asertiva/))
    await user.click(screen.getByLabelText(/Gestión del tiempo/))
    await user.click(screen.getByRole('button', { name: 'Asociar (2)' }))

    expect(onAsociar).toHaveBeenCalledWith([recursos[0], recursos[2]])
  })

  it('"Volver al detalle" cierra el selector', async () => {
    const onVolver = vi.fn()
    renderSelector({ onVolver })

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Volver al detalle' }))

    expect(onVolver).toHaveBeenCalledOnce()
  })

  it('si ya están todos asociados lo explica', async () => {
    renderSelector({ excluidos: [1, 2, 3] })

    expect(await screen.findByText('Todos los recursos disponibles ya están asociados a esta competencia.')).toBeInTheDocument()
  })

  it('sin micro-contenidos registrados lo explica', async () => {
    vi.mocked(listarRecursosDisponibles).mockResolvedValue([])
    renderSelector({ tipo: 'microcontenido' })

    expect(await screen.findByText('Aún no hay micro-contenidos disponibles.')).toBeInTheDocument()
  })
})
