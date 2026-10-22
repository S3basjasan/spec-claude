// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { borrador, detalle, detalleSinConfigurar, previsualizacion, version } from '../../../../test/politicasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { crearBorrador, descartarBorrador, editarBorrador, obtenerPolitica, previsualizarPublicacion, publicarVersion } from '../api'
import { BorradorPage } from './BorradorPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerPolitica: vi.fn(),
  crearBorrador: vi.fn(),
  editarBorrador: vi.fn(),
  descartarBorrador: vi.fn(),
  previsualizarPublicacion: vi.fn(),
  publicarVersion: vi.fn(),
}))

const COMPLETO = { disponible: true, justificacion_obligatoria: true, documento_obligatorio: false, anticipacion_dias: 4 }

function renderEditor(tipo = 'permisos') {
  return renderRoutes(
    <>
      <Route path="/politicas-personal/:tipo/borrador" element={<BorradorPage />} />
      <Route path="/politicas-personal/:tipo" element={<p>Detalle de la política</p>} />
    </>,
    `/politicas-personal/${tipo}/borrador`,
  )
}

const conBorrador = (cambios = {}) => detalle('permisos', { borrador: borrador({ descripcion: 'Permisos de la organización', parametros: COMPLETO, version: 1, ...cambios }) })

beforeEach(() => {
  vi.mocked(obtenerPolitica).mockReset().mockResolvedValue(conBorrador())
  vi.mocked(crearBorrador).mockReset().mockResolvedValue(borrador())
  vi.mocked(editarBorrador).mockReset().mockImplementation(async (_t, id, cambios) => borrador({ id, version: cambios.version + 1, descripcion: cambios.descripcion, parametros: cambios.parametros }))
  vi.mocked(descartarBorrador).mockReset().mockResolvedValue(undefined)
  vi.mocked(previsualizarPublicacion).mockReset().mockResolvedValue(previsualizacion())
  vi.mocked(publicarVersion).mockReset().mockResolvedValue(version({ numero: 2 }))
})

