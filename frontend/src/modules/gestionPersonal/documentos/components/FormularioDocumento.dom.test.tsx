// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { categoria, paginaDeCategorias } from '../../../../test/categoriasFixtures'
import { documento, paginaDeDocumentos } from '../../../../test/documentosFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { listarCategorias } from '../../categorias/api'
import { cargarDocumento, listarDocumentos } from '../api'
import { SeccionDocumentos } from './SeccionDocumentos'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarDocumentos: vi.fn(),
  cargarDocumento: vi.fn(),
}))
vi.mock('../../categorias/api', async (importOriginal) => ({ ...(await importOriginal<typeof import('../../categorias/api')>()), listarCategorias: vi.fn() }))

const pdf = (nombre = 'contrato.pdf', bytes = 800) => new File([new Uint8Array(bytes)], nombre, { type: 'application/pdf' })

function usuario() {
  return userEvent.setup({ applyAccept: false })
}

async function abrirDialogo(user: ReturnType<typeof usuario>) {
  renderRoutes(<Route path="/" element={<SeccionDocumentos colaboradorId={1} />} />, '/')
  await user.click((await screen.findAllByRole('button', { name: 'Subir documento' }))[0])
  return screen.getByRole('dialog')
}

/** Consultas acotadas al diálogo: la sección de fondo también tiene un filtro «Categoría». */
const dlg = () => within(screen.getByRole('dialog'))

async function completar(user: ReturnType<typeof usuario>, archivo: File | null = pdf()) {
  await user.type(await dlg().findByLabelText('Título'), 'Contrato')
  await user.selectOptions(dlg().getByLabelText('Categoría'), '4')
  if (archivo) await user.upload(dlg().getByLabelText('Archivo PDF'), archivo)
}

beforeEach(() => {
  vi.mocked(listarDocumentos).mockReset().mockResolvedValue(paginaDeDocumentos([documento()]))
  vi.mocked(listarCategorias).mockReset().mockResolvedValue(paginaDeCategorias([categoria({ id: 4, nombre: 'Contratos' })]))
  vi.mocked(cargarDocumento).mockReset().mockResolvedValue(documento({ id: 7 }))
})

