// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { avisos, dnf, generada, opciones, resultado } from '../../../../test/dnfFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { generarDnf, obtenerDnf, obtenerOpciones } from '../api'
import { DnfAdminPage } from './DnfAdminPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  generarDnf: vi.fn(),
  obtenerDnf: vi.fn(),
  obtenerOpciones: vi.fn(),
}))

function renderPage() {
  return renderRoutes(<Route path="/dnf" element={<DnfAdminPage />} />, '/dnf')
}

async function elegirColaborador() {
  await userEvent.type(await screen.findByRole('combobox', { name: 'Colaborador' }), 'Dieg')
  await userEvent.click(await screen.findByRole('option', { name: /Diego Paredes/ }))
  await userEvent.click(screen.getByRole('button', { name: 'Detectar necesidades' }))
}

const masiva = generada({
  id: 8,
  codigo: 'DNF-0008',
  alcance: 'Departamento',
  departamento: { id: 5, nombre: 'Tecnología' },
  colaboradores: [
    resultado({ colaborador_id: 10, nombre: 'Diego Paredes', brechas: undefined, cantidad_brechas: 2, prioridad_mas_alta: 'Alta' }),
    resultado({ colaborador_id: 11, nombre: 'Camila Andrade', brechas: undefined, cantidad_brechas: 0, prioridad_mas_alta: null }),
  ],
})
delete masiva.data.colaboradores[0].brechas
delete masiva.data.colaboradores[1].brechas

beforeEach(() => {
  vi.mocked(obtenerOpciones).mockReset().mockResolvedValue(opciones())
  vi.mocked(generarDnf).mockReset().mockResolvedValue(generada())
  vi.mocked(obtenerDnf).mockReset().mockResolvedValue({ data: dnf({ colaboradores: [resultado({ colaborador_id: 11, nombre: 'Camila Andrade' })] }) })
})

