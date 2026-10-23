import type {
  CompromisoSugerido,
  CuerpoAccion,
  CuerpoCompromiso,
  CuerpoPdi,
  DimensionPdi,
  Grado,
  Pdi,
  TipoAccion,
  TipoPdi,
} from './api'

/** Estado del formulario del asistente. `clave` identifica cada fila en la interfaz (no viaja al servidor). */
export interface AccionForm {
  clave: string
  tipo: TipoAccion
  descripcion: string
  persona_cargo: string
  fecha_inicio: string
  fecha_fin: string
  plan_accion_id: number | null
  vinculo_nombre: string | null
}

export interface CompromisoForm {
  clave: string
  dimension: DimensionPdi
  tipo_elemento: string | null
  elemento_id: number | null
  elemento_nombre: string | null
  descripcion: string
  nivel_actual: Grado | ''
  nivel_objetivo: Grado | ''
  indicador: string
  fecha_inicio: string
  fecha_fin: string
  acciones: AccionForm[]
}

export interface HitoForm {
  clave: string
  fecha: string
  descripcion: string
}

export interface DatosAsistente {
  colaborador_id: number | null
  colaborador_nombre: string
  tipo: TipoPdi
  objetivo: string
  puesto_objetivo_id: number | null
  responsable_id: number | null
  fecha_inicio: string
  fecha_fin: string
  origen: { tipo: 'dnf' | 'adecuacion'; id: number } | null
  compromisos: CompromisoForm[]
  hitos: HitoForm[]
}

let contador = 0
export function nuevaClave(): string {
  contador += 1

  return `f${contador}`
}

export function accionVacia(inicio: string, fin: string): AccionForm {
  return { clave: nuevaClave(), tipo: 'Curso', descripcion: '', persona_cargo: '', fecha_inicio: inicio, fecha_fin: fin, plan_accion_id: null, vinculo_nombre: null }
}

export function compromisoVacio(inicio: string, fin: string, dimension: DimensionPdi = 'Competencia'): CompromisoForm {
  return {
    clave: nuevaClave(),
    dimension,
    tipo_elemento: dimension === 'Competencia' || dimension === 'Conocimiento' ? dimension : null,
    elemento_id: null,
    elemento_nombre: null,
    descripcion: '',
    nivel_actual: '',
    nivel_objetivo: '',
    indicador: '',
    fecha_inicio: inicio,
    fecha_fin: fin,
    acciones: [accionVacia(inicio, fin)],
  }
}

export function datosVacios(responsableId: number | null, parcial: Partial<DatosAsistente> = {}): DatosAsistente {
  return {
    colaborador_id: null,
    colaborador_nombre: '',
    tipo: 'Mejora',
    objetivo: '',
    puesto_objetivo_id: null,
    responsable_id: responsableId,
    fecha_inicio: '',
    fecha_fin: '',
    origen: null,
    compromisos: [],
    hitos: [],
    ...parcial,
  }
}

/** Convierte un compromiso sugerido por la precarga en una fila del formulario, con una acción vacía. */
export function compromisoDesdeSugerido(sugerido: CompromisoSugerido, inicio: string, fin: string): CompromisoForm {
  return {
    ...compromisoVacio(inicio, fin, sugerido.dimension),
    tipo_elemento: sugerido.tipo_elemento,
    elemento_id: sugerido.elemento?.id ?? null,
    elemento_nombre: sugerido.elemento?.nombre ?? null,
    descripcion: sugerido.descripcion ?? '',
    nivel_actual: sugerido.nivel_actual ?? '',
    nivel_objetivo: sugerido.nivel_objetivo ?? '',
  }
}

/** Carga un plan existente (Borrador) en el formulario para editarlo. */
export function datosDesdePdi(pdi: Pdi): DatosAsistente {
  return {
    colaborador_id: pdi.colaborador.id,
    colaborador_nombre: pdi.colaborador.nombre,
    tipo: pdi.tipo,
    objetivo: pdi.objetivo,
    puesto_objetivo_id: pdi.puesto_objetivo?.id ?? null,
    responsable_id: pdi.responsable.id,
    fecha_inicio: pdi.fecha_inicio,
    fecha_fin: pdi.fecha_fin,
    origen: pdi.origen,
    compromisos: pdi.compromisos.map((c) => ({
      clave: nuevaClave(),
      dimension: c.dimension,
      tipo_elemento: c.tipo_elemento,
      elemento_id: c.elemento?.id ?? null,
      elemento_nombre: c.elemento?.nombre ?? null,
      descripcion: c.descripcion ?? '',
      nivel_actual: c.nivel_actual ?? '',
      nivel_objetivo: c.nivel_objetivo ?? '',
      indicador: c.indicador ?? '',
      fecha_inicio: c.fecha_inicio,
      fecha_fin: c.fecha_fin,
      acciones: c.acciones.map((a) => ({
        clave: nuevaClave(),
        tipo: a.tipo,
        descripcion: a.descripcion,
        persona_cargo: a.persona_cargo ?? '',
        fecha_inicio: a.fecha_inicio,
        fecha_fin: a.fecha_fin,
        plan_accion_id: a.vinculo?.plan_accion_id ?? null,
        vinculo_nombre: a.vinculo?.accion_nombre ?? a.vinculo?.plan_nombre ?? null,
      })),
    })),
    hitos: pdi.hitos.map((h) => ({ clave: nuevaClave(), fecha: h.fecha, descripcion: h.descripcion })),
  }
}

function textoONulo(valor: string): string | null {
  const limpio = valor.trim()

  return limpio === '' ? null : limpio
}

function cuerpoDeAccion(a: AccionForm): CuerpoAccion {
  return {
    tipo: a.tipo,
    descripcion: a.descripcion.trim(),
    persona_cargo: textoONulo(a.persona_cargo),
    fecha_inicio: a.fecha_inicio,
    fecha_fin: a.fecha_fin,
    plan_accion_id: a.plan_accion_id,
  }
}

function cuerpoDeCompromiso(c: CompromisoForm): CuerpoCompromiso {
  const conElemento = c.dimension === 'Competencia' || c.dimension === 'Conocimiento'

  return {
    dimension: c.dimension,
    tipo_elemento: conElemento ? (c.tipo_elemento ?? c.dimension) : null,
    elemento_id: conElemento ? c.elemento_id : null,
    descripcion: conElemento ? null : textoONulo(c.descripcion),
    nivel_actual: conElemento && c.nivel_actual !== '' ? c.nivel_actual : null,
    nivel_objetivo: conElemento && c.nivel_objetivo !== '' ? c.nivel_objetivo : null,
    indicador: textoONulo(c.indicador),
    fecha_inicio: c.fecha_inicio,
    fecha_fin: c.fecha_fin,
    acciones: c.acciones.map(cuerpoDeAccion),
  }
}

/** Cuerpo para crear o editar el plan; el servidor valida todo de nuevo. */
export function aCuerpo(d: DatosAsistente): CuerpoPdi {
  return {
    colaborador_id: d.colaborador_id as number,
    tipo: d.tipo,
    objetivo: d.objetivo.trim(),
    puesto_objetivo_id: d.tipo === 'Mejora' ? null : d.puesto_objetivo_id,
    responsable_id: d.responsable_id as number,
    fecha_inicio: d.fecha_inicio,
    fecha_fin: d.fecha_fin,
    origen: d.origen,
    compromisos: d.compromisos.map(cuerpoDeCompromiso),
    hitos: d.hitos.map((h) => ({ fecha: h.fecha, descripcion: h.descripcion.trim() })),
  }
}
