// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { detalle, escalas } from '../../../../test/competenciasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import {
  actualizarCompetencia,
  actualizarComportamientos,
  actualizarPreguntas,
  listarEscalas,
  obtenerCompetencia,
} from '../api'
import { FormularioSeccion, type SeccionEditable } from './FormularioSeccion'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerCompetencia: vi.fn(),
  listarEscalas: vi.fn(),
  actualizarCompetencia: vi.fn(),
  actualizarComportamientos: vi.fn(),
  actualizarPreguntas: vi.fn(),
}))

const onClose = vi.fn()
const onGuardado = vi.fn()

function renderSeccion(seccion: SeccionEditable) {
  return renderRoutes(
    <Route path="/" element={<FormularioSeccion competenciaId={1} seccion={seccion} onClose={onClose} onGuardado={onGuardado} />} />,
    '/',
  )
}

type Usuario = ReturnType<typeof userEvent.setup>

async function reemplazar(usuario: Usuario, etiqueta: string, texto: string) {
  const campo = screen.getByLabelText(etiqueta)
  await usuario.clear(campo)
  await usuario.click(campo)
  if (texto !== '') await usuario.paste(texto)
}

beforeEach(() => {
  onClose.mockReset()
  onGuardado.mockReset()
  vi.mocked(obtenerCompetencia).mockReset().mockResolvedValue(detalle({ version: 3 }))
  vi.mocked(listarEscalas).mockReset().mockResolvedValue(escalas())
  vi.mocked(actualizarCompetencia).mockReset().mockResolvedValue(detalle())
  vi.mocked(actualizarComportamientos).mockReset().mockResolvedValue(detalle())
  vi.mocked(actualizarPreguntas).mockReset().mockResolvedValue(detalle())
})

describe('FormularioSeccion: Editar competencia', () => {
  it('parte de una lectura fresca y muestra los datos actuales, con el nombre bajo el título', async () => {
    renderSeccion('competencia')

    expect(await screen.findByLabelText('Nombre *')).toHaveValue('Liderazgo')
    expect(screen.getByRole('dialog', { name: 'Editar competencia' })).toHaveAccessibleDescription('Liderazgo')
    expect(screen.getByLabelText('Tipo *')).toHaveValue('Cardinal')
    expect(screen.getByLabelText('Descripción *')).toHaveValue('Guiar e inspirar a otras personas.')
    expect(screen.getByLabelText('Grado B')).toHaveValue('Descripción del grado B.')
    expect(screen.getByRole('radio', { name: /Letras/, checked: true })).toBeInTheDocument()
    expect(screen.queryByLabelText(/código|estado/i)).not.toBeInTheDocument()
    expect(obtenerCompetencia).toHaveBeenCalledWith(1)
  })

  it('guarda nombre, tipo, escala y grados enviando la versión con la que se abrió y avisa el resultado', async () => {
    const usuario = userEvent.setup()
    renderSeccion('competencia')
    await screen.findByLabelText('Nombre *')

    await reemplazar(usuario, 'Nombre *', ' Liderazgo estratégico ')
    await usuario.selectOptions(screen.getByLabelText('Tipo *'), 'Especifica')
    await usuario.click(screen.getByRole('radio', { name: /Números/ }))
    await reemplazar(usuario, 'Nivel 1', 'Nuevo primer nivel')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(actualizarCompetencia).toHaveBeenCalledTimes(1))
    expect(actualizarCompetencia).toHaveBeenCalledWith(
      1,
      {
        nombre: 'Liderazgo estratégico',
        descripcion: 'Guiar e inspirar a otras personas.',
        tipo: 'Especifica',
        tipo_grado_id: 2,
        grados: ['Nuevo primer nivel', 'Descripción del grado B.', 'Descripción del grado C.', 'Descripción del grado D.'],
      },
      3,
    )
    await waitFor(() => expect(onGuardado).toHaveBeenCalledWith('Competencia actualizada correctamente.'))
  })

  it('con la escala Categorías parte de los nombres propios guardados y los envía', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerCompetencia).mockResolvedValue(
      detalle({ escala: { tipo_grado_id: 3, nombre: 'Categorías' }, categorias: ['Maestría', 'Avanzado', 'Competente', 'Inicial'] }),
    )
    renderSeccion('competencia')
    await screen.findByLabelText('Nombre *')

    expect((screen.getByLabelText('Categoría 1') as HTMLInputElement).value).toBe('Maestría')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(actualizarCompetencia).toHaveBeenCalledTimes(1))
    expect(vi.mocked(actualizarCompetencia).mock.calls[0][1]).toMatchObject({ tipo_grado_id: 3, categorias: ['Maestría', 'Avanzado', 'Competente', 'Inicial'] })
  })

  it('no guarda con un campo vacío y muestra el error junto al campo', async () => {
    const usuario = userEvent.setup()
    renderSeccion('competencia')
    await screen.findByLabelText('Nombre *')

    await reemplazar(usuario, 'Nombre *', '')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('Ingresa el nombre.')).toBeInTheDocument()
    expect(actualizarCompetencia).not.toHaveBeenCalled()
  })

  it('muestra el aviso de nombre duplicado del servidor y conserva lo escrito', async () => {
    const usuario = userEvent.setup()
    vi.mocked(actualizarCompetencia).mockRejectedValue(httpError(422, { message: 'x', errors: { nombre: ['Ya existe una competencia con este nombre.'] } }))
    renderSeccion('competencia')
    await screen.findByLabelText('Nombre *')

    await reemplazar(usuario, 'Nombre *', 'Empatía')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('Ya existe una competencia con este nombre.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre *')).toHaveValue('Empatía')
  })
})

