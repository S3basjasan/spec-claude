import type {
  CadenaDeTipos,
  ImpactoDeDesactivacion,
  ListadoDeUnidades,
  OpcionesDeUnidad,
  TipoDeUnidad,
  TipoUnidad,
  UnidadDetalle,
  UnidadOpcion,
  UnidadPadre,
  UnidadResumen,
} from '../modules/estructura/unidades/api'

/** Cadena de ejemplo del seeder: Gerencia General (1, tipo raíz), Gerencia, Área, Departamento y Sección. */
export const TIPOS: Record<'GerenciaGeneral' | 'Gerencia' | 'Area' | 'Departamento' | 'Seccion', TipoDeUnidad> = {
  GerenciaGeneral: { id: 1, nombre: 'Gerencia General', nivel: 1 },
  Gerencia: { id: 2, nombre: 'Gerencia', nivel: 2 },
  Area: { id: 3, nombre: 'Área', nivel: 3 },
  Departamento: { id: 4, nombre: 'Departamento', nivel: 4 },
  Seccion: { id: 5, nombre: 'Sección', nivel: 5 },
}

export function unidad(cambios: Partial<UnidadResumen> = {}): UnidadResumen {
  return {
    id: 2,
    codigo: 'UO-002',
    nombre: 'Gerencia de Talento Humano',
    tipo: TIPOS.Gerencia,
    unidad_padre: { id: 1, codigo: 'UO-001', nombre: 'Gerencia General', estado: 'Activa' },
    nivel_arbol: 1,
    estado: 'Activa',
    tiene_dependientes: false,
    dependientes_activas: 0,
    posiciones_vigentes: 0,
    admite_hijas: true,
    sigue_cadena: true,
    ...cambios,
  }
}

export function detalle(cambios: Partial<UnidadDetalle> = {}): UnidadDetalle {
  return {
    ...unidad(),
    version: 1,
    fecha_creacion: '2026-10-14',
    fecha_supresion: null,
    dependientes: [],
    ...cambios,
  }
}

/** Detalle tal como lo recibe el Administrador, con la auditoría. */
export function detalleConAuditoria(cambios: Partial<UnidadDetalle> = {}): UnidadDetalle {
  return detalle({
    auditoria: {
      created_by: { id: 1, nombre: 'Ana Administradora' },
      created_at: '2026-10-14T12:00:00Z',
      updated_by: null,
      updated_at: null,
    },
    ...cambios,
  })
}

const padre = (id: number, codigo: string, nombre: string): UnidadPadre => ({ id, codigo, nombre, estado: 'Activa' })
const GENERAL = padre(1, 'UO-001', 'Gerencia General')
const TALENTO = padre(2, 'UO-002', 'Gerencia de Talento Humano')
const TECNOLOGIA = padre(3, 'UO-003', 'Gerencia de Tecnología')

