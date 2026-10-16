import type { Desarrollo, Experiencia, Formacion, Movilidad } from '../modules/gestionPersonal/desarrollo/api'

export function formacion(cambios: Partial<Formacion> = {}): Formacion {
  return {
    id: 1,
    nivel: 'Tercer nivel',
    titulo_programa: 'Ingeniería de Sistemas',
    institucion: 'EPN',
    estado: 'Completada',
    fecha_inicio: null,
    fecha_finalizacion: null,
    observaciones: null,
    version: 1,
    created_at: '2026-10-15T10:00:00Z',
    updated_at: '2026-10-15T10:00:00Z',
    ...cambios,
  }
}

export function experiencia(cambios: Partial<Experiencia> = {}): Experiencia {
  return {
    id: 1,
    organizacion: 'ACME',
    cargo: 'Analista',
    fecha_inicio: '2019-01-07',
    fecha_finalizacion: null,
    funciones: null,
    observaciones: null,
    version: 1,
    created_at: '2026-10-15T10:00:00Z',
    updated_at: '2026-10-15T10:00:00Z',
    ...cambios,
  }
}

export function movilidad(cambios: Partial<Movilidad> = {}): Movilidad {
  return { estado: 'NoInformada', observaciones: null, actualizada_at: null, version: 1, ...cambios }
}

/** Desarrollo sintético de una ficha sin datos, editable por el Administrador. */
export function desarrollo(cambios: Partial<Desarrollo> = {}): Desarrollo {
  return {
    colaborador_id: 1,
    hoy: '2026-10-15',
    formaciones: [],
    experiencias: [],
    movilidad: movilidad(),
    acciones: { editar: true },
    ...cambios,
  }
}
