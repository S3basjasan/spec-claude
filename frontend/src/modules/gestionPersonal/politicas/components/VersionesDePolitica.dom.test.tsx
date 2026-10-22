// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError, networkError } from '../../../../test/apiErrors'
import { borrador, version } from '../../../../test/politicasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { compararVersion, retirarVersion } from '../api'
import { ComparacionDeVersiones } from './ComparacionDeVersiones'
import { DialogoDeRetiro } from './DialogoDeRetiro'
import { LineaDeVersiones } from './LineaDeVersiones'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  compararVersion: vi.fn(),
  retirarVersion: vi.fn(),
}))

const programada = version({ id: 11, numero: 2, estado: 'Programada', inicio_vigencia_local: '2026-11-05T08:00', motivo_cambio: 'Subir la anticipación' })
const vigente = version({ id: 10, numero: 1, estado: 'Vigente' })
const retirada = version({ id: 12, numero: 3, estado: 'Retirada', retiro: { por: 1, en: '2026-10-25T10:00:00Z', motivo: 'Cambió el criterio' } })

function renderConQuery(elemento: ReactNode) {
  return renderRoutes(<Route path="/" element={elemento} />, '/')
}

beforeEach(() => {
  vi.mocked(compararVersion).mockReset().mockResolvedValue({ contra: vigente, diferencias: [{ clave: 'anticipacion_dias', etiqueta: 'Anticipación mínima (días calendario)', anterior: 2, nuevo: 5 }] })
  vi.mocked(retirarVersion).mockReset().mockResolvedValue({ ...programada, estado: 'Retirada', retiro: { por: 1, en: '2026-10-20T10:00:00Z', motivo: 'Motivo' } })
})

