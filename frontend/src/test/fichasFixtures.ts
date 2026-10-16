import type { ColaboradorDetalle, ColaboradorResumen, PaginaColaboradores } from '../modules/gestionPersonal/fichas/api'

/** Fila de listado sintética: ficha mínima, sin puesto ni unidad. */
export function resumen(cambios: Partial<ColaboradorResumen> = {}): ColaboradorResumen {
  return {
    id: 1,
    codigo: 'EMP-001',
    nombres: 'Ana',
    apellidos: 'Núñez Paredes',
    estado: 'SinVinculoVigente',
    puesto: null,
    unidad: null,
    laboral_disponible: true,
    ...cambios,
  }
}

export function pagina(items: ColaboradorResumen[], meta: Partial<PaginaColaboradores['meta']> = {}): PaginaColaboradores {
  return {
    data: items,
    meta: {
      current_page: 1,
      per_page: 20,
      total: items.length,
      last_page: 1,
      from: items.length ? 1 : null,
      to: items.length || null,
      ...meta,
    },
  }
}

/** Detalle sintético: ficha mínima sin vínculo; los opcionales ausentes van en null. */
export function detalle(cambios: Partial<ColaboradorDetalle> = {}): ColaboradorDetalle {
  return {
    id: 1,
    codigo: 'EMP-001',
    version: 1,
    tipo_identificacion: 'Cedula',
    numero_identificacion: '0102030405',
    nombres: 'Ana',
    apellidos: 'Núñez Paredes',
    fecha_nacimiento: null,
    nacionalidad: null,
    genero: null,
    estado_civil: null,
    numero_hijos: null,
    direccion: null,
    telefono: null,
    correo_contacto: null,
    emergencia_nombre: null,
    emergencia_relacion: null,
    emergencia_telefono: null,
    created_at: '2026-10-15T10:00:00Z',
    updated_at: '2026-10-15T10:00:00Z',
    laboral: { disponibilidad: 'disponible', estado: 'SinVinculoVigente', puesto: null, unidad: null },
    trayectoria: { disponibilidad: 'disponible', hechos: [] },
    capacidades: { desarrollo: false, documentos: false, registrar_ingreso: false, exportar: false },
    acciones: { editar: true },
    ...cambios,
  }
}
