import { z } from 'zod'

const MAX_NOMBRE = 50
const MAX_TEXTO = 500

const textoRequerido = (vacio: string, max: number, largo: string) => z.string().trim().min(1, vacio).max(max, largo)

const grado = (letra: string) =>
  textoRequerido(
    `Ingresa la descripción del grado ${letra}.`,
    MAX_TEXTO,
    `La descripción del grado ${letra} no puede superar los ${MAX_TEXTO} caracteres.`,
  )

/** Replica las reglas de GuardarCompetenciaRequest del backend. */
export const competenciaSchema = z.object({
  nombre: textoRequerido(
    'Ingresa el nombre de la competencia.',
    MAX_NOMBRE,
    `El nombre no puede superar los ${MAX_NOMBRE} caracteres.`,
  ),
  descripcion: textoRequerido(
    'Ingresa la descripción de la competencia.',
    MAX_TEXTO,
    `La descripción no puede superar los ${MAX_TEXTO} caracteres.`,
  ),
  tipo: z.enum(['Cardinal', 'Especifica'], { error: 'Selecciona el tipo de competencia.' }),
  grados: z.object({
    A: grado('A'),
    B: grado('B'),
    C: grado('C'),
    D: grado('D'),
  }),
})

export type CompetenciaFormValues = z.infer<typeof competenciaSchema>

export const LIMITES = { nombre: MAX_NOMBRE, texto: MAX_TEXTO } as const
