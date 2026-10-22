// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle, escalas } from '../../../../test/competenciasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { aplicarEscala, listarEscalas, obtenerCompetencia } from '../api'
import { PestanasCompetencia } from './PestanasCompetencia'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerCompetencia: vi.fn(),
  listarEscalas: vi.fn(),
  aplicarEscala: vi.fn(),
}))

const onEditar = vi.fn()
const onAviso = vi.fn()

function renderPestanas(esAdmin = false) {
  return renderRoutes(
    <Route
      path="/"
      element={<PestanasCompetencia competenciaId={1} nombre="Liderazgo" esAdmin={esAdmin} onEditar={onEditar} onAviso={onAviso} />}
    />,
    '/',
  )
}

beforeEach(() => {
  onEditar.mockReset()
  onAviso.mockReset()
  vi.mocked(obtenerCompetencia).mockReset().mockResolvedValue(detalle())
  vi.mocked(listarEscalas).mockReset().mockResolvedValue(escalas())
  vi.mocked(aplicarEscala).mockReset()
})

describe('PestanasCompetencia', () => {
  it('abre la pestaña Competencia con la descripción y los cuatro grados con su escala', async () => {
    renderPestanas()

    expect(await screen.findByText('Guiar e inspirar a otras personas.')).toBeInTheDocument()
    expect(screen.getByRole('tablist', { name: 'Información de Liderazgo' })).toBeInTheDocument()
    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Competencia', 'Comportamientos', 'Preguntas'])
    expect(screen.getByRole('heading', { name: 'Grados de desarrollo · Letras' })).toBeInTheDocument()
    for (const letra of ['A', 'B', 'C', 'D']) {
      expect(screen.getByText(`Grado ${letra}`)).toBeInTheDocument()
      expect(screen.getByText(`Descripción del grado ${letra}.`)).toBeInTheDocument()
    }
  })

  it('en Comportamientos muestra por grado sus cuatro comportamientos como viñetas', async () => {
    const usuario = userEvent.setup()
    renderPestanas()
    await screen.findByText('Guiar e inspirar a otras personas.')

    await usuario.click(screen.getByRole('tab', { name: 'Comportamientos' }))

    expect(screen.getByRole('heading', { name: 'Comportamientos por grado' })).toBeInTheDocument()
    expect(screen.getAllByRole('list')).toHaveLength(4)
    for (const lista of screen.getAllByRole('list')) {
      expect(within(lista).getAllByRole('listitem')).toHaveLength(4)
    }
    expect(screen.getByText('Comportamiento 3 del grado B.')).toBeInTheDocument()
  })

  it('en Preguntas muestra las cuatro preguntas numeradas', async () => {
    const usuario = userEvent.setup()
    renderPestanas()
    await screen.findByText('Guiar e inspirar a otras personas.')

    await usuario.click(screen.getByRole('tab', { name: 'Preguntas' }))

    expect(screen.getByRole('heading', { name: 'Preguntas de la competencia' })).toBeInTheDocument()
    const lista = screen.getByRole('list')
    expect(lista.tagName).toBe('OL')
    expect(within(lista).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Pregunta uno.',
      'Pregunta dos.',
      'Pregunta tres.',
      'Pregunta cuatro.',
    ])
  })

  it('se mueve entre pestañas con las flechas, Inicio y Fin', async () => {
    const usuario = userEvent.setup()
    renderPestanas()
    await screen.findByText('Guiar e inspirar a otras personas.')

    screen.getByRole('tab', { name: 'Competencia' }).focus()
    await usuario.keyboard('{ArrowRight}')
    expect(screen.getByRole('tab', { name: 'Comportamientos', selected: true })).toHaveFocus()
    await usuario.keyboard('{End}')
    expect(screen.getByRole('tab', { name: 'Preguntas', selected: true })).toHaveFocus()
    await usuario.keyboard('{Home}')
    expect(screen.getByRole('tab', { name: 'Competencia', selected: true })).toHaveFocus()
  })

  it('muestra un esqueleto mientras carga y el error con "Reintentar"', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerCompetencia).mockRejectedValueOnce(new Error('falló'))
    renderPestanas()

    expect(screen.getByRole('status', { name: 'Cargando competencia…' })).toBeInTheDocument()
    expect(await screen.findByText('No se pudo cargar la competencia')).toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Guiar e inspirar a otras personas.')).toBeInTheDocument()
  })

  it('el usuario sin rol de administrador no ve botones de edición ni "Aplicar esta escala"', async () => {
    const usuario = userEvent.setup()
    renderPestanas(false)
    await screen.findByText('Guiar e inspirar a otras personas.')
    await usuario.click(screen.getByRole('button', { name: 'Números' }))

    expect(screen.queryByRole('button', { name: /^Editar/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aplicar esta escala' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Descartar' })).toBeInTheDocument()
  })

  it('el administrador edita cada sección desde el botón de su pestaña', async () => {
    const usuario = userEvent.setup()
    renderPestanas(true)
    await screen.findByText('Guiar e inspirar a otras personas.')

    await usuario.click(screen.getByRole('button', { name: 'Editar grados de desarrollo de Liderazgo' }))
    await usuario.click(screen.getByRole('tab', { name: 'Comportamientos' }))
    await usuario.click(screen.getByRole('button', { name: 'Editar comportamientos de Liderazgo' }))
    await usuario.click(screen.getByRole('tab', { name: 'Preguntas' }))
    await usuario.click(screen.getByRole('button', { name: 'Editar preguntas de Liderazgo' }))

    expect(onEditar.mock.calls.map(([seccion]) => seccion)).toEqual(['competencia', 'comportamientos', 'preguntas'])
  })

  it('al administrador le avisa cuando la competencia está incompleta', async () => {
    vi.mocked(obtenerCompetencia).mockResolvedValue(detalle({ completa: false, faltantes: ['comportamientos', 'preguntas'], estado: 'Inactivo' }))
    renderPestanas(true)

    expect(await screen.findByText(/Contenido incompleto: faltan los comportamientos y las preguntas/)).toBeInTheDocument()
  })

  it('al usuario sin rol de administrador no le muestra el aviso de contenido incompleto', async () => {
    vi.mocked(obtenerCompetencia).mockResolvedValue(detalle({ completa: false, faltantes: ['preguntas'] }))
    renderPestanas(false)
    await screen.findByText('Guiar e inspirar a otras personas.')

    expect(screen.queryByText(/Contenido incompleto/)).not.toBeInTheDocument()
  })
})