describe('DnfAdminPage', () => {
  it('muestra el asistente de dos pasos con el segundo deshabilitado hasta generar', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Paso 1 · ¿A quién quieres evaluar?' }, { timeout: 5000 })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Diagnóstico/ })).toBeDisabled()
    expect(screen.getByRole('link', { name: 'Ver historial' })).toHaveAttribute('href', '/dnf/historial')
  })

  it('genera una DNF individual y muestra el diagnóstico con tabla, gráfico y avisos', async () => {
    vi.mocked(generarDnf).mockResolvedValue(generada({}, { competencias_omitidas: 2, sin_evaluacion: 1 }))
    renderPage()

    await elegirColaborador()

    expect(await screen.findByRole('heading', { name: 'Paso 2 · Diagnóstico: Diego Paredes' })).toBeInTheDocument()
    expect(generarDnf).toHaveBeenCalledWith({ alcance: 'Individual', colaborador_id: 10 })
    expect(screen.getByText(/Diagnóstico guardado como DNF-0007/)).toBeInTheDocument()
    expect(screen.getByRole('table', { name: 'Diagnóstico por competencia' })).toBeInTheDocument()
    expect(screen.getByRole('img', { name: /Gráfico del perfil requerido/ })).toBeInTheDocument()
    expect(screen.getByText('Se omitieron 2 competencias inactivas del perfil.')).toBeInTheDocument()
    expect(screen.getByText(/Hay 1 competencia sin evaluación/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Diagnóstico/ })).toHaveAttribute('aria-current', 'step')
  })

  it('no ofrece botones de editar ni eliminar la DNF', async () => {
    renderPage()
    await elegirColaborador()
    await screen.findByRole('heading', { name: 'Paso 2 · Diagnóstico: Diego Paredes' })

    expect(screen.queryByRole('button', { name: /Editar|Eliminar/ })).not.toBeInTheDocument()
  })

  it('los filtros de prioridad y estado acotan las filas de la tabla', async () => {
    renderPage()
    await elegirColaborador()
    const tabla = await screen.findByRole('table', { name: 'Diagnóstico por competencia' })
    expect(within(tabla).getAllByRole('row')).toHaveLength(4)

    await userEvent.selectOptions(screen.getByLabelText('Filtrar por estado'), 'Cumple')

    expect(within(tabla).getAllByRole('row')).toHaveLength(2)
    expect(within(tabla).getByText('Trabajo en equipo')).toBeInTheDocument()
  })

  it('muestra el error del servidor por campo cuando el colaborador no es evaluable', async () => {
    vi.mocked(generarDnf).mockRejectedValue(
      httpError(422, { message: 'El colaborador no tiene un puesto asignado.', errors: { colaborador_id: ['El colaborador no tiene un puesto asignado.'] } }),
    )
    renderPage()

    await elegirColaborador()

    expect(await screen.findByText('El colaborador no tiene un puesto asignado.')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Paso 1 · ¿A quién quieres evaluar?' })).toBeInTheDocument()
  })

  it('si no hay colaboradores ni departamentos evaluables muestra un estado vacío', async () => {
    vi.mocked(obtenerOpciones).mockResolvedValue({ colaboradores: [], departamentos: [] })
    renderPage()

    expect(await screen.findByText('No hay colaboradores evaluables')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Detectar necesidades' })).not.toBeInTheDocument()
  })

  it('si fallan las opciones muestra el error y permite reintentar', async () => {
    vi.mocked(obtenerOpciones).mockRejectedValueOnce(httpError(500)).mockResolvedValue(opciones())
    renderPage()

    expect(await screen.findByText('No se pudo cargar la información')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('heading', { name: 'Paso 1 · ¿A quién quieres evaluar?' })).toBeInTheDocument()
  })

  describe('DNF masiva', () => {
    async function elegirDepartamento() {
      await userEvent.click(await screen.findByRole('radio', { name: /Por departamento/ }))
      await userEvent.selectOptions(screen.getByLabelText('Departamento'), 'Tecnología')
      await userEvent.click(screen.getByRole('button', { name: 'Detectar necesidades' }))
    }

    beforeEach(() => {
      vi.mocked(generarDnf).mockResolvedValue(masiva)
    })

    it('pide confirmación con la cantidad de colaboradores y no genera hasta confirmar', async () => {
      renderPage()

      await elegirDepartamento()

      const dialogo = await screen.findByRole('alertdialog', { name: 'Generar DNF masiva' })
      expect(dialogo).toHaveAccessibleDescription(/Se evaluarán 2 colaboradores del departamento Tecnología/)
      expect(generarDnf).not.toHaveBeenCalled()

      await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }))
      expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
      expect(generarDnf).not.toHaveBeenCalled()
    })

    it('al confirmar muestra el resumen por colaborador y luego la tabla del elegido', async () => {
      renderPage()
      await elegirDepartamento()

      await userEvent.click(await screen.findByRole('button', { name: 'Generar DNF' }))

      expect(await screen.findByRole('table', { name: 'Resumen por colaborador' })).toBeInTheDocument()
      expect(generarDnf).toHaveBeenCalledWith({ alcance: 'Departamento', departamento_id: 5 })

      await userEvent.click(screen.getByRole('button', { name: 'Ver diagnóstico de Camila Andrade' }))

      expect(await screen.findByRole('table', { name: 'Diagnóstico por competencia' })).toBeInTheDocument()
      expect(obtenerDnf).toHaveBeenCalledWith(8, 11)

      await userEvent.click(screen.getByRole('button', { name: 'Volver al resumen' }))
      expect(screen.getByRole('table', { name: 'Resumen por colaborador' })).toBeInTheDocument()
    })

    it('informa los colaboradores excluidos con su motivo', async () => {
      vi.mocked(generarDnf).mockResolvedValue({
        ...masiva,
        avisos: avisos({ excluidos: [{ colaborador_id: 20, nombre: 'Marcos Villacís', motivo: 'SinPuesto' }] }),
      })
      renderPage()
      await elegirDepartamento()

      await userEvent.click(await screen.findByRole('button', { name: 'Generar DNF' }))

      expect(await screen.findByText(/Marcos Villacís — No tiene puesto/)).toBeInTheDocument()
    })

    it('si la generación masiva falla muestra el motivo dentro del diálogo', async () => {
      vi.mocked(generarDnf).mockRejectedValue(httpError(422, { message: 'No hay colaboradores evaluables.' }))
      renderPage()
      await elegirDepartamento()

      await userEvent.click(await screen.findByRole('button', { name: 'Generar DNF' }))

      const dialogo = await screen.findByRole('alertdialog')
      await waitFor(() => expect(within(dialogo).getByRole('alert')).toHaveTextContent('No hay colaboradores evaluables.'))
    })

    it('si falla la consulta del colaborador muestra el error con reintento', async () => {
      vi.mocked(obtenerDnf).mockRejectedValueOnce(httpError(500)).mockResolvedValue({ data: dnf() })
      renderPage()
      await elegirDepartamento()
      await userEvent.click(await screen.findByRole('button', { name: 'Generar DNF' }))
      await userEvent.click(await screen.findByRole('button', { name: 'Ver diagnóstico de Diego Paredes' }))

      expect(await screen.findByText('No se pudo cargar la información')).toBeInTheDocument()
      await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }))

      expect(await screen.findByRole('table', { name: 'Diagnóstico por competencia' })).toBeInTheDocument()
    })
  })
})
