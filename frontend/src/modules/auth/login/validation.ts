import { z } from 'zod'

/** Replica las reglas de LoginRequest del backend. El correo se recorta antes de validar. */
export const loginSchema = z.object({
  email: z
    .string()
    .trim()
    .min(1, 'Ingresa tu correo electrónico.')
    .pipe(z.email('Ingresa un correo electrónico válido.')),
  password: z.string().min(1, 'Ingresa tu contraseña.'),
})

export type LoginValues = z.infer<typeof loginSchema>
