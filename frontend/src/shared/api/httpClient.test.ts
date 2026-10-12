import { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { http, onSessionExpired, toApiError } from './httpClient'

const store = new Map<string, string>()

beforeEach(() => {
  store.clear()
  vi.stubGlobal('localStorage', {
    getItem: (key: string) => store.get(key) ?? null,
    setItem: (key: string, value: string) => void store.set(key, value),
    removeItem: (key: string) => void store.delete(key),
  })
  vi.stubGlobal('window', new EventTarget())
})

afterEach(() => vi.unstubAllGlobals())

function respondWith(status: number, data: unknown = {}) {
  http.defaults.adapter = async (config: InternalAxiosRequestConfig) => {
    const response = { data, status, statusText: '', headers: {}, config }
    if (status >= 400) {
      throw new AxiosError('error', 'ERR_BAD_REQUEST', config, null, response)
    }
    return response
  }
}

describe('httpClient', () => {
  it('adjunta el token Bearer cuando existe', async () => {
    store.set('hrms.token', 'abc')
    let authorization: unknown
    http.defaults.adapter = async (config) => {
      authorization = config.headers.get('Authorization')
      return { data: {}, status: 200, statusText: '', headers: {}, config }
    }

    await http.get('/api/auth/me')

    expect(authorization).toBe('Bearer abc')
  })

  it('no envía Authorization sin token', async () => {
    let authorization: unknown = 'pendiente'
    http.defaults.adapter = async (config) => {
      authorization = config.headers.get('Authorization')
      return { data: {}, status: 200, statusText: '', headers: {}, config }
    }

    await http.post('/api/auth/login', {})

    expect(authorization).toBeFalsy()
  })

  it('ante un 401 con token limpia el token y avisa la sesión expirada', async () => {
    store.set('hrms.token', 'abc')
    respondWith(401, { message: 'No autenticado.' })
    const expired = vi.fn()
    const stop = onSessionExpired(expired)

    await expect(http.get('/api/auth/me')).rejects.toBeInstanceOf(AxiosError)

    expect(store.has('hrms.token')).toBe(false)
    expect(expired).toHaveBeenCalledOnce()
    stop()
  })

  it('un 401 sin token (credenciales de login inválidas) no dispara sesión expirada', async () => {
    respondWith(401, { message: 'Correo o contraseña incorrectos.' })
    const expired = vi.fn()
    const stop = onSessionExpired(expired)

    await expect(http.post('/api/auth/login', {})).rejects.toBeInstanceOf(AxiosError)

    expect(expired).not.toHaveBeenCalled()
    stop()
  })
})

describe('toApiError', () => {
  it('extrae estado, mensaje y errores por campo de un 422', async () => {
    respondWith(422, { message: 'Inválido', errors: { email: ['Ingresa tu correo electrónico.'] } })

    const error = await http.post('/api/auth/login', {}).catch((e: unknown) => e)

    expect(toApiError(error)).toEqual({
      status: 422,
      message: 'Inválido',
      fieldErrors: { email: ['Ingresa tu correo electrónico.'] },
      isNetworkError: false,
    })
  })

  it('marca como error de red cuando no hay respuesta', () => {
    const error = new AxiosError('Network Error', 'ERR_NETWORK')

    expect(toApiError(error)).toEqual({ fieldErrors: {}, isNetworkError: true })
  })

  it('trata errores desconocidos como error de red', () => {
    expect(toApiError(new Error('x')).isNetworkError).toBe(true)
  })
})
