// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { micro, opciones, quiz } from '../../../../test/microFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { actualizarMicro, cambiarEstadoMicro, crearMicro, obtenerMicro, obtenerOpciones } from '../api'
import { FormularioMicro } from './FormularioMicro'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerOpciones: vi.fn(),
  obtenerMicro: vi.fn(),
  crearMicro: vi.fn(),
  actualizarMicro: vi.fn(),
  cambiarEstadoMicro: vi.fn(),
}))

function renderFormulario(id: number | null = null, onCerrar = vi.fn()) {
  renderRoutes(<Route path="/" element={<FormularioMicro id={id} onCerrar={onCerrar} />} />, '/')
  return onCerrar
}

beforeEach(() => {
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opciones())
  vi.mocked(obtenerMicro).mockReset().mockResolvedValue(micro())
  vi.mocked(crearMicro).mockReset().mockResolvedValue(micro({ id: 20 }))
  vi.mocked(actualizarMicro).mockReset().mockResolvedValue(micro())
  vi.mocked(cambiarEstadoMicro).mockReset().mockResolvedValue(micro({ id: 20 }))
})

async function llenarBasico(user: ReturnType<typeof userEvent.setup>) {
  await user.type(await screen.findByLabelText('Título'), 'Nuevo video')
  await user.type(screen.getByLabelText('Duración (minutos)'), '12')
}

