import { AxiosError, AxiosHeaders } from 'axios'

/** Crea un AxiosError con respuesta, como lo devolvería el cliente HTTP ante un 401, 422, etc. */
export function httpError(status: number, data: unknown = {}): AxiosError {
  const config = { headers: new AxiosHeaders() }
  return new AxiosError('error', 'ERR_BAD_REQUEST', config, null, {
    status,
    data,
    statusText: '',
    headers: {},
    config,
  })
}

/** Error sin respuesta (servidor caído, sin red). */
export function networkError(): AxiosError {
  return new AxiosError('Network Error', 'ERR_NETWORK')
}
