import { z } from 'zod'
import type { DatosFicha } from './api'
import { fichasText } from './text'

export const LIMITES = {
  identificacion: 50,
  nombres: 150,
  apellidos: 150,
  nacionalidad: 100,
  genero: 80,
  estadoCivil: 80,
  direccion: 500,
  telefono: 30,
  correo: 254,
  emergenciaNombre: 150,
  emergenciaRelacion: 100,
  emergenciaTelefono: 30,
} as const

const text = fichasText.validation
const PATRON_TELEFONO = /^\+?[0-9 ()-]+$/
const PATRON_CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

/** Fecha de hoy (AAAA-MM-DD) según el reloj del navegador; el servidor decide con la zona del tenant. */
export function hoyIso(ahora: Date = new Date()): string {
  const mes = String(ahora.getMonth() + 1).padStart(2, '0')
  const dia = String(ahora.getDate()).padStart(2, '0')
  return `${ahora.getFullYear()}-${mes}-${dia}`
}

const opcional = (max: number) => z.string().trim().max(max, text.tooLong(max))

/** Replica las reglas de GuardarColaboradorRequest y del dominio del backend. */
export const fichaSchema = z
  .object({
    tipo_identificacion: z.enum(['Cedula', 'Pasaporte', 'Otro']),
    numero_identificacion: z.string().trim().min(1, text.required).max(LIMITES.identificacion, text.tooLong(LIMITES.identificacion)),
    nombres: z.string().trim().min(1, text.required).max(LIMITES.nombres, text.tooLong(LIMITES.nombres)),
    apellidos: z.string().trim().min(1, text.required).max(LIMITES.apellidos, text.tooLong(LIMITES.apellidos)),
    fecha_nacimiento: z.string().trim(),
    nacionalidad: opcional(LIMITES.nacionalidad),
    genero: opcional(LIMITES.genero),
    estado_civil: opcional(LIMITES.estadoCivil),
    numero_hijos: z.string().trim(),
    direccion: opcional(LIMITES.direccion),
    telefono: opcional(LIMITES.telefono),
    correo_contacto: opcional(LIMITES.correo),
    emergencia_nombre: opcional(LIMITES.emergenciaNombre),
    emergencia_relacion: opcional(LIMITES.emergenciaRelacion),
    emergencia_telefono: opcional(LIMITES.emergenciaTelefono),
  })
  .superRefine((valores, contexto) => {
    const problema = (path: keyof FichaFormValues, message: string) => contexto.addIssue({ code: 'custom', path: [path], message })

    if (valores.fecha_nacimiento !== '') {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(valores.fecha_nacimiento) || Number.isNaN(Date.parse(valores.fecha_nacimiento))) {
        problema('fecha_nacimiento', text.invalidDate)
      } else if (valores.fecha_nacimiento > hoyIso()) {
        problema('fecha_nacimiento', text.futureDate)
      }
    }
    if (valores.numero_hijos !== '' && !/^\d+$/.test(valores.numero_hijos)) {
      problema('numero_hijos', text.children)
    }
    if (valores.telefono !== '' && !PATRON_TELEFONO.test(valores.telefono)) {
      problema('telefono', text.phone)
    }
    if (valores.correo_contacto !== '' && !PATRON_CORREO.test(valores.correo_contacto)) {
      problema('correo_contacto', text.email)
    }
    if (valores.emergencia_telefono !== '' && !PATRON_TELEFONO.test(valores.emergencia_telefono)) {
      problema('emergencia_telefono', text.phone)
    }
    const hayEmergencia = [valores.emergencia_nombre, valores.emergencia_relacion, valores.emergencia_telefono].some((v) => v !== '')
    if (hayEmergencia && valores.emergencia_nombre === '') problema('emergencia_nombre', text.emergencyName)
    if (hayEmergencia && valores.emergencia_telefono === '') problema('emergencia_telefono', text.emergencyPhone)
  })

/** Valores del formulario: todo texto (vacío = sin dato). */
export type FichaFormValues = z.input<typeof fichaSchema>

const nulo = (valor: string) => (valor.trim() === '' ? null : valor.trim())

/** Convierte los valores del formulario al cuerpo de la API (vacío → null; hijos → entero). */
export function datosDeFormulario(valores: FichaFormValues): DatosFicha {
  return {
    tipo_identificacion: valores.tipo_identificacion,
    numero_identificacion: valores.numero_identificacion.trim(),
    nombres: valores.nombres.trim(),
    apellidos: valores.apellidos.trim(),
    fecha_nacimiento: nulo(valores.fecha_nacimiento),
    nacionalidad: nulo(valores.nacionalidad),
    genero: nulo(valores.genero),
    estado_civil: nulo(valores.estado_civil),
    numero_hijos: valores.numero_hijos.trim() === '' ? null : Number(valores.numero_hijos),
    direccion: nulo(valores.direccion),
    telefono: nulo(valores.telefono),
    correo_contacto: nulo(valores.correo_contacto),
    emergencia_nombre: nulo(valores.emergencia_nombre),
    emergencia_relacion: nulo(valores.emergencia_relacion),
    emergencia_telefono: nulo(valores.emergencia_telefono),
  }
}

export function valoresVacios(): FichaFormValues {
  return {
    tipo_identificacion: 'Cedula',
    numero_identificacion: '',
    nombres: '',
    apellidos: '',
    fecha_nacimiento: '',
    nacionalidad: '',
    genero: '',
    estado_civil: '',
    numero_hijos: '',
    direccion: '',
    telefono: '',
    correo_contacto: '',
    emergencia_nombre: '',
    emergencia_relacion: '',
    emergencia_telefono: '',
  }
}

/** Valores del formulario a partir de una ficha existente. */
export function valoresDeFicha(ficha: DatosFicha): FichaFormValues {
  const texto = (valor: string | number | null) => (valor === null ? '' : String(valor))
  return {
    tipo_identificacion: ficha.tipo_identificacion,
    numero_identificacion: ficha.numero_identificacion,
    nombres: ficha.nombres,
    apellidos: ficha.apellidos,
    fecha_nacimiento: texto(ficha.fecha_nacimiento),
    nacionalidad: texto(ficha.nacionalidad),
    genero: texto(ficha.genero),
    estado_civil: texto(ficha.estado_civil),
    numero_hijos: texto(ficha.numero_hijos),
    direccion: texto(ficha.direccion),
    telefono: texto(ficha.telefono),
    correo_contacto: texto(ficha.correo_contacto),
    emergencia_nombre: texto(ficha.emergencia_nombre),
    emergencia_relacion: texto(ficha.emergencia_relacion),
    emergencia_telefono: texto(ficha.emergencia_telefono),
  }
}

/** Solo los campos que difieren de la ficha original (PATCH parcial). */
export function cambiosDe(original: DatosFicha, nuevos: DatosFicha): Partial<DatosFicha> {
  const cambios: Partial<DatosFicha> = {}
  for (const campo of Object.keys(nuevos) as (keyof DatosFicha)[]) {
    if (nuevos[campo] !== original[campo]) {
      ;(cambios as Record<string, unknown>)[campo] = nuevos[campo]
    }
  }
  return cambios
}