describe('FormularioSeccion: Editar comportamientos', () => {
  it('muestra los 16 comportamientos agrupados por grado con los datos actuales', async () => {
    renderSeccion('comportamientos')

    expect(await screen.findByLabelText('Grado A · Comportamiento 1')).toHaveValue('Comportamiento 1 del grado A.')
    expect(screen.getByRole('dialog', { name: 'Editar comportamientos' })).toBeInTheDocument()
    expect(screen.getAllByLabelText(/^Grado [A-D] · Comportamiento/)).toHaveLength(16)
  })

  it('guarda los 16 comportamientos con la versión actual', async () => {
    const usuario = userEvent.setup()
    renderSeccion('comportamientos')
    await screen.findByLabelText('Grado A · Comportamiento 1')

    await reemplazar(usuario, 'Grado D · Comportamiento 4', ' Nuevo texto ')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(actualizarComportamientos).toHaveBeenCalledTimes(1))
    const [id, comportamientos, version] = vi.mocked(actualizarComportamientos).mock.calls[0]
    expect(id).toBe(1)
    expect(version).toBe(3)
    expect(comportamientos).toHaveLength(4)
    expect(comportamientos[3][3]).toBe('Nuevo texto')
    await waitFor(() => expect(onGuardado).toHaveBeenCalledWith('Comportamientos actualizados correctamente.'))
  })

  it('completa una competencia heredada: los comportamientos que faltan quedan vacíos hasta escribirlos', async () => {
    const usuario = userEvent.setup()
    const incompleta = detalle({ completa: false, faltantes: ['comportamientos'] })
    incompleta.grados.forEach((grado) => (grado.comportamientos = []))
    vi.mocked(obtenerCompetencia).mockResolvedValue(incompleta)
    renderSeccion('comportamientos')
    await screen.findByLabelText('Grado A · Comportamiento 1')

    expect(screen.getByLabelText('Grado A · Comportamiento 1')).toHaveValue('')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('Completa los cuatro comportamientos de cada grado.')).toBeInTheDocument()
    expect(actualizarComportamientos).not.toHaveBeenCalled()
  })

  it('no permite un comportamiento de más de 300 caracteres', async () => {
    const usuario = userEvent.setup()
    renderSeccion('comportamientos')
    await screen.findByLabelText('Grado A · Comportamiento 1')

    // El campo limita la escritura a 300; se fuerza el valor para comprobar también la validación.
    const campo = screen.getByLabelText('Grado A · Comportamiento 1') as HTMLInputElement
    campo.removeAttribute('maxlength')
    await usuario.clear(campo)
    await usuario.click(campo)
    await usuario.paste('c'.repeat(301))
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('Un comportamiento no puede superar los 300 caracteres.')).toBeInTheDocument()
    expect(actualizarComportamientos).not.toHaveBeenCalled()
  })
})

