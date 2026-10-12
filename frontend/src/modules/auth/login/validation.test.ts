import { describe, expect, it } from 'vitest'
import { loginSchema } from './validation'

function errorsOf(values: { email: string; password: string }) {
  const result = loginSchema.safeParse(values)
  if (result.success) return {}
  const fields: Record<string, string> = {}
  for (const issue of result.error.issues) {
    fields[String(issue.path[0])] ??= issue.message
  }
  return fields
}

describe('loginSchema', () => {
  it('acepta correo y contraseña válidos y recorta el correo', () => {
    const result = loginSchema.parse({ email: '  admin@hrms.test ', password: 'x' })

    expect(result.email).toBe('admin@hrms.test')
  })

  it('exige correo y contraseña', () => {
    expect(errorsOf({ email: '', password: '' })).toEqual({
      email: 'Ingresa tu correo electrónico.',
      password: 'Ingresa tu contraseña.',
    })
  })

  it('rechaza un correo con formato inválido', () => {
    expect(errorsOf({ email: 'no-es-correo', password: 'x' })).toEqual({
      email: 'Ingresa un correo electrónico válido.',
    })
  })

  it('trata un correo de solo espacios como vacío', () => {
    expect(errorsOf({ email: '   ', password: 'x' }).email).toBe('Ingresa tu correo electrónico.')
  })
})
