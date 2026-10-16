// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { area, detalleAdmin, entrada, meta, recurso } from '../../../../test/catalogoFormativoFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { guardarConfiguracion, listarAreas, listarHistorial, listarRecursosDisponibles, obtenerDetalle } from '../api'
import { DetalleCompetenciaPanel } from './DetalleCompetenciaPanel'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerDetalle: vi.fn(),
  listarAreas: vi.fn(),
  listarHistorial: vi.fn(),
  listarRecursosDisponibles: vi.fn(),
  guardarConfiguracion: vi.fn(),
}))

function renderPanel(onCerrar = vi.fn()) {
  renderRoutes(<Route path="/" element={<DetalleCompetenciaPanel competenciaId={1} onCerrar={onCerrar} />} />, '/')
  return onCerrar
}

const otroCurso = recurso({ id: 5, nombre: 'Gestión del tiempo', competencias_count: 1 })

beforeEach(() => {
  vi.mocked(obtenerDetalle).mockReset().mockResolvedValue(detalleAdmin())
  vi.mocked(listarAreas).mockReset().mockResolvedValue([area({ id: 1, nombre: 'Habilidades interpersonales' }), area({ id: 2, nombre: 'Liderazgo' })])
  vi.mocked(listarHistorial).mockReset().mockResolvedValue({ data: [entrada()], meta: meta() })
  vi.mocked(listarRecursosDisponibles).mockReset().mockResolvedValue([otroCurso])
  vi.mocked(guardarConfiguracion).mockReset().mockResolvedValue(detalleAdmin({ version: 4 }))
})

async function abrirYAsociarOtroCurso(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('button', { name: 'Asociar curso' }))
  await user.click(await screen.findByLabelText(/Gestión del tiempo/))
  await user.click(screen.getByRole('button', { name: 'Asociar (1)' }))
}