describe('FormularioSeccion: Editar preguntas', () => {
  it('guarda las cuatro preguntas y avisa el resultado', async () => {
    const usuario = userEvent.setup()
    renderSeccion('preguntas')
    await screen.findByLabelText('Pregunta 1')

    await reemplazar(usuario, 'Pregunta 2', ' ¿Cómo delegas? ')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await waitFor(() => expect(actualizarPreguntas).toHaveBeenCalledWith(1, ['Pregunta uno.', '¿Cómo delegas?', 'Pregunta tres.', 'Pregunta cuatro.'], 3))
    await waitFor(() => expect(onGuardado).toHaveBeenCalledWith('Preguntas actualizadas correctamente.'))
  })

  it('no guarda con una pregunta vacía', async () => {
    const usuario = userEvent.setup()
    renderSeccion('preguntas')
    await screen.findByLabelText('Pregunta 1')

    await reemplazar(usuario, 'Pregunta 3', '')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('Completa las cuatro preguntas.')).toBeInTheDocument()
    expect(actualizarPreguntas).not.toHaveBeenCalled()
  })
})

describe('FormularioSeccion: conflicto de edición', () => {
  it('ante un 409 deja el formulario abierto con el aviso y permite recargar la competencia', async () => {
    const usuario = userEvent.setup()
    vi.mocked(actualizarPreguntas).mockRejectedValue(httpError(409, { message: 'Esta competencia cambió mientras la editabas. Recarga para ver la versión actual.' }))
    renderSeccion('preguntas')
    await screen.findByLabelText('Pregunta 1')

    await reemplazar(usuario, 'Pregunta 1', 'Mi cambio')
    await usuario.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('Esta competencia cambió mientras la editabas. Recarga para ver la versión actual.')).toBeInTheDocument()
    expect(screen.getByRole('dialog', { name: 'Editar preguntas' })).toBeInTheDocument()
    expect(onGuardado).not.toHaveBeenCalled()

    vi.mocked(obtenerCompetencia).mockResolvedValue(detalle({ version: 4, preguntas: ['Otra uno.', 'Otra dos.', 'Otra tres.', 'Otra cuatro.'] }))
    await usuario.click(screen.getByRole('button', { name: 'Recargar competencia' }))

    await waitFor(() => expect(screen.getByLabelText('Pregunta 1')).toHaveValue('Otra uno.'))
    expect(screen.queryByText('Esta competencia cambió mientras la editabas. Recarga para ver la versión actual.')).not.toBeInTheDocument()
  })
})

describe('FormularioSeccion: estados de carga', () => {
  it('muestra un esqueleto mientras carga y no ofrece guardar', () => {
    vi.mocked(obtenerCompetencia).mockReturnValue(new Promise(() => {}))
    renderSeccion('preguntas')

    expect(screen.getByRole('status', { name: 'Cargando competencia…' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar cambios' })).not.toBeInTheDocument()
  })

  it('si la competencia ya no existe, lo indica', async () => {
    vi.mocked(obtenerCompetencia).mockRejectedValue(httpError(404))
    renderSeccion('preguntas')

    expect(await screen.findByText('La competencia ya no existe')).toBeInTheDocument()
  })

  it('ante un error de carga ofrece reintentar', async () => {
    const usuario = userEvent.setup()
    vi.mocked(obtenerCompetencia).mockRejectedValueOnce(httpError(500))
    renderSeccion('preguntas')

    expect(await screen.findByText('No se pudo cargar la competencia')).toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByLabelText('Pregunta 1')).toBeInTheDocument()
  })

  it('Cancelar cierra sin guardar', async () => {
    const usuario = userEvent.setup()
    renderSeccion('preguntas')
    await screen.findByLabelText('Pregunta 1')

    await usuario.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(onClose).toHaveBeenCalledTimes(1)
    expect(actualizarPreguntas).not.toHaveBeenCalled()
  })
})
