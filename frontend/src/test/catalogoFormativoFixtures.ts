import type {
  AreaFormativa,
  Cobertura,
  DetalleFormativo,
  EntradaHistorial,
  FilaCatalogo,
  Meta,
  Recomendado,
  Recurso,
} from '../modules/desarrollo/catalogo-formativo/api'

export function area(sobrescribir: Partial<AreaFormativa> = {}): AreaFormativa {
  return { id: 1, nombre: 'Habilidades interpersonales', ...sobrescribir }
}

export function fila(sobrescribir: Partial<FilaCatalogo> = {}): FilaCatalogo {
  return {
    competencia_id: 1,
    codigo: 'COMP-0001',
    nombre: 'Comunicación efectiva',
    tipo: 'Cardinal',
    estado: 'Activo',
    area_formativa: area(),
    cursos_count: 2,
    microcontenidos_count: 1,
    cubierta: true,
    ...sobrescribir,
  }
}

export function recurso(sobrescribir: Partial<Recurso> = {}): Recurso {
  return {
    id: 1,
    nombre: 'Comunicación asertiva',
    tipo: 'Online',
    duracion_minutos: 90,
    formador: 'Ana Salas',
    estado: 'Disponible',
    accion: 'Iniciar',
    url_destino: null,
    ...sobrescribir,
  }
}

export function detalle(sobrescribir: Partial<DetalleFormativo> = {}): DetalleFormativo {
  return {
    competencia: {
      id: 1,
      codigo: 'COMP-0001',
      nombre: 'Comunicación efectiva',
      descripcion: 'Transmitir ideas de forma clara y respetuosa.',
      tipo: 'Cardinal',
      estado: 'Activo',
      grados: {
        A: 'Comunica con impacto en toda la organización.',
        B: 'Adapta su mensaje a cada audiencia.',
        C: 'Se expresa con claridad en su equipo.',
        D: 'Transmite instrucciones simples.',
      },
    },
    area_formativa: area(),
    cursos: [recurso({ id: 1 }), recurso({ id: 2, nombre: 'Escucha activa', tipo: 'Presencial', duracion_minutos: 120, formador: 'Luis Mora' })],
    microcontenidos: [recurso({ id: 10, nombre: 'Guía de retroalimentación', tipo: 'Lectura', duracion_minutos: 10, accion: 'Ver' })],
    ...sobrescribir,
  }
}

export function detalleAdmin(sobrescribir: Partial<DetalleFormativo> = {}): DetalleFormativo {
  return detalle({ version: 3, ultima_configuracion: { usuario: 'Marta Ríos', fecha: '2026-10-14T15:20:00Z' }, ...sobrescribir })
}

export function meta(sobrescribir: Partial<Meta> = {}): Meta {
  return { current_page: 1, per_page: 10, total: 1, last_page: 1, from: 1, to: 1, ...sobrescribir }
}

export function cobertura(sobrescribir: Partial<Cobertura> = {}): Cobertura {
  return { activas_totales: 10, activas_cubiertas: 4, porcentaje: 40, ...sobrescribir }
}

export function recomendado(sobrescribir: Partial<Recomendado> = {}): Recomendado {
  return {
    competencia_id: 5,
    nombre: 'Pensamiento analítico',
    tipo: 'Especifica',
    area_formativa: area({ id: 3, nombre: 'Técnica y datos' }),
    nivel_actual: 'C',
    nivel_requerido: 'B',
    cursos_count: 2,
    microcontenidos_count: 1,
    ...sobrescribir,
  }
}

export function entrada(sobrescribir: Partial<EntradaHistorial> = {}): EntradaHistorial {
  return {
    fecha: '2026-10-14T15:20:00Z',
    usuario: 'Marta Ríos',
    tipo_cambio: 'Curso',
    accion: 'Asociado',
    recurso: 'Comunicación asertiva',
    valor_anterior: null,
    valor_nuevo: 'Comunicación asertiva',
    ...sobrescribir,
  }
}
