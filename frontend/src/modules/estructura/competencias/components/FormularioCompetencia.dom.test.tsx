// @vitest-environment jsdom
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { detalle, escalas } from '../../../../test/competenciasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { crearCompetencia, listarEscalas } from '../api'
import { FormularioCompetencia } from './FormularioCompetencia'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  crearCompetencia: vi.fn(),
  listarEscalas: vi.fn(),
}))

const onClose = vi.fn()
const onCreada = vi.fn()

function renderFormulario() {
  return renderRoutes(<Route path="/" element={<FormularioCompetencia open onClose={onClose} onCreada={onCreada} />} />, '/')
}

type Usuario = ReturnType<typeof userEvent.setup>

async function escribir(usuario: Usuario, etiqueta: string, texto: string) {
  const campo = screen.getByLabelText(etiqueta)
  await usuario.click(campo)
  await usuario.paste(texto)
}

/** Llena los 4 grados, los 16 comportamientos y las 4 preguntas con textos reconocibles. */
async function llenarContenido(usuario: Usuario, etiquetas: string[] = ['Grado A', 'Grado B', 'Grado C', 'Grado D']) {
  for (const [grado, etiqueta] of etiquetas.entries()) {
    await escribir(usuario, etiqueta, `descripción ${grado + 1}`)
    for (const numero of [1, 2, 3, 4]) {
      await escribir(usuario, `${etiqueta} · Comportamiento ${numero}`, `c${grado + 1}${numero}`)
    }
  }
  for (const numero of [1, 2, 3, 4]) await escribir(usuario, `Pregunta ${numero}`, `p${numero}`)
}

async function llenarDatos(usuario: Usuario, nombre = 'Pensamiento estratégico') {
  await escribir(usuario, 'Nombre *', nombre)
  await usuario.selectOptions(screen.getByLabelText('Tipo *'), 'Especifica')
  await escribir(usuario, 'Descripción *', 'Anticipar escenarios.')
}

beforeEach(() => {
  onClose.mockReset()
  onCreada.mockReset()
  vi.mocked(listarEscalas).mockReset().mockResolvedValue(escalas())
  vi.mocked(crearCompetencia).mockReset().mockResolvedValue(detalle())
})

