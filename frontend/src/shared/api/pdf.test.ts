// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import { toApiError, http } from './httpClient'
import { descargarBlob, obtenerPdf, RespuestaNoEsPdfError } from './pdf'
import { httpError } from '../../test/apiErrors'

afterEach(() => vi.restoreAllMocks())

const pdf = () => new Blob(['%PDF-1.4'], { type: 'application/pdf' })

describe('obtenerPdf', () => {
  it('devuelve el blob y el nombre propuesto por el servidor', async () => {
    vi.spyOn(http, 'get').mockResolvedValue({
      data: pdf(),
      headers: { 'content-type': 'application/pdf', 'content-disposition': 'attachment; filename="Ficha_EMP-001.pdf"' },
    })

    const resultado = await obtenerPdf('/api/x')

    expect(resultado.nombreDeArchivo).toBe('Ficha_EMP-001.pdf')
    expect(resultado.blob).toBeInstanceOf(Blob)
  })

  it('rechaza una respuesta que no es PDF', async () => {
    vi.spyOn(http, 'get').mockResolvedValue({ data: new Blob(['{"message":"x"}']), headers: { 'content-type': 'application/json' } })

    await expect(obtenerPdf('/api/x')).rejects.toBeInstanceOf(RespuestaNoEsPdfError)
  })

  it('lee un error JSON recibido como blob para que toApiError lo interprete', async () => {
    const error = httpError(503, new Blob([JSON.stringify({ message: 'No disponible', code: 'EXPORTACION_NO_DISPONIBLE' })]))
    vi.spyOn(http, 'get').mockRejectedValue(error)

    const capturado = await obtenerPdf('/api/x').catch((e: unknown) => e)

    expect(toApiError(capturado)).toMatchObject({ status: 503, code: 'EXPORTACION_NO_DISPONIBLE', message: 'No disponible' })
  })

  it('un cuerpo de error ilegible no rompe la interpretación', async () => {
    vi.spyOn(http, 'get').mockRejectedValue(httpError(500, new Blob(['no es json'])))

    const capturado = await obtenerPdf('/api/x').catch((e: unknown) => e)

    expect(toApiError(capturado)).toMatchObject({ status: 500 })
  })
})

describe('descargarBlob', () => {
  it('descarga con un enlace temporal y libera la URL', () => {
    const crear = vi.fn(() => 'blob:prueba')
    const revocar = vi.fn()
    Object.assign(URL, { createObjectURL: crear, revokeObjectURL: revocar })
    vi.useFakeTimers()
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

    descargarBlob(pdf(), 'Ficha_EMP-001.pdf')
    vi.runAllTimers()

    expect(click).toHaveBeenCalledTimes(1)
    expect(revocar).toHaveBeenCalledWith('blob:prueba')
    vi.useRealTimers()
  })
})
