import { z } from 'zod'
import { dnfText } from './text'

const t = dnfText.admin.paso1

/** Paso 1: alcance obligatorio; colaborador solo si Individual y departamento solo si Departamento. */
export const seleccionSchema = z
  .object({
    alcance: z.enum(['Individual', 'Departamento'], { error: t.errorAlcance }),
    colaborador_id: z.number().int().positive().nullable(),
    departamento_id: z.number().int().positive().nullable(),
  })
  .superRefine((valor, contexto) => {
    if (valor.alcance === 'Individual' && valor.colaborador_id === null) {
      contexto.addIssue({ code: 'custom', path: ['colaborador_id'], message: t.errorColaborador })
    }
    if (valor.alcance === 'Departamento' && valor.departamento_id === null) {
      contexto.addIssue({ code: 'custom', path: ['departamento_id'], message: t.errorDepartamento })
    }
  })

export type Seleccion = z.infer<typeof seleccionSchema>

const fecha = z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional()

/** Filtros del historial: la fecha inicial no puede ser posterior a la final. */
export const filtrosHistorialSchema = z
  .object({
    fecha_desde: fecha,
    fecha_hasta: fecha,
    colaborador_id: z.number().int().positive().optional(),
    departamento_id: z.number().int().positive().optional(),
    prioridad: z.enum(['Critica', 'Alta', 'Media', 'Baja']).optional(),
    page: z.number().int().min(1).optional(),
    per_page: z.number().int().min(1).max(100).optional(),
  })
  .refine((f) => !f.fecha_desde || !f.fecha_hasta || f.fecha_desde <= f.fecha_hasta, {
    path: ['fecha_hasta'],
    message: dnfText.historial.filtros.errorRango,
  })
