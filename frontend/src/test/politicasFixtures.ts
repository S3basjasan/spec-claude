import type { Definicion, PoliticaAplicable, PoliticaDetalle, PoliticaResumen, Previsualizacion, TipoPolitica, Version } from '../modules/gestionPersonal/politicas/api'

const REGLAS_NOVEDAD = [
  { clave: 'NOV-RESUELVE-ADMIN', texto: 'El administrador de Talento Humano resuelve las solicitudes que requieren aprobación.' },
  { clave: 'COM-AISLAMIENTO', texto: 'Ninguna política concede acceso entre organizaciones ni permisos que la persona no tenga.' },
]

const REGLAS_OFFBOARDING = [
  { clave: 'OFF-MOTIVO-FECHA', texto: 'El motivo y la fecha efectiva de salida son siempre obligatorios.' },
  { clave: 'OFF-SIN-RETENCION', texto: 'Las tareas pendientes no prolongan el vínculo, no mantienen ocupada la posición ni impiden registrar la salida.' },
]

const LIMITES = { descripcion: 1000, motivo: 500, justificacion_retiro: 500, revisor: 150, observaciones_revision: 1000, tarea_nombre: 150, tarea_descripcion: 500, max_tareas: 30 }

export function definicionDe(tipo: TipoPolitica): Definicion {
  if (tipo === 'offboarding') {
    return {
      categoria: 'Offboarding',
      parametros: [{ clave: 'tareas', tipo: 'tareas', etiqueta: 'Tareas del cierre administrativo', ayuda: 'Plantilla que se copia a cada proceso de salida.', obligatorio: true }],
      reglas_fijas: REGLAS_OFFBOARDING,
      limites: LIMITES,
    }
  }

  const parametros: Definicion['parametros'] = [
    { clave: 'disponible', tipo: 'booleano', etiqueta: 'Permite nuevas solicitudes', ayuda: 'Si se desactiva, no se aceptan nuevas solicitudes.', obligatorio: true },
    { clave: 'justificacion_obligatoria', tipo: 'booleano', etiqueta: 'Exige justificación', ayuda: 'La solicitud debe incluir una justificación.', obligatorio: true },
    { clave: 'documento_obligatorio', tipo: 'booleano', etiqueta: 'Exige documento de respaldo', ayuda: 'Debe existir un respaldo válido.', obligatorio: true },
  ]
  if (tipo !== 'ausencias') {
    parametros.push({ clave: 'anticipacion_dias', tipo: 'entero', etiqueta: 'Anticipación mínima (días calendario)', ayuda: 'Días calendario enteros, iguales o mayores que 0.', obligatorio: true, minimo: 0 })
  }
  return { categoria: 'Novedad', parametros, reglas_fijas: REGLAS_NOVEDAD, limites: LIMITES }
}

export function version(cambios: Partial<Version> = {}): Version {
  return {
    id: 10,
    numero: 1,
    estado: 'Vigente',
    descripcion: 'Permisos de la organización',
    parametros: { disponible: true, justificacion_obligatoria: true, documento_obligatorio: false, anticipacion_dias: 2 },
    inicio_vigencia: '2026-10-19T13:00:00Z',
    inicio_vigencia_local: '2026-10-19T08:00',
    motivo_cambio: 'Política inicial',
    revision: { revisor: 'Ana Núñez', observaciones: 'Revisado con Talento Humano', revision_en: '2026-10-19T13:00:00Z' },
    publicada_por: 1,
    publicada_en: '2026-10-19T13:00:00Z',
    retiro: null,
    base_version_id: null,
    version: 1,
    created_by: 1,
    created_at: '2026-10-19T12:00:00Z',
    updated_by: 1,
    updated_at: '2026-10-19T13:00:00Z',
    ...cambios,
  }
}

export function borrador(cambios: Partial<Version> = {}): Version {
  return version({
    id: 20, numero: null, estado: 'Borrador', descripcion: '', parametros: {}, inicio_vigencia: null, inicio_vigencia_local: null,
    motivo_cambio: null, revision: null, publicada_por: null, publicada_en: null, ...cambios,
  })
}