describe('FormularioMicro', () => {
  it('muestra la nota de conocimientos y solo ofrece competencias activas', async () => {
    renderFormulario()
    const user = userEvent.setup()

    await screen.findByLabelText('Título')
    expect(screen.getByRole('dialog', { name: 'Nuevo contenido' })).toBeInTheDocument()
    expect(screen.getByText(/refuerzan conocimientos y no reemplazan/)).toBeInTheDocument()
    await user.click(screen.getByLabelText('Competencias asociadas'))
    const lista = await screen.findByRole('listbox')
    expect(lista).toHaveTextContent('Comunicación efectiva')
    expect(lista).toHaveTextContent('Liderazgo')
    expect(lista).not.toHaveTextContent('Vieja')
  })

  it('guarda como borrador con solo título, tipo y duración', async () => {
    const onCerrar = renderFormulario()
    const user = userEvent.setup()
    await llenarBasico(user)

    await user.click(screen.getByRole('button', { name: 'Guardar borrador' }))

    await waitFor(() => expect(crearMicro).toHaveBeenCalledOnce())
    expect(vi.mocked(crearMicro).mock.calls[0][0]).toEqual({
      titulo: 'Nuevo video',
      tipo: 'Video',
      duracion_minutos: 12,
      url: null,
      miniatura_url: null,
      competencia_ids: [],
      preguntas: null,
    })
    expect(cambiarEstadoMicro).not.toHaveBeenCalled()
    await waitFor(() => expect(onCerrar).toHaveBeenCalled())
  })

  it('no guarda sin título ni duración y muestra los errores por campo', async () => {
    renderFormulario()
    const user = userEvent.setup()
    await screen.findByLabelText('Título')

    await user.click(screen.getByRole('button', { name: 'Guardar borrador' }))

    expect(await screen.findByText('El título es obligatorio.')).toBeInTheDocument()
    expect(screen.getByText('La duración debe ser un número entero de minutos mayor que cero.')).toBeInTheDocument()
    expect(crearMicro).not.toHaveBeenCalled()
  })

  it('para publicar exige URL y competencia y conserva lo escrito', async () => {
    renderFormulario()
    const user = userEvent.setup()
    await llenarBasico(user)

    await user.click(screen.getByRole('button', { name: 'Guardar y publicar' }))

    expect(await screen.findByText('La URL es obligatoria para publicar.')).toBeInTheDocument()
    expect(screen.getByText('Asocia al menos una competencia activa para publicar.')).toBeInTheDocument()
    expect(crearMicro).not.toHaveBeenCalled()
    expect(screen.getByLabelText('Título')).toHaveValue('Nuevo video')
  })

  it('crea y publica un contenido completo', async () => {
    renderFormulario()
    const user = userEvent.setup()
    await llenarBasico(user)
    await user.type(screen.getByLabelText('URL del contenido'), 'https://contenido.test/v')
    await user.click(screen.getByLabelText('Competencias asociadas'))
    await user.click(await screen.findByRole('option', { name: /Liderazgo/ }))
    expect(screen.getByRole('list', { name: 'Competencias asociadas' })).toHaveTextContent('Liderazgo')

    await user.click(screen.getByRole('button', { name: 'Guardar y publicar' }))

    await waitFor(() => expect(cambiarEstadoMicro).toHaveBeenCalledWith(20, 'Publicado'))
    expect(vi.mocked(crearMicro).mock.calls[0][0]).toMatchObject({ url: 'https://contenido.test/v', competencia_ids: [2] })
  })

  it('al elegir Quiz reemplaza la URL por el constructor de preguntas', async () => {
    renderFormulario()
    const user = userEvent.setup()
    await screen.findByLabelText('Título')

    await user.selectOptions(screen.getByLabelText('Tipo'), 'Quiz')

    expect(screen.queryByLabelText('URL del contenido')).not.toBeInTheDocument()
    expect(screen.getByText('Preguntas de opción múltiple (0/20)')).toBeInTheDocument()
  })

  it('muestra el rechazo del servidor sin perder lo escrito', async () => {
    vi.mocked(crearMicro).mockRejectedValue(httpError(422, { message: 'Hay datos inválidos.', errors: { url: ['Un quiz no lleva URL.'] } }))
    const onCerrar = renderFormulario()
    const user = userEvent.setup()
    await llenarBasico(user)

    await user.click(screen.getByRole('button', { name: 'Guardar borrador' }))

    expect(await screen.findByText('Un quiz no lleva URL.')).toBeInTheDocument()
    expect(onCerrar).not.toHaveBeenCalled()
    expect(screen.getByLabelText('Título')).toHaveValue('Nuevo video')
  })

  it('informa los datos que faltan si el servidor rechaza la publicación', async () => {
    vi.mocked(cambiarEstadoMicro).mockRejectedValue(httpError(422, { message: 'Faltan datos.', code: 'faltan_requisitos', faltantes: ['url', 'competencias'] }))
    renderFormulario()
    const user = userEvent.setup()
    await llenarBasico(user)
    await user.type(screen.getByLabelText('URL del contenido'), 'https://contenido.test/v')
    await user.click(screen.getByLabelText('Competencias asociadas'))
    await user.click(await screen.findByRole('option', { name: /Liderazgo/ }))

    await user.click(screen.getByRole('button', { name: 'Guardar y publicar' }))

    expect(await screen.findByText(/Faltan datos para publicar: la URL, al menos una competencia activa/)).toBeInTheDocument()
  })

  it('al editar carga el contenido, bloquea el tipo y muestra el código', async () => {
    vi.mocked(obtenerMicro).mockResolvedValue(micro({ id: 7, codigo: 'MICRO-0007', titulo: 'Existente', estado: 'Publicado' }))
    renderFormulario(7)

    expect(await screen.findByLabelText('Título')).toHaveValue('Existente')
    expect(screen.getByRole('dialog', { name: 'Editar contenido' })).toBeInTheDocument()
    expect(screen.getByLabelText('Tipo')).toBeDisabled()
    expect(screen.getByText('MICRO-0007')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar y publicar' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Guardar cambios' })).toBeInTheDocument()
  })

  it('guarda los cambios de un contenido existente', async () => {
    vi.mocked(obtenerMicro).mockResolvedValue(micro({ id: 7, titulo: 'Existente', estado: 'Borrador' }))
    const onCerrar = renderFormulario(7)
    const user = userEvent.setup()
    const titulo = await screen.findByLabelText('Título')

    await user.clear(titulo)
    await user.type(titulo, 'Editado')
    await user.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(actualizarMicro).toHaveBeenCalledWith(7, expect.objectContaining({ titulo: 'Editado' })))
    await waitFor(() => expect(onCerrar).toHaveBeenCalled())
  })

  it('un quiz con intentos terminados queda en solo lectura', async () => {
    vi.mocked(obtenerMicro).mockResolvedValue(quiz({ id: 5, tiene_intentos: true }))
    renderFormulario(5)

    expect(await screen.findByText(/ya tiene intentos terminados/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Agregar pregunta' })).toBeDisabled()
  })

  it('la vista previa del borrador no hace ninguna petición de guardado', async () => {
    renderFormulario()
    const user = userEvent.setup()
    await llenarBasico(user)

    await user.click(screen.getByRole('button', { name: 'Vista previa' }))

    expect(await screen.findByRole('dialog', { name: 'Vista previa · así lo verá el colaborador' })).toBeInTheDocument()
    expect(screen.getByText('La vista previa no registra progreso ni intentos.')).toBeInTheDocument()
    expect(crearMicro).not.toHaveBeenCalled()
  })

  it('muestra un error con reintentar si no cargan las competencias', async () => {
    vi.mocked(obtenerOpciones).mockRejectedValueOnce(httpError(500)).mockResolvedValue(opciones())
    renderFormulario()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByLabelText('Título')).toBeInTheDocument()
  })
})