describe('DetalleCompetenciaPanel', () => {
  it('muestra la sección de solo lectura y la editable', async () => {
    renderPanel()

    const dialogo = await screen.findByRole('dialog', { name: 'Comunicación efectiva' })
    expect(dialogo).toHaveAttribute('aria-modal', 'true')
    expect(within(dialogo).getByText('Estructura Empresarial · solo lectura')).toBeInTheDocument()
    expect(within(dialogo).getByText('Transmitir ideas de forma clara y respetuosa.')).toBeInTheDocument()
    expect(within(dialogo).getByText('Adapta su mensaje a cada audiencia.')).toBeInTheDocument()
    expect(within(dialogo).getByLabelText('Área formativa')).toHaveValue('1')
    expect(within(dialogo).getByText('Comunicación asertiva')).toBeInTheDocument()
    expect(within(dialogo).getByText('Guía de retroalimentación')).toBeInTheDocument()
    expect(within(dialogo).getByText('Última configuración: Marta Ríos', { exact: false })).toBeInTheDocument()
  })

  it('Guardar está deshabilitado hasta que haya cambios', async () => {
    renderPanel()

    expect(await screen.findByRole('button', { name: 'Guardar' })).toBeDisabled()
  })

  it('asocia un curso, guarda con la versión del detalle y muestra el aviso', async () => {
    renderPanel()
    const user = userEvent.setup()

    await abrirYAsociarOtroCurso(user)
    expect(screen.getByText('Tienes cambios sin guardar.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    await waitFor(() =>
      expect(guardarConfiguracion).toHaveBeenCalledWith(1, { area_formativa_id: 1, curso_ids: [1, 2, 5], microcontenido_ids: [10] }, 3),
    )
    expect(await screen.findByText('Cambios guardados.')).toBeInTheDocument()
  })

  it('quitar un curso pide confirmación, informa que solo se elimina el vínculo y se aplica al guardar', async () => {
    renderPanel()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Quitar Escucha activa' }))
    const confirmacion = await screen.findByRole('alertdialog')
    expect(within(confirmacion).getByText(/Solo se elimina el vínculo con la competencia/)).toBeInTheDocument()
    await user.click(within(confirmacion).getByRole('button', { name: 'Quitar' }))

    expect(screen.queryByText('Escucha activa')).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Guardar' }))
    await waitFor(() =>
      expect(guardarConfiguracion).toHaveBeenCalledWith(1, { area_formativa_id: 1, curso_ids: [1], microcontenido_ids: [10] }, 3),
    )
  })

  it('cambiar el área también se guarda con el botón', async () => {
    renderPanel()
    const user = userEvent.setup()

    await user.selectOptions(await screen.findByLabelText('Área formativa'), '2')
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(guardarConfiguracion).toHaveBeenCalledWith(1, expect.objectContaining({ area_formativa_id: 2 }), 3))
  })

  it('una competencia inactiva deshabilita asociar, avisa el motivo y deja quitar', async () => {
    vi.mocked(obtenerDetalle).mockResolvedValue(detalleAdmin({ competencia: { ...detalleAdmin().competencia, estado: 'Inactivo' } }))
    renderPanel()

    expect(await screen.findByText(/La competencia está inactiva: no se pueden asociar recursos nuevos/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Asociar curso' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Asociar contenido' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Quitar Escucha activa' })).toBeEnabled()
  })

  it('señala los recursos archivados y cuenta solo los disponibles en el encabezado', async () => {
    vi.mocked(obtenerDetalle).mockResolvedValue(detalleAdmin({ cursos: [recurso({ id: 1 }), recurso({ id: 2, nombre: 'Viejo', estado: 'Archivado' })] }))
    renderPanel()

    expect(await screen.findByText('Archivado')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Cursos asociados (1 · 1 archivado)' })).toBeInTheDocument()
  })

  it('al cerrar con cambios sin guardar advierte y permite seguir editando o descartar', async () => {
    const onCerrar = renderPanel()
    const user = userEvent.setup()

    await abrirYAsociarOtroCurso(user)
    await user.click(screen.getByRole('button', { name: 'Cerrar panel' }))

    const aviso = await screen.findByRole('alertdialog')
    expect(within(aviso).getByText('¿Descartar los cambios sin guardar?')).toBeInTheDocument()
    await user.click(within(aviso).getByRole('button', { name: 'Seguir editando' }))
    expect(onCerrar).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Cerrar panel' }))
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Descartar cambios' }))
    expect(onCerrar).toHaveBeenCalledOnce()
  })

  it('sin cambios cierra directamente, también con Escape', async () => {
    const onCerrar = renderPanel()
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Cerrar panel' }))
    await user.keyboard('{Escape}')

    expect(onCerrar).toHaveBeenCalledTimes(2)
  })

  it('un conflicto de edición ofrece recargar la versión actual', async () => {
    vi.mocked(guardarConfiguracion).mockRejectedValue(httpError(409, { message: 'x' }))
    renderPanel()
    const user = userEvent.setup()

    await abrirYAsociarOtroCurso(user)
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('La configuración cambió')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Recargar' }))
    await waitFor(() => expect(obtenerDetalle).toHaveBeenCalledTimes(2))
  })

  it('un error de validación 422 se muestra en el panel', async () => {
    vi.mocked(guardarConfiguracion).mockRejectedValue(
      httpError(422, { message: 'x', errors: { competencia: ['La competencia está inactiva: no se pueden asociar recursos nuevos.'] } }),
    )
    renderPanel()
    const user = userEvent.setup()

    await abrirYAsociarOtroCurso(user)
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Revisa los datos antes de guardar.')).toBeInTheDocument()
  })

  it('si no se pudo cargar el detalle muestra el error con Reintentar', async () => {
    vi.mocked(obtenerDetalle).mockRejectedValueOnce(httpError(500))
    renderPanel()
    const user = userEvent.setup()

    expect(await screen.findByText('No se pudo cargar el detalle')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('dialog', { name: 'Comunicación efectiva' })).toBeInTheDocument()
  })

  it('una competencia inexistente (404) lo dice con claridad', async () => {
    vi.mocked(obtenerDetalle).mockRejectedValue(httpError(404))
    renderPanel()

    expect(await screen.findByText('La competencia ya no existe')).toBeInTheDocument()
  })

  it('muestra el historial de cambios', async () => {
    renderPanel()

    expect(await screen.findByText('Asoció el curso "Comunicación asertiva"')).toBeInTheDocument()
  })
})