describe('LineaDeVersiones', () => {
  const acciones = { onCrearDesde: vi.fn(), onRetirar: vi.fn(), creandoDesdeId: null }

  it('muestra cada versión con su estado, vigencia, motivo, revisión y responsables', () => {
    renderConQuery(<LineaDeVersiones tipo="permisos" versiones={[retirada, programada, vigente]} hayBorrador={false} {...acciones} />)

    const lista = screen.getByRole('list', { name: /versiones publicadas/i })
    const items = within(lista).getAllByRole('listitem').filter((li) => li.querySelector('h3'))
    expect(items).toHaveLength(3)
    expect(within(items[0]).getByText('Retirada')).toBeInTheDocument()
    expect(within(items[0]).getByText(/Cambió el criterio/)).toBeInTheDocument()
    expect(within(items[1]).getByText('Programada')).toBeInTheDocument()
    expect(within(items[1]).getByText('05/11/2026 08:00')).toBeInTheDocument()
    expect(within(items[1]).getByText('Subir la anticipación')).toBeInTheDocument()
    expect(within(items[2]).getByText('Vigente')).toBeInTheDocument()
    expect(within(items[2]).getByText('Ana Núñez')).toBeInTheDocument()
  })

  it('una versión publicada no muestra controles de edición directa y solo la programada se retira', () => {
    renderConQuery(<LineaDeVersiones tipo="permisos" versiones={[programada, vigente]} hayBorrador={false} {...acciones} />)

    expect(screen.queryByRole('button', { name: /editar|eliminar|guardar/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Retirar versión' })).toHaveLength(1)
  })

  it('crear un borrador a partir de una versión se bloquea si ya hay un borrador', async () => {
    const onCrearDesde = vi.fn()
    const user = userEvent.setup()
    const { unmount } = renderConQuery(<LineaDeVersiones tipo="permisos" versiones={[vigente]} hayBorrador={false} {...acciones} onCrearDesde={onCrearDesde} />)

    await user.click(screen.getByRole('button', { name: 'Crear borrador a partir de esta versión' }))
    expect(onCrearDesde).toHaveBeenCalledWith(vigente)
    unmount()

    renderConQuery(<LineaDeVersiones tipo="permisos" versiones={[vigente]} hayBorrador {...acciones} />)
    expect(screen.getByRole('button', { name: 'Crear borrador a partir de esta versión' })).toBeDisabled()
  })

  it('comparar abre y cierra las diferencias con la vigente', async () => {
    const user = userEvent.setup()
    renderConQuery(<LineaDeVersiones tipo="permisos" versiones={[programada, vigente]} hayBorrador={false} {...acciones} />)

    await user.click(screen.getAllByRole('button', { name: 'Comparar con la vigente' })[0])
    expect(await screen.findByText('Anticipación mínima (días calendario)')).toBeInTheDocument()
    expect(compararVersion).toHaveBeenCalledWith('permisos', 11, expect.anything())

    await user.click(screen.getByRole('button', { name: 'Ocultar comparación' }))
    expect(screen.queryByText('Anticipación mínima (días calendario)')).not.toBeInTheDocument()
  })

  it('sin versiones publicadas lo indica', () => {
    renderConQuery(<LineaDeVersiones tipo="permisos" versiones={[]} hayBorrador={false} {...acciones} />)

    expect(screen.getByText('Aún no hay versiones publicadas.')).toBeInTheDocument()
  })
})

describe('ComparacionDeVersiones', () => {
  it('lista las diferencias antes y después', async () => {
    renderConQuery(<ComparacionDeVersiones tipo="permisos" versionId={11} />)

    expect(await screen.findByText(/Antes:/)).toBeInTheDocument()
    expect(screen.getByText(/Antes:/).parentElement).toHaveTextContent('2 días calendario')
    expect(screen.getByText(/Después:/).parentElement).toHaveTextContent('5 días calendario')
  })

  it('sin versión vigente todas las condiciones son nuevas y sin diferencias lo dice', async () => {
    vi.mocked(compararVersion).mockResolvedValueOnce({ contra: null, diferencias: [] })
    const { unmount } = renderConQuery(<ComparacionDeVersiones tipo="permisos" versionId={11} />)
    expect(await screen.findByText(/todas las condiciones son nuevas/i)).toBeInTheDocument()
    unmount()

    vi.mocked(compararVersion).mockResolvedValueOnce({ contra: vigente, diferencias: [] })
    renderConQuery(<ComparacionDeVersiones tipo="permisos" versionId={11} />)
    expect(await screen.findByText('No hay diferencias con la versión vigente.')).toBeInTheDocument()
  })

  it('un error muestra un aviso y no un resultado vacío', async () => {
    vi.mocked(compararVersion).mockRejectedValue(httpError(404))
    renderConQuery(<ComparacionDeVersiones tipo="permisos" versionId={11} />)

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo calcular la comparación.')
  })
})

describe('DialogoDeRetiro', () => {
  function abrir(onRetirada = vi.fn(), onCerrar = vi.fn()) {
    renderConQuery(<DialogoDeRetiro tipo="permisos" version={programada} onCerrar={onCerrar} onRetirada={onRetirada} />)
    return { onRetirada, onCerrar }
  }

  it('advierte que la versión anterior continuará aplicándose y exige justificación', async () => {
    const user = userEvent.setup()
    const { onRetirada } = abrir()

    expect(screen.getByRole('dialog', { name: 'Retirar versión programada' })).toBeInTheDocument()
    expect(screen.getByText('La versión anterior continuará aplicándose.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Retirar versión' }))
    expect(await screen.findByText('Este campo es obligatorio.')).toBeInTheDocument()
    expect(retirarVersion).not.toHaveBeenCalled()
    expect(onRetirada).not.toHaveBeenCalled()
  })

  it('retira con la justificación y una clave de operación', async () => {
    const user = userEvent.setup()
    const { onRetirada } = abrir()

    await user.type(screen.getByLabelText('Justificación del retiro'), '  Cambió el criterio  ')
    await user.click(screen.getByRole('button', { name: 'Retirar versión' }))

    await waitFor(() => expect(onRetirada).toHaveBeenCalledTimes(1))
    expect(retirarVersion).toHaveBeenCalledWith('permisos', 11, 'Cambió el criterio', expect.stringMatching(/^[0-9a-f-]{36}$/))
  })

  it('un reintento tras un fallo de red reutiliza la clave y un texto distinto genera otra', async () => {
    const user = userEvent.setup()
    vi.mocked(retirarVersion).mockRejectedValueOnce(networkError())
    abrir()

    await user.type(screen.getByLabelText('Justificación del retiro'), 'Motivo A')
    await user.click(screen.getByRole('button', { name: 'Retirar versión' }))
    expect(await screen.findByText(/no se pudo conectar/i)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Retirar versión' }))
    await waitFor(() => expect(retirarVersion).toHaveBeenCalledTimes(2))

    const claves = vi.mocked(retirarVersion).mock.calls.map((llamada) => llamada[3])
    expect(claves[0]).toBe(claves[1])

    vi.mocked(retirarVersion).mockRejectedValueOnce(networkError())
    await user.type(screen.getByLabelText('Justificación del retiro'), ' B')
    await user.click(screen.getByRole('button', { name: 'Retirar versión' }))
    await waitFor(() => expect(retirarVersion).toHaveBeenCalledTimes(3))
    expect(vi.mocked(retirarVersion).mock.calls[2][3]).not.toBe(claves[0])
  })

  it('si la vigencia ya comenzó explica que se sustituye publicando otra', async () => {
    const user = userEvent.setup()
    vi.mocked(retirarVersion).mockRejectedValueOnce(httpError(409, { code: 'VIGENCIA_INICIADA' }))
    abrir()

    await user.type(screen.getByLabelText('Justificación del retiro'), 'Tarde')
    await user.click(screen.getByRole('button', { name: 'Retirar versión' }))

    expect(await screen.findByText(/ya comenzó su vigencia: no puede retirarse/i)).toBeInTheDocument()
  })

  it('el error 422 de la justificación aparece junto al campo', async () => {
    const user = userEvent.setup()
    vi.mocked(retirarVersion).mockRejectedValueOnce(httpError(422, { code: 'VALIDACION', errors: { justificacion: ['Máximo 500 caracteres.'] } }))
    abrir()

    await user.type(screen.getByLabelText('Justificación del retiro'), 'Algo')
    await user.click(screen.getByRole('button', { name: 'Retirar versión' }))

    expect(await screen.findByLabelText('Justificación del retiro')).toHaveAccessibleDescription(/Máximo 500 caracteres/)
  })

  it('cancelar cierra sin retirar', async () => {
    const user = userEvent.setup()
    const { onCerrar } = abrir()

    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(onCerrar).toHaveBeenCalled()
    expect(retirarVersion).not.toHaveBeenCalled()
    expect(borrador().estado).toBe('Borrador')
  })
})