describe('Vista previa de la escala', () => {
  it('cambia los nombres de los grados solo en pantalla, marca "(vista previa)" y no llama al servidor', async () => {
    const usuario = userEvent.setup()
    renderPestanas(true)
    await screen.findByText('Guiar e inspirar a otras personas.')

    expect(screen.getByRole('button', { name: 'Letras', pressed: true })).toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Números' }))

    expect(screen.getByRole('button', { name: 'Números', pressed: true })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Grados de desarrollo · Números (vista previa)' })).toBeInTheDocument()
    expect(screen.getByText('Nivel 1')).toBeInTheDocument()
    expect(screen.queryByText('Grado A')).not.toBeInTheDocument()
    expect(screen.getByText('Descripción del grado A.')).toBeInTheDocument()
    expect(aplicarEscala).not.toHaveBeenCalled()
  })

  it('"Descartar" restablece la escala guardada', async () => {
    const usuario = userEvent.setup()
    renderPestanas(true)
    await screen.findByText('Guiar e inspirar a otras personas.')

    await usuario.click(screen.getByRole('button', { name: 'Categorías' }))
    expect(screen.getByText('Excelente')).toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Descartar' }))

    expect(screen.getByText('Grado A')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aplicar esta escala' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Letras', pressed: true })).toBeInTheDocument()
  })

  it('con Categorías usa los nombres propios de la competencia o, sin ellos, los de por defecto', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerCompetencia).mockResolvedValue(detalle({ categorias: ['Maestría', 'Avanzado', 'Competente', 'Inicial'] }))
    renderPestanas(true)
    await screen.findByText('Guiar e inspirar a otras personas.')

    await usuario.click(screen.getByRole('button', { name: 'Categorías' }))

    expect(screen.getByText('Maestría')).toBeInTheDocument()
    expect(screen.getByText('Inicial')).toBeInTheDocument()
    expect(screen.queryByText('Excelente')).not.toBeInTheDocument()
  })

  it('también cambia los nombres en la pestaña Comportamientos', async () => {
    const usuario = userEvent.setup()
    renderPestanas(true)
    await screen.findByText('Guiar e inspirar a otras personas.')
    await usuario.click(screen.getByRole('tab', { name: 'Comportamientos' }))

    await usuario.click(screen.getByRole('button', { name: 'Números' }))

    expect(screen.getByRole('heading', { name: 'Comportamientos por grado (vista previa)' })).toBeInTheDocument()
    expect(screen.getByText('Nivel 4')).toBeInTheDocument()
  })

  it('el administrador aplica la escala y se avisa "Escala de grados actualizada correctamente."', async () => {
    const usuario = userEvent.setup()
    vi.mocked(aplicarEscala).mockResolvedValue(detalle({ escala: { tipo_grado_id: 2, nombre: 'Números' } }))
    renderPestanas(true)
    await screen.findByText('Guiar e inspirar a otras personas.')

    await usuario.click(screen.getByRole('button', { name: 'Números' }))
    await usuario.click(screen.getByRole('button', { name: 'Aplicar esta escala' }))

    await waitFor(() => expect(aplicarEscala).toHaveBeenCalledWith(1, 2))
    await waitFor(() => expect(onAviso).toHaveBeenCalledWith('Escala de grados actualizada correctamente.'))
    expect(screen.queryByRole('button', { name: 'Aplicar esta escala' })).not.toBeInTheDocument()
  })

  it('si no se puede aplicar, muestra el error y conserva la vista previa', async () => {
    const usuario = userEvent.setup()
    vi.mocked(aplicarEscala).mockRejectedValue(httpError(500))
    renderPestanas(true)
    await screen.findByText('Guiar e inspirar a otras personas.')

    await usuario.click(screen.getByRole('button', { name: 'Números' }))
    await usuario.click(screen.getByRole('button', { name: 'Aplicar esta escala' }))

    expect(await screen.findByText('No se pudo aplicar la escala. Inténtalo de nuevo.')).toBeInTheDocument()
    expect(onAviso).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Aplicar esta escala' })).toBeInTheDocument()
  })

  it('la vista previa es propia de cada instancia: otra competencia abierta no cambia', async () => {
    const usuario = userEvent.setup()
    renderRoutes(
      <Route
        path="/"
        element={
          <>
            <PestanasCompetencia competenciaId={1} nombre="Liderazgo" esAdmin onEditar={onEditar} onAviso={onAviso} />
            <PestanasCompetencia competenciaId={2} nombre="Equipo" esAdmin onEditar={onEditar} onAviso={onAviso} />
          </>
        }
      />,
      '/',
    )
    await waitFor(() => expect(screen.getAllByRole('tablist')).toHaveLength(2))

    await usuario.click(within(screen.getByRole('tablist', { name: 'Información de Liderazgo' }).parentElement as HTMLElement).getByRole('button', { name: 'Números' }))

    expect(screen.getAllByRole('button', { name: 'Letras', pressed: true })).toHaveLength(1)
    expect(screen.getAllByRole('button', { name: 'Números', pressed: true })).toHaveLength(1)
  })
})