/** Las doce unidades del seeder, en orden jerárquico; `UO-008` (Capacitación) está inactiva. */
export const ARBOL_DE_EJEMPLO: UnidadResumen[] = [
  unidad({ id: 1, codigo: 'UO-001', nombre: 'Gerencia General', tipo: TIPOS.GerenciaGeneral, unidad_padre: null, nivel_arbol: 0, tiene_dependientes: true, dependientes_activas: 10 }),
  unidad({ id: 2, codigo: 'UO-002', nombre: 'Gerencia de Talento Humano', unidad_padre: GENERAL, nivel_arbol: 1, tiene_dependientes: true, dependientes_activas: 3, posiciones_vigentes: 2 }),
  unidad({ id: 4, codigo: 'UO-004', nombre: 'Selección y Desarrollo', tipo: TIPOS.Area, unidad_padre: TALENTO, nivel_arbol: 2, tiene_dependientes: true, dependientes_activas: 2 }),
  unidad({ id: 7, codigo: 'UO-007', nombre: 'Reclutamiento', tipo: TIPOS.Departamento, unidad_padre: padre(4, 'UO-004', 'Selección y Desarrollo'), nivel_arbol: 3, tiene_dependientes: true, dependientes_activas: 1 }),
  unidad({ id: 11, codigo: 'UO-011', nombre: 'Selección Técnica', tipo: TIPOS.Seccion, unidad_padre: padre(7, 'UO-007', 'Reclutamiento'), nivel_arbol: 4, admite_hijas: false }),
  unidad({ id: 8, codigo: 'UO-008', nombre: 'Capacitación', tipo: TIPOS.Departamento, unidad_padre: padre(4, 'UO-004', 'Selección y Desarrollo'), nivel_arbol: 3, estado: 'Inactiva' }),
  unidad({ id: 3, codigo: 'UO-003', nombre: 'Gerencia de Tecnología', unidad_padre: GENERAL, nivel_arbol: 1, tiene_dependientes: true, dependientes_activas: 5 }),
  unidad({ id: 5, codigo: 'UO-005', nombre: 'Infraestructura', tipo: TIPOS.Area, unidad_padre: TECNOLOGIA, nivel_arbol: 2, tiene_dependientes: true, dependientes_activas: 1 }),
  unidad({ id: 9, codigo: 'UO-009', nombre: 'Soporte', tipo: TIPOS.Departamento, unidad_padre: padre(5, 'UO-005', 'Infraestructura'), nivel_arbol: 3 }),
  unidad({ id: 6, codigo: 'UO-006', nombre: 'Desarrollo de Software', tipo: TIPOS.Area, unidad_padre: TECNOLOGIA, nivel_arbol: 2, tiene_dependientes: true, dependientes_activas: 2 }),
  unidad({ id: 10, codigo: 'UO-010', nombre: 'Aplicaciones', tipo: TIPOS.Departamento, unidad_padre: padre(6, 'UO-006', 'Desarrollo de Software'), nivel_arbol: 3, tiene_dependientes: true, dependientes_activas: 1 }),
  unidad({ id: 12, codigo: 'UO-012', nombre: 'Desarrollo Web', tipo: TIPOS.Seccion, unidad_padre: padre(10, 'UO-010', 'Aplicaciones'), nivel_arbol: 4, admite_hijas: false, posiciones_vigentes: 1 }),
]

export function listado(items: UnidadResumen[] = ARBOL_DE_EJEMPLO): ListadoDeUnidades {
  return { data: items, meta: { total: items.length, coincidencias: items.length } }
}

function tipoCompleto(tipo: TipoDeUnidad, cantidad = 0): TipoUnidad {
  return { ...tipo, es_raiz: tipo.nivel === 1, cantidad_unidades: cantidad }
}

/** La cadena de ejemplo con su token. */
export function cadena(cambios: Partial<CadenaDeTipos> = {}): CadenaDeTipos {
  return { tipos: Object.values(TIPOS).map((tipo) => tipoCompleto(tipo)), version: 'token-1', ...cambios }
}

function opcion(u: UnidadResumen): UnidadOpcion {
  return {
    id: u.id,
    codigo: u.codigo,
    nombre: u.nombre,
    tipo: u.tipo,
    nivel_arbol: u.nivel_arbol,
    estado: u.estado,
    admite_hijas: u.admite_hijas,
    habilitada: u.estado === 'Activa' && u.admite_hijas,
  }
}

/** Opciones del formulario: la cadena y todas las unidades en orden de árbol. */
export function opciones(cambios: Partial<OpcionesDeUnidad> = {}): OpcionesDeUnidad {
  return { tipos: Object.values(TIPOS), unidades: ARBOL_DE_EJEMPLO.map(opcion), ...cambios }
}

export function impacto(cambios: Partial<ImpactoDeDesactivacion> = {}): ImpactoDeDesactivacion {
  return { procede: true, dependientes_activas: [], unidades_con_posiciones: [], ...cambios }
}