describe('FormularioDocumento', () => {
  it('sube un PDF válido con la visibilidad desmarcada por defecto y cierra el diálogo', async () => {
    const user = usuario()
    const dialogo = await abrirDialogo(user)
    expect(within(dialogo).getByLabelText(/Visible para el colaborador/)).not.toBeChecked()
    expect(within(dialogo).getByText('Solo PDF, de hasta 10 MB.')).toBeInTheDocument()
    await completar(user)
    await user.click(within(dialogo).getByRole('button', { name: 'Subir' }))

    await waitFor(() => expect(cargarDocumento).toHaveBeenCalledTimes(1))
    const [colaborador, datos, clave] = vi.mocked(cargarDocumento).mock.calls[0]
    expect(colaborador).toBe(1)
    expect(datos).toMatchObject({ titulo: 'Contrato', categoria_id: 4, visible_colaborador: false })
    expect(datos.archivo.name).toBe('contrato.pdf')
    expect(clave).toMatch(/^[0-9a-f-]{36}$/)
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(screen.getByText('Documento subido.')).toBeInTheDocument()
  })

  it('envía la visibilidad cuando se marca', async () => {
    const user = usuario()
    await abrirDialogo(user)
    await completar(user)
    await user.click(dlg().getByLabelText(/Visible para el colaborador/))
    await user.click(screen.getByRole('button', { name: 'Subir' }))

    await waitFor(() => expect(cargarDocumento).toHaveBeenCalledTimes(1))
    expect(vi.mocked(cargarDocumento).mock.calls[0][1]).toMatchObject({ visible_colaborador: true })
  })

  it('muestra los errores junto a cada campo sin enviar', async () => {
    const user = usuario()
    await abrirDialogo(user)
    await user.click(screen.getByRole('button', { name: 'Subir' }))

    expect(await screen.findByText('Este campo es obligatorio.')).toBeInTheDocument()
    expect(screen.getByText('Selecciona una categoría.')).toBeInTheDocument()
    expect(screen.getAllByText('Selecciona un archivo PDF.').length).toBeGreaterThanOrEqual(1)
    expect(screen.getByRole('alert')).toHaveTextContent('Revisa los campos marcados')
    expect(cargarDocumento).not.toHaveBeenCalled()
  })

  it('rechaza un archivo que no es PDF, vacío o mayor de 10 MB sin enviar', async () => {
    const user = usuario()
    await abrirDialogo(user)
    await completar(user, new File(['x'], 'foto.png', { type: 'image/png' }))
    await user.click(screen.getByRole('button', { name: 'Subir' }))
    expect(await screen.findAllByText('El formato no es válido. Selecciona un archivo PDF.')).not.toHaveLength(0)

    await user.upload(dlg().getByLabelText('Archivo PDF'), pdf('vacio.pdf', 0))
    expect((await screen.findAllByText('El archivo está vacío.')).length).toBeGreaterThanOrEqual(1)

    await user.upload(dlg().getByLabelText('Archivo PDF'), pdf('grande.pdf', 10_000_001))
    expect((await screen.findAllByText('El archivo supera el tamaño máximo de 10 MB.')).length).toBeGreaterThanOrEqual(1)
    expect(cargarDocumento).not.toHaveBeenCalled()
  })

  it('un 413 se presenta como tamaño excedido y pide volver a seleccionar el archivo', async () => {
    vi.mocked(cargarDocumento).mockRejectedValueOnce(httpError(413, { code: 'ARCHIVO_DEMASIADO_GRANDE' }))
    const user = usuario()
    await abrirDialogo(user)
    await completar(user)
    await user.click(screen.getByRole('button', { name: 'Subir' }))

    expect((await screen.findAllByText('El archivo supera el tamaño máximo de 10 MB.')).length).toBeGreaterThanOrEqual(1)
    expect(screen.getByText('Vuelve a seleccionar el archivo.')).toBeInTheDocument()
    expect(dlg().getByLabelText('Título')).toHaveValue('Contrato')
  })

  it('un PDF rechazado por el servidor muestra el motivo junto al archivo y conserva título y categoría', async () => {
    vi.mocked(cargarDocumento).mockRejectedValueOnce(
      httpError(422, { code: 'VALIDACION', errors: { archivo: ['El archivo no es un PDF válido o está dañado.'] }, motivo: 'estructura_invalida' }),
    )
    const user = usuario()
    await abrirDialogo(user)
    await completar(user)
    await user.click(screen.getByRole('button', { name: 'Subir' }))

    expect(await screen.findByText('El archivo no es un PDF válido o está dañado.')).toBeInTheDocument()
    expect(dlg().getByLabelText('Título')).toHaveValue('Contrato')
    expect(dlg().getByLabelText('Categoría')).toHaveValue('4')
    expect(screen.getByText('Vuelve a seleccionar el archivo.')).toBeInTheDocument()
  })

  it('una categoría desactivada antes de confirmar se informa sin cambiarla y conserva el archivo', async () => {
    vi.mocked(cargarDocumento).mockRejectedValueOnce(httpError(409, { code: 'CATEGORIA_INACTIVA' }))
    const user = usuario()
    await abrirDialogo(user)
    await completar(user)
    await user.click(screen.getByRole('button', { name: 'Subir' }))

    expect(await screen.findByText(/La categoría que elegiste ya no está activa/)).toBeInTheDocument()
    expect(screen.getByText('Tu archivo sigue seleccionado; puedes reintentar.')).toBeInTheDocument()
    expect(dlg().getByLabelText('Título')).toHaveValue('Contrato')
  })

  it('un fallo de red conserva todo y el reintento reutiliza la misma Idempotency-Key', async () => {
    vi.mocked(cargarDocumento).mockRejectedValueOnce(networkError())
    const user = usuario()
    await abrirDialogo(user)
    await completar(user)
    await user.click(screen.getByRole('button', { name: 'Subir' }))
    expect(await screen.findByText(/No se pudo conectar con el servidor/)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Subir' }))
    await waitFor(() => expect(cargarDocumento).toHaveBeenCalledTimes(2))
    expect(vi.mocked(cargarDocumento).mock.calls[1][2]).toBe(vi.mocked(cargarDocumento).mock.calls[0][2])
  })

  it('una clave reutilizada genera una clave nueva para el siguiente intento', async () => {
    vi.mocked(cargarDocumento).mockRejectedValueOnce(httpError(409, { code: 'CLAVE_DE_OPERACION_REUTILIZADA' }))
    const user = usuario()
    await abrirDialogo(user)
    await completar(user)
    await user.click(screen.getByRole('button', { name: 'Subir' }))
    expect(await screen.findByText(/Esta carga cambió respecto de un intento anterior/)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Subir' }))
    await waitFor(() => expect(cargarDocumento).toHaveBeenCalledTimes(2))
    expect(vi.mocked(cargarDocumento).mock.calls[1][2]).not.toBe(vi.mocked(cargarDocumento).mock.calls[0][2])
  })

  it('un doble clic envía una sola petición', async () => {
    let resolver: (valor: ReturnType<typeof documento>) => void = () => {}
    vi.mocked(cargarDocumento).mockReturnValue(new Promise((r) => (resolver = r)))
    const user = usuario()
    await abrirDialogo(user)
    await completar(user)
    await user.dblClick(screen.getByRole('button', { name: 'Subir' }))

    await waitFor(() => expect(cargarDocumento).toHaveBeenCalledTimes(1))
    resolver(documento())
  })

  it('sin categorías activas explica el requisito, ofrece ir al catálogo y no permite subir', async () => {
    vi.mocked(listarCategorias).mockResolvedValue(paginaDeCategorias([]))
    const user = usuario()
    await abrirDialogo(user)

    expect(await screen.findByText('No hay categorías activas')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ir a categorías documentales' })).toHaveAttribute('href', '/categorias-documentales')
    expect(screen.getByRole('button', { name: 'Subir' })).toBeDisabled()
  })
})