export function resumen(cambios: Partial<PoliticaResumen> = {}): PoliticaResumen {
  return {
    tipo: 'permisos', nombre: 'Permisos', categoria: 'Novedad', situacion: 'SinConfigurar', disponible: null,
    version_vigente: null, version_programada: null, tiene_borrador: false, borrador_id: null, ...cambios,
  }
}

export function listaDeResumenes(): PoliticaResumen[] {
  return [
    resumen({ situacion: 'Vigente', disponible: true, version_vigente: { id: 10, numero: 2, inicio_vigencia: '2026-10-19T13:00:00Z', inicio_vigencia_local: '2026-10-19T08:00' } }),
    resumen({ tipo: 'ausencias', nombre: 'Ausencias', situacion: 'NoAdmiteSolicitudes', disponible: false, version_vigente: { id: 11, numero: 1, inicio_vigencia: '2026-10-01T13:00:00Z', inicio_vigencia_local: '2026-10-01T08:00' } }),
    resumen({ tipo: 'licencias', nombre: 'Licencias' }),
    resumen({ tipo: 'vacaciones', nombre: 'Vacaciones', tiene_borrador: true, borrador_id: 30 }),
    resumen({ tipo: 'ascensos', nombre: 'Ascensos', categoria: 'Movimiento' }),
    resumen({ tipo: 'traslados', nombre: 'Traslados', categoria: 'Movimiento' }),
    resumen({ tipo: 'offboarding', nombre: 'Offboarding', categoria: 'Offboarding' }),
  ]
}

export function detalle(tipo: TipoPolitica = 'permisos', cambios: Partial<PoliticaDetalle> = {}): PoliticaDetalle {
  const vigente = version()
  return {
    resumen: resumen({ tipo, nombre: tipo.charAt(0).toUpperCase() + tipo.slice(1), situacion: 'Vigente', disponible: true, version_vigente: { id: 10, numero: 1, inicio_vigencia: vigente.inicio_vigencia, inicio_vigencia_local: vigente.inicio_vigencia_local } }),
    definicion: definicionDe(tipo),
    vigente,
    programada: null,
    borrador: null,
    versiones: [vigente],
    ...cambios,
  }
}

export function detalleSinConfigurar(tipo: TipoPolitica = 'permisos'): PoliticaDetalle {
  return { resumen: resumen({ tipo, nombre: tipo.charAt(0).toUpperCase() + tipo.slice(1) }), definicion: definicionDe(tipo), vigente: null, programada: null, borrador: null, versiones: [] }
}

export function previsualizacion(cambios: Partial<Previsualizacion> = {}): Previsualizacion {
  return {
    condiciones: { numero: 2, tipo: 'Permisos', parametros: { disponible: true, justificacion_obligatoria: true, documento_obligatorio: false, anticipacion_dias: 4 }, reglas_fijas: REGLAS_NOVEDAD },
    diferencias: [{ clave: 'anticipacion_dias', etiqueta: 'Anticipación mínima (días calendario)', anterior: 2, nuevo: 4 }],
    inicio_vigencia: '2026-10-20T18:00:00Z',
    inicio_vigencia_local: '2026-10-20T13:00',
    efecto: { momento: '2026-10-20T18:00:00Z', aplica_a: 'nuevas solicitudes', solicitudes_anteriores: 'conservan sus condiciones', sustituye: 1 },
    errores: {},
    ...cambios,
  }
}

export function aplicable(cambios: Partial<PoliticaAplicable> = {}): PoliticaAplicable {
  return {
    tipo: 'permisos', situacion: 'Vigente', numero: 2, inicio_vigencia: '2026-10-19T13:00:00Z',
    parametros: { disponible: true, justificacion_obligatoria: true, documento_obligatorio: false, anticipacion_dias: 2 },
    reglas_fijas: REGLAS_NOVEDAD, ...cambios,
  }
}