describe('BorradorPage: edición', () => {
  it('sin borrador ofrece crearlo y lo crea', async () => {
    const user = userEvent.setup()
    vi.mocked(obtenerPolitica).mockResolvedValue(detalleSinConfigurar())
    renderEditor()

    expect(await screen.findByText('No hay un borrador abierto')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Crear borrador' }))

    await waitFor(() => expect(crearBorrador).toHaveBeenCalledWith('permisos', undefined))
  })

  it('muestra solo los parámetros admitidos y las reglas fijas como información de solo lectura', async () => {
    renderEditor()

    expect(await screen.findByLabelText('Descripción')).toHaveValue('Permisos de la organización')
    expect(screen.getByLabelText('Anticipación mínima (días calendario)')).toHaveValue(4)
    expect(screen.getByLabelText('Permite nuevas solicitudes')).toHaveValue('true')
    expect(screen.getByRole('region', { name: 'Reglas fijas' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Borrador de Permisos' })).toBeInTheDocument()
  })

  it('guarda con el control de edición y los parámetros normalizados', async () => {
    const user = userEvent.setup()
    renderEditor()
    const anticipacion = await screen.findByLabelText('Anticipación mínima (días calendario)')

    await user.clear(anticipacion)
    await user.type(anticipacion, '6')
    await user.click(screen.getByRole('button', { name: 'Guardar borrador' }))

    await waitFor(() => expect(editarBorrador).toHaveBeenCalledTimes(1))
    expect(editarBorrador).toHaveBeenCalledWith('permisos', 20, { version: 1, descripcion: 'Permisos de la organización', parametros: { ...COMPLETO, anticipacion_dias: 6 } })
    expect(await screen.findByText('Borrador guardado.')).toBeInTheDocument()
  })

  it('una anticipación inválida se rechaza en el cliente y no se envía', async () => {
    const user = userEvent.setup()
    renderEditor()
    const anticipacion = await screen.findByLabelText('Anticipación mínima (días calendario)')

    await user.clear(anticipacion)
    await user.type(anticipacion, '-1')
    await user.click(screen.getByRole('button', { name: 'Guardar borrador' }))

    expect(await screen.findByText(/igual o mayor que 0/i)).toBeInTheDocument()
    expect(editarBorrador).not.toHaveBeenCalled()
  })

  it('los errores por campo del servidor aparecen junto al campo', async () => {
    const user = userEvent.setup()
    vi.mocked(editarBorrador).mockRejectedValueOnce(httpError(422, { code: 'VALIDACION', errors: { descripcion: ['Máximo 1000 caracteres.'] } }))
    renderEditor()
    await screen.findByLabelText('Descripción')

    await user.click(screen.getByRole('button', { name: 'Guardar borrador' }))

    expect(await screen.findByLabelText('Descripción')).toHaveAccessibleDescription(/Máximo 1000 caracteres/)
  })

  it('ante un conflicto conserva el contenido pendiente y no reenvía; recargar trae la versión actual', async () => {
    const user = userEvent.setup()
    vi.mocked(editarBorrador).mockRejectedValueOnce(httpError(409, { code: 'BORRADOR_MODIFICADO' }))
    renderEditor()
    const descripcion = await screen.findByLabelText('Descripción')

    await user.clear(descripcion)
    await user.type(descripcion, 'Mi cambio pendiente')
    await user.click(screen.getByRole('button', { name: 'Guardar borrador' }))

    const alerta = await screen.findByRole('alert')
    expect(within(alerta).getByText('El borrador cambió')).toBeInTheDocument()
    expect(screen.getByLabelText('Descripción')).toHaveValue('Mi cambio pendiente')
    expect(editarBorrador).toHaveBeenCalledTimes(1)

    vi.mocked(obtenerPolitica).mockResolvedValue(conBorrador({ descripcion: 'Versión de otra persona', version: 3 }))
    await user.click(within(alerta).getByRole('button', { name: 'Recargar versión actual' }))

    await waitFor(() => expect(screen.getByLabelText('Descripción')).toHaveValue('Versión de otra persona'))
    expect(editarBorrador).toHaveBeenCalledTimes(1)
  })

  it('un fallo de red avisa sin perder lo escrito', async () => {
    const user = userEvent.setup()
    vi.mocked(editarBorrador).mockRejectedValueOnce(networkError())
    renderEditor()
    const descripcion = await screen.findByLabelText('Descripción')

    await user.type(descripcion, ' extra')
    await user.click(screen.getByRole('button', { name: 'Guardar borrador' }))

    expect(await screen.findByText(/no se pudo conectar/i)).toBeInTheDocument()
    expect(screen.getByLabelText('Descripción')).toHaveValue('Permisos de la organización extra')
    expect(screen.getByText('Hay cambios sin guardar.')).toBeInTheDocument()
  })

  it('descartar pide confirmación y vuelve a la política', async () => {
    const user = userEvent.setup()
    renderEditor()
    await screen.findByLabelText('Descripción')

    await user.click(screen.getByRole('button', { name: 'Descartar borrador' }))
    const confirmacion = await screen.findByRole('alertdialog')
    expect(descartarBorrador).not.toHaveBeenCalled()
    await user.click(within(confirmacion).getByRole('button', { name: 'Descartar borrador' }))

    await waitFor(() => expect(descartarBorrador).toHaveBeenCalledWith('permisos', 20, 1))
    expect(await screen.findByText('Detalle de la política')).toBeInTheDocument()
  })
})

describe('BorradorPage: revisión y publicación', () => {
  async function abrirPublicacion(user: ReturnType<typeof userEvent.setup>) {
    renderEditor()
    await screen.findByLabelText('Descripción')
    await user.click(screen.getByRole('button', { name: 'Revisar y publicar' }))
    return screen.findByRole('dialog', { name: 'Revisar y publicar' })
  }

  it('muestra condiciones, diferencias, inicio, efecto y el aviso de solicitudes anteriores', async () => {
    const dialogo = await abrirPublicacion(userEvent.setup())

    expect(await within(dialogo).findByText('Condiciones resultantes')).toBeInTheDocument()
    expect(within(dialogo).getByText('Diferencias con la versión vigente')).toBeInTheDocument()
    expect(within(dialogo).getByText(/Antes:/).parentElement).toHaveTextContent('2 días calendario')
    expect(within(dialogo).getByText('Se aplicará a las nuevas solicitudes.')).toBeInTheDocument()
    expect(within(dialogo).getByText('Las solicitudes anteriores conservan sus condiciones.')).toBeInTheDocument()
    expect(within(dialogo).getByText('Sustituirá a la versión 1 desde el inicio de vigencia.')).toBeInTheDocument()
    expect(within(dialogo).getByText('Comenzará el 20/10/2026 13:00.')).toBeInTheDocument()
    expect(previsualizarPublicacion).toHaveBeenCalledWith('permisos', 20, { modo: 'inmediato' })
  })

  it('exige motivo y revisor y no publica sin ellos', async () => {
    const user = userEvent.setup()
    const dialogo = await abrirPublicacion(user)
    await within(dialogo).findByText('Condiciones resultantes')

    await user.click(within(dialogo).getByRole('button', { name: 'Publicar versión' }))

    expect(await within(dialogo).findAllByText('Este campo es obligatorio.')).toHaveLength(2)
    expect(publicarVersion).not.toHaveBeenCalled()
  })

  it('publica con motivo, constancia, inicio y una clave de operación, y vuelve a la política', async () => {
    const user = userEvent.setup()
    const dialogo = await abrirPublicacion(user)
    await within(dialogo).findByText('Condiciones resultantes')

    await user.type(within(dialogo).getByLabelText('Motivo del cambio'), 'Ajuste anual')
    await user.type(within(dialogo).getByLabelText('Quién revisó la configuración'), 'Ana Núñez')
    await user.type(within(dialogo).getByLabelText('Observaciones de la revisión'), 'Revisado con el área legal')
    await user.click(within(dialogo).getByRole('button', { name: 'Publicar versión' }))

    await waitFor(() => expect(publicarVersion).toHaveBeenCalledTimes(1))
    expect(publicarVersion).toHaveBeenCalledWith(
      'permisos',
      20,
      { version: 1, motivo: 'Ajuste anual', revision: { revisor: 'Ana Núñez', observaciones: 'Revisado con el área legal' }, inicio: { modo: 'inmediato' } },
      expect.stringMatching(/^[0-9a-f-]{36}$/),
    )
    expect(await screen.findByText('Detalle de la política')).toBeInTheDocument()
  })

  it('un inicio programado pide la fecha y hora y recalcula el resumen', async () => {
    const user = userEvent.setup()
    const dialogo = await abrirPublicacion(user)
    await within(dialogo).findByText('Condiciones resultantes')

    await user.click(within(dialogo).getByLabelText('Programar un inicio'))
    expect(await within(dialogo).findByLabelText('Fecha y hora de inicio')).toBeInTheDocument()
    await user.click(within(dialogo).getByRole('button', { name: 'Publicar versión' }))
    expect(await within(dialogo).findByText('Elige la fecha y hora de inicio.')).toBeInTheDocument()
    expect(publicarVersion).not.toHaveBeenCalled()

    await user.type(within(dialogo).getByLabelText('Fecha y hora de inicio'), '2030-01-15T08:30')
    await waitFor(() => expect(previsualizarPublicacion).toHaveBeenLastCalledWith('permisos', 20, { modo: 'programado', fecha_hora_local: '2030-01-15T08:30' }))
  })

  it('con errores en el resumen el botón de publicar queda bloqueado y se explican', async () => {
    vi.mocked(previsualizarPublicacion).mockResolvedValue(previsualizacion({ errores: { publicacion: ['Ya existe una versión programada para este tipo. Retírala antes de publicar otra.'] } }))
    const dialogo = await abrirPublicacion(userEvent.setup())

    expect(await within(dialogo).findByText(/ya existe una versión programada/i)).toBeInTheDocument()
    expect(within(dialogo).getByRole('button', { name: 'Publicar versión' })).toBeDisabled()
  })

  it('un conflicto al publicar pide recargar y un reintento reutiliza la clave de operación', async () => {
    const user = userEvent.setup()
    vi.mocked(publicarVersion).mockRejectedValueOnce(networkError()).mockRejectedValueOnce(httpError(409, { code: 'BORRADOR_MODIFICADO' }))
    const dialogo = await abrirPublicacion(user)
    await within(dialogo).findByText('Condiciones resultantes')
    await user.type(within(dialogo).getByLabelText('Motivo del cambio'), 'Ajuste')
    await user.type(within(dialogo).getByLabelText('Quién revisó la configuración'), 'Ana')

    await user.click(within(dialogo).getByRole('button', { name: 'Publicar versión' }))
    expect(await within(dialogo).findByText(/no se pudo conectar/i)).toBeInTheDocument()
    await user.click(within(dialogo).getByRole('button', { name: 'Publicar versión' }))
    expect(await within(dialogo).findByText(/el borrador cambió desde que lo revisaste/i)).toBeInTheDocument()

    const claves = vi.mocked(publicarVersion).mock.calls.map((llamada) => llamada[3])
    expect(claves[0]).toBe(claves[1])
  })

  it('el error 422 del servidor aparece junto al campo del motivo', async () => {
    const user = userEvent.setup()
    vi.mocked(publicarVersion).mockRejectedValueOnce(httpError(422, { code: 'VALIDACION', errors: { motivo: ['Máximo 500 caracteres.'] } }))
    const dialogo = await abrirPublicacion(user)
    await within(dialogo).findByText('Condiciones resultantes')
    await user.type(within(dialogo).getByLabelText('Motivo del cambio'), 'Ajuste')
    await user.type(within(dialogo).getByLabelText('Quién revisó la configuración'), 'Ana')

    await user.click(within(dialogo).getByRole('button', { name: 'Publicar versión' }))

    expect(await within(dialogo).findByLabelText('Motivo del cambio')).toHaveAccessibleDescription(/Máximo 500 caracteres/)
  })

  it('con cambios sin guardar primero guarda y luego abre la revisión', async () => {
    const user = userEvent.setup()
    renderEditor()
    const descripcion = await screen.findByLabelText('Descripción')
    await user.type(descripcion, ' nueva')

    await user.click(screen.getByRole('button', { name: 'Revisar y publicar' }))

    await waitFor(() => expect(editarBorrador).toHaveBeenCalledTimes(1))
    expect(await screen.findByRole('dialog', { name: 'Revisar y publicar' })).toBeInTheDocument()
    expect(previsualizarPublicacion).toHaveBeenCalledWith('permisos', 20, { modo: 'inmediato' })
  })

  it('el doble clic en publicar no envía dos solicitudes', async () => {
    const user = userEvent.setup()
    vi.mocked(publicarVersion).mockReturnValue(new Promise(() => undefined))
    const dialogo = await abrirPublicacion(user)
    await within(dialogo).findByText('Condiciones resultantes')
    await user.type(within(dialogo).getByLabelText('Motivo del cambio'), 'Ajuste')
    await user.type(within(dialogo).getByLabelText('Quién revisó la configuración'), 'Ana')

    const boton = within(dialogo).getByRole('button', { name: 'Publicar versión' })
    await user.dblClick(boton)

    await waitFor(() => expect(publicarVersion).toHaveBeenCalledTimes(1))
  })
})
