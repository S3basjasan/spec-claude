import { describe, expect, it } from 'vitest'
import { httpError, networkError } from '../../../test/apiErrors'
import { documentoSchema, errorDeArchivo, formatearTamano, LIMITE_TITULO, problemaDeCarga } from './validation'

const archivo = (nombre: string, bytes: number) => new File([new Uint8Array(bytes)], nombre, { type: 'application/pdf' })

describe('documentoSchema', () => {
  it('acepta título y categoría válidos y recorta el título', () => {
    const resultado = documentoSchema.safeParse({ titulo: '  Contrato  ', categoria_id: '3', visible_colaborador: false })
    expect(resultado.success).toBe(true)
    expect(resultado.data?.titulo).toBe('Contrato')
  })

  it('exige título y categoría', () => {
    expect(documentoSchema.safeParse({ titulo: '   ', categoria_id: '', visible_colaborador: false }).success).toBe(false)
  })

  it('acepta 200 caracteres y rechaza 201', () => {
    expect(documentoSchema.safeParse({ titulo: 'a'.repeat(LIMITE_TITULO), categoria_id: '1', visible_colaborador: false }).success).toBe(true)
    expect(documentoSchema.safeParse({ titulo: 'a'.repeat(LIMITE_TITULO + 1), categoria_id: '1', visible_colaborador: false }).success).toBe(false)
  })
})

describe('errorDeArchivo', () => {
  it('exige un archivo PDF no vacío de hasta 10 000 000 bytes', () => {
    expect(errorDeArchivo(null)).toContain('Selecciona un archivo PDF')
    expect(errorDeArchivo(archivo('foto.png', 10))).toContain('formato no es válido')
    expect(errorDeArchivo(archivo('a.pdf', 0))).toContain('vacío')
    expect(errorDeArchivo(archivo('a.pdf', 10_000_001))).toContain('10 MB')
    expect(errorDeArchivo(archivo('a.pdf', 10_000_000))).toBeNull()
    expect(errorDeArchivo(archivo('A.PDF', 5))).toBeNull()
  })
})

describe('problemaDeCarga', () => {
  it('mapea un 422 a los campos y pide reseleccionar solo si el archivo fue rechazado', () => {
    const conArchivo = problemaDeCarga(httpError(422, { code: 'VALIDACION', errors: { archivo: ['El formato no es válido.'] } }))
    expect(conArchivo).toMatchObject({ archivo: 'El formato no es válido.', reseleccionar: true, aviso: null })

    const soloTitulo = problemaDeCarga(httpError(422, { code: 'VALIDACION', errors: { titulo: ['Este campo es obligatorio.'] } }))
    expect(soloTitulo).toMatchObject({ titulo: 'Este campo es obligatorio.', reseleccionar: false })
  })

  it('distingue 413, categoría desactivada, clave reutilizada, 404, 503, escritura deshabilitada y red', () => {
    expect(problemaDeCarga(httpError(413, { code: 'ARCHIVO_DEMASIADO_GRANDE' }))).toMatchObject({ reseleccionar: true })
    expect(problemaDeCarga(httpError(409, { code: 'CATEGORIA_INACTIVA' })).aviso).toBe('categoria')
    expect(problemaDeCarga(httpError(409, { code: 'CLAVE_DE_OPERACION_REUTILIZADA' })).aviso).toBe('clave')
    expect(problemaDeCarga(httpError(404, { code: 'FICHA_NO_ENCONTRADA' })).aviso).toBe('noExiste')
    expect(problemaDeCarga(httpError(503, { code: 'ALMACENAMIENTO_NO_DISPONIBLE' })).aviso).toBe('almacenamiento')
    expect(problemaDeCarga(httpError(503, { code: 'ESCRITURA_DESHABILITADA' })).aviso).toBe('deshabilitada')
    expect(problemaDeCarga(networkError()).aviso).toBe('red')
    expect(problemaDeCarga(httpError(500)).aviso).toBe('inesperado')
  })
})

describe('formatearTamano', () => {
  it('muestra bytes, KB y MB', () => {
    expect(formatearTamano(500)).toBe('500 B')
    expect(formatearTamano(482_113)).toBe('482 KB')
    expect(formatearTamano(1_250_000)).toBe('1,3 MB')
  })
})