describe('FormularioCompetencia', () => {
  it('muestra los campos en orden con la escala predeterminada seleccionada', async () => {
    renderFormulario()

    await screen.findByLabelText('Nombre *')
    expect(screen.getByRole('dialog', { name: 'Nueva competencia' })).toBeInTheDocument()
    expect(screen.getByText('Los campos con * son obligatorios. Se guarda como una competencia activa.')).toBeInTheDocument()
    const campos = [
      screen.getByLabelText('Nombre *'),
      screen.getByLabelText('Tipo *'),
      screen.getByLabelText('Descripción *'),
      screen.getByRole('radiogroup', { name: 'Escala de los grados' }),
      screen.getByLabelText('Grado A'),
      screen.getByLabelText('Grado A · Comportamiento 1'),
      screen.getByLabelText('Pregunta 1'),
    ]
    campos.slice(1).forEach((campo, indice) => {
      expect(campos[indice].compareDocumentPosition(campo) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    })
    expect(screen.getByRole('radio', { name: /Letras/, checked: true })).toBeInTheDocument()
    expect(screen.getByText('Excelente, Muy buena…')).toBeInTheDocument()
    expect(screen.getAllByLabelText(/^Grado [A-D] · Comportamiento/)).toHaveLength(16)
    expect(screen.getAllByLabelText(/^Pregunta [1-4]$/)).toHaveLength(4)
  })

  it('cambia los rótulos de los grados al cambiar de escala', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await screen.findByLabelText('Nombre *')

    await usuario.click(screen.getByRole('radio', { name: /Números/ }))

    expect(screen.getByLabelText('Nivel 1')).toBeInTheDocument()
    expect(screen.getByLabelText('Nivel 4 · Comportamiento 2')).toBeInTheDocument()
    expect(screen.queryByLabelText('Grado A')).not.toBeInTheDocument()
  })

  it('con Categorías muestra cuatro nombres prellenados que cambian los rótulos de los grados', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await screen.findByLabelText('Nombre *')
    expect(screen.queryByLabelText('Categoría 1')).not.toBeInTheDocument()

    await usuario.click(screen.getByRole('radio', { name: /Categorías/ }))

    expect([1, 2, 3, 4].map((n) => (screen.getByLabelText(`Categoría ${n}`) as HTMLInputElement).value)).toEqual(['Excelente', 'Muy buena', 'Buena', 'Normal'])
    expect(screen.getByLabelText('Muy buena')).toBeInTheDocument()

    await usuario.clear(screen.getByLabelText('Categoría 1'))
    await usuario.click(screen.getByLabelText('Categoría 1'))
    await usuario.paste('Maestría')
    expect(screen.getByLabelText('Maestría')).toBeInTheDocument()
  })

  it('guarda la competencia completa con los textos recortados y avisa que se creó', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await screen.findByLabelText('Nombre *')

    await llenarDatos(usuario, '  Pensamiento estratégico ')
    await llenarContenido(usuario)
    await usuario.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    await waitFor(() => expect(crearCompetencia).toHaveBeenCalledTimes(1))
    expect(vi.mocked(crearCompetencia).mock.calls[0][0]).toEqual({
      nombre: 'Pensamiento estratégico',
      descripcion: 'Anticipar escenarios.',
      tipo: 'Especifica',
      tipo_grado_id: 1,
      grados: ['descripción 1', 'descripción 2', 'descripción 3', 'descripción 4'],
      comportamientos: [
        ['c11', 'c12', 'c13', 'c14'],
        ['c21', 'c22', 'c23', 'c24'],
        ['c31', 'c32', 'c33', 'c34'],
        ['c41', 'c42', 'c43', 'c44'],
      ],
      preguntas: ['p1', 'p2', 'p3', 'p4'],
    })
    await waitFor(() => expect(onCreada).toHaveBeenCalledWith('Competencia creada correctamente.'))
  })

  it('con Categorías envía los nombres propios', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await screen.findByLabelText('Nombre *')

    await llenarDatos(usuario)
    await usuario.click(screen.getByRole('radio', { name: /Categorías/ }))
    for (const [indice, nombre] of ['Maestría', 'Avanzado', 'Competente', 'Inicial'].entries()) {
      const campo = screen.getByLabelText(`Categoría ${indice + 1}`)
      await usuario.clear(campo)
      await usuario.click(campo)
      await usuario.paste(nombre)
    }
    await llenarContenido(usuario, ['Maestría', 'Avanzado', 'Competente', 'Inicial'])
    await usuario.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    await waitFor(() => expect(crearCompetencia).toHaveBeenCalledTimes(1))
    expect(vi.mocked(crearCompetencia).mock.calls[0][0]).toMatchObject({ tipo_grado_id: 3, categorias: ['Maestría', 'Avanzado', 'Competente', 'Inicial'] })
  })

  it('no guarda nada si falta un campo y muestra los errores solo tras el primer intento', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await screen.findByLabelText('Nombre *')
    expect(screen.queryByText('Ingresa el nombre.')).not.toBeInTheDocument()

    await usuario.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('Ingresa el nombre.')).toBeInTheDocument()
    expect(screen.getByText('Selecciona el tipo.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa la descripción.')).toBeInTheDocument()
    expect(screen.getByText('Completa los cuatro grados.')).toBeInTheDocument()
    expect(screen.getByText('Completa los cuatro comportamientos de cada grado.')).toBeInTheDocument()
    expect(screen.getByText('Completa las cuatro preguntas.')).toBeInTheDocument()
    expect(screen.getByText('Revisa los campos marcados antes de guardar.')).toBeInTheDocument()
    expect(crearCompetencia).not.toHaveBeenCalled()
  })

  it('rechaza nombres de categoría repetidos con el mensaje de la especificación', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await screen.findByLabelText('Nombre *')
    await llenarDatos(usuario)
    await usuario.click(screen.getByRole('radio', { name: /Categorías/ }))
    const segundo = screen.getByLabelText('Categoría 2')
    await usuario.clear(segundo)
    await usuario.click(segundo)
    await usuario.paste(' excelente ')
    await llenarContenido(usuario, ['Excelente', 'excelente', 'Buena', 'Normal'])
    await usuario.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('Escribe las cuatro categorías, sin repetir nombres.')).toBeInTheDocument()
    expect(crearCompetencia).not.toHaveBeenCalled()
  })

  it('muestra junto al nombre el error de nombre duplicado del servidor y conserva lo escrito', async () => {
    const usuario = userEvent.setup()
    vi.mocked(crearCompetencia).mockRejectedValue(httpError(422, { message: 'x', errors: { nombre: ['Ya existe una competencia con este nombre.'] } }))
    renderFormulario()
    await screen.findByLabelText('Nombre *')

    await llenarDatos(usuario, 'Liderazgo')
    await llenarContenido(usuario)
    await usuario.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('Ya existe una competencia con este nombre.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre *')).toHaveValue('Liderazgo')
    expect(screen.getByLabelText('Pregunta 4')).toHaveValue('p4')
    expect(onCreada).not.toHaveBeenCalled()
  })

  it('muestra un error general ante una falla de red y conserva los valores', async () => {
    const usuario = userEvent.setup()
    vi.mocked(crearCompetencia).mockRejectedValue(networkError())
    renderFormulario()
    await screen.findByLabelText('Nombre *')

    await llenarDatos(usuario)
    await llenarContenido(usuario)
    await usuario.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByText('No se pudo conectar con el servidor. Inténtalo de nuevo.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre *')).toHaveValue('Pensamiento estratégico')
  })

  it('bloquea el envío mientras se guarda', async () => {
    const usuario = userEvent.setup()
    vi.mocked(crearCompetencia).mockReturnValue(new Promise(() => {}))
    renderFormulario()
    await screen.findByLabelText('Nombre *')

    await llenarDatos(usuario)
    await llenarContenido(usuario)
    await usuario.click(screen.getByRole('button', { name: 'Guardar competencia' }))

    expect(await screen.findByRole('button', { name: 'Guardando…' })).toBeDisabled()
    expect(crearCompetencia).toHaveBeenCalledTimes(1)
  })

  it('Cancelar y Escape cierran sin crear nada', async () => {
    const usuario = userEvent.setup()
    renderFormulario()
    await screen.findByLabelText('Nombre *')

    await usuario.click(screen.getByRole('button', { name: 'Cancelar' }))
    await usuario.keyboard('{Escape}')

    expect(onClose).toHaveBeenCalledTimes(2)
    expect(crearCompetencia).not.toHaveBeenCalled()
  })

  it('muestra un esqueleto mientras carga las escalas y un error con reintento si fallan', async () => {
    const usuario = userEvent.setup()
    vi.mocked(listarEscalas).mockRejectedValueOnce(new Error('falló'))
    renderFormulario()

    expect(screen.getByRole('status', { name: 'Cargando competencia…' })).toBeInTheDocument()
    expect(await screen.findByText('No se pudo cargar la competencia')).toBeInTheDocument()
    await usuario.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByLabelText('Nombre *')).toBeInTheDocument()
  })
})
